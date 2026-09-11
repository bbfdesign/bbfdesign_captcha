<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use Plugin\bbfdesign_captcha\src\Models\IPEntry;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Lokale Login/Admin-Firewall fuer Cockpit-Policy-Signale.
 *
 * Default ist Monitor: Wiederholungen und WATCH-Quellen werden geloggt/gescored,
 * aber echte Logins werden nicht hart blockiert. Harte Sperren brauchen das
 * explizite Setting `cockpit_auth_firewall_mode=enforce`.
 */
class AuthFirewallService
{
    private const SURFACES = ['login', 'password_reset', 'wp_login', 'wp_admin', 'jtl_admin'];
    private const MIN_WINDOW_SECONDS = 60;
    private const MAX_WINDOW_SECONDS = 3600;
    private const MIN_LOCKOUT_SECONDS = 60;
    private const MAX_LOCKOUT_SECONDS = 86400;
    private const MIN_ATTEMPTS = 3;
    private const MAX_ATTEMPTS = 100;

    private DbInterface $db;
    private Setting $settings;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    /**
     * @return array{score:int,reason:string,shouldBlock:bool,attempts:int,limit:int,mode:string}
     */
    public function inspect(string $ip, string $formType, ?string $policyState = null): array
    {
        $empty = [
            'score'       => 0,
            'reason'      => '',
            'shouldBlock' => false,
            'attempts'    => 0,
            'limit'       => 0,
            'mode'        => 'off',
        ];

        if ($ip === '' || !in_array($formType, self::SURFACES, true)) {
            return $empty;
        }

        $policy = RemoteRulesetService::firewallPolicy($this->settings);
        $cfg    = $this->config($policy);
        $mode   = $cfg['mode'];
        if ($mode === 'off') {
            return $empty;
        }

        $key = $this->counterKey($formType);
        $this->cleanupCounters($cfg['windowSeconds']);
        $attemptsBefore = $this->countAttempts($ip, $key, $cfg['windowSeconds']);
        $this->incrementCounter($ip, $key);
        $attempts = $attemptsBefore + 1;

        $score   = 0;
        $reasons = [];

        if ($policyState === 'WATCH') {
            $score += 25;
            $reasons[] = 'Cockpit-Firewall-Policy: Quelle unter Beobachtung';
        }

        if ($attempts > $cfg['maxAttempts']) {
            $score += 80;
            $reasons[] = 'Auth-Firewall: ' . $attempts . '/' . $cfg['maxAttempts']
                . ' Login/Admin-Versuche in ' . (int)ceil($cfg['windowSeconds'] / 60) . ' Min.';
        } elseif ($attempts >= max(1, $cfg['maxAttempts'] - 1)) {
            $score += 35;
            $reasons[] = 'Auth-Firewall: auffaellige Login/Admin-Wiederholung '
                . $attempts . '/' . $cfg['maxAttempts'];
        }

        $shouldBlock = $mode === 'enforce' && $attempts > $cfg['maxAttempts'];
        if ($shouldBlock) {
            $durationMinutes = max(1, (int)ceil($cfg['lockoutSeconds'] / 60));
            try {
                (new IPEntry($this->db))->autoBlock(
                    $ip,
                    $durationMinutes,
                    'Auth-Firewall: temporaere Login/Admin-Sperre nach Wiederholungen'
                );
            } catch (\Throwable) {
                // Fail-open fuer den Persistenzteil: der aktuelle Request darf
                // weiterhin anhand der Rueckgabe behandelt werden.
            }
        }

        return [
            'score'       => $score,
            'reason'      => implode('; ', $reasons),
            'shouldBlock' => $shouldBlock,
            'attempts'    => $attempts,
            'limit'       => $cfg['maxAttempts'],
            'mode'        => $mode,
        ];
    }

    /**
     * @param array<string,mixed> $policy
     * @return array{mode:string,maxAttempts:int,windowSeconds:int,lockoutSeconds:int}
     */
    private function config(array $policy): array
    {
        $mode = strtolower(trim($this->settings->get('cockpit_auth_firewall_mode', 'monitor')));
        if (!in_array($mode, ['off', 'monitor', 'enforce'], true)) {
            $mode = 'monitor';
        }

        $loginPolicy = $policy['loginProtection'] ?? [];
        if (is_array($loginPolicy) && $mode === 'monitor') {
            $defaultMode = strtolower((string)($loginPolicy['defaultMode'] ?? ''));
            if (in_array($defaultMode, ['off', 'monitor'], true)) {
                $mode = $defaultMode;
            }
        }

        $maxAttempts = $this->settings->getInt('cockpit_auth_firewall_max_attempts', 8);
        if (is_array($loginPolicy) && isset($loginPolicy['loginMaxAttemptsPerIpHash'])) {
            $maxAttempts = (int)$loginPolicy['loginMaxAttemptsPerIpHash'];
        }

        $windowSeconds = $this->settings->getInt('cockpit_auth_firewall_window_seconds', 300);
        if (is_array($loginPolicy) && isset($loginPolicy['loginWindowSeconds'])) {
            $windowSeconds = (int)$loginPolicy['loginWindowSeconds'];
        }

        $lockoutSeconds = $this->settings->getInt('cockpit_auth_firewall_lockout_seconds', 900);
        if (is_array($loginPolicy) && isset($loginPolicy['lockoutSeconds'])) {
            $lockoutSeconds = (int)$loginPolicy['lockoutSeconds'];
        }

        return [
            'mode'           => $mode,
            'maxAttempts'    => max(self::MIN_ATTEMPTS, min(self::MAX_ATTEMPTS, $maxAttempts)),
            'windowSeconds'  => max(self::MIN_WINDOW_SECONDS, min(self::MAX_WINDOW_SECONDS, $windowSeconds)),
            'lockoutSeconds' => max(self::MIN_LOCKOUT_SECONDS, min(self::MAX_LOCKOUT_SECONDS, $lockoutSeconds)),
        ];
    }

    private function counterKey(string $formType): string
    {
        return 'authfw:' . substr(preg_replace('/[^a-z0-9_]/', '_', strtolower($formType)) ?: 'login', 0, 42);
    }

    private function countAttempts(string $ip, string $key, int $windowSeconds): int
    {
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);
        $count = $this->db->queryPrepared(
            "SELECT SUM(`request_count`) AS total FROM `bbf_captcha_rate_limits`
             WHERE `ip_address` = :ip AND `form_type` = :form AND `window_start` >= :start",
            ['ip' => $ip, 'form' => $key, 'start' => $windowStart],
            1
        );

        return (int)($count->total ?? 0);
    }

    private function incrementCounter(string $ip, string $key): void
    {
        $this->db->queryPrepared(
            "INSERT INTO `bbf_captcha_rate_limits` (`ip_address`, `form_type`, `window_start`, `request_count`)
             VALUES (:ip, :form, :start, 1)",
            ['ip' => $ip, 'form' => $key, 'start' => date('Y-m-d H:i:00')]
        );
    }

    private function cleanupCounters(int $windowSeconds): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(86400, $windowSeconds * 4));
        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_rate_limits`
             WHERE `form_type` LIKE 'authfw:%' AND `window_start` < :cutoff",
            ['cutoff' => $cutoff]
        );
    }
}
