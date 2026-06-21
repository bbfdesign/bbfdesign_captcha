<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Meldet Operator-Feedback (FN „ist Spam" / FP „Fehlalarm") aus dem Spam-Log an das
 * CaptchaCockpit-Trainingszentrum: POST /api/v1/feedback (HMAC-signiert).
 *
 * - Sendet NUR pseudonyme Felder (formType, contentFp, ipHash, emailDomain,
 *   occurredAt, rulesetVersion) – KEIN Klartext/PII, kein note-Freitext.
 * - Fail-open: ein Fehler verschluckt sich; das Backend zeigt den Status, blockt nichts.
 * - Nur aktiv bei cockpit_enabled + Endpoint + Secret.
 *
 * Vertrag: ~/captchacockpit/docs/API-CONTRACT.md §6.
 */
class CockpitFeedbackService
{
    public const TYPE_SPAM_MISSED    = 'SPAM_MISSED';
    public const TYPE_FALSE_POSITIVE = 'FALSE_POSITIVE';

    private const HTTP_TIMEOUT  = 8;
    private const IGNORE_FIELDS = ['password', 'passwort', 'pass', 'pass2', 'token', 'jtl_token', 'bbf_altcha', 'bbf_ct', 'hp_', 'jtl_hp'];

    private DbInterface $db;
    private Setting $settings;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    public function isEnabled(): bool
    {
        return $this->settings->getBool('cockpit_enabled')
            && $this->settings->get('cockpit_endpoint') !== ''
            && $this->settings->get('cockpit_secret') !== '';
    }

    /**
     * Meldet einen Spam-Log-Eintrag ans Cockpit.
     *
     * @return array{success:bool,message:string}
     */
    public function report(int $spamLogId, string $type): array
    {
        if (!in_array($type, [self::TYPE_SPAM_MISSED, self::TYPE_FALSE_POSITIVE], true)) {
            return ['success' => false, 'message' => 'Ungültiger Meldungstyp'];
        }
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Cockpit-Integration ist nicht aktiv'];
        }

        $row = $this->db->queryPrepared(
            'SELECT `ip_address`, `form_type`, `request_data`, `created_at`
             FROM `bbf_captcha_spam_log` WHERE `id` = :id',
            ['id' => $spamLogId],
            1
        );
        if ($row === null) {
            return ['success' => false, 'message' => 'Eintrag nicht gefunden'];
        }

        [$contentFp, $emailDomain] = $this->derive((string)($row->request_data ?? ''));
        $ip     = (string)($row->ip_address ?? '');
        $pepper = $this->settings->get('cockpit_pepper');

        $payload = array_filter([
            'type'           => $type,
            'occurredAt'     => $this->toIso((string)($row->created_at ?? '')),
            'formType'       => (string)($row->form_type ?? ''),
            'contentFp'      => $contentFp,
            'ipHash'         => ($ip !== '' && $pepper !== '') ? hash_hmac('sha256', $ip, $pepper) : null,
            'emailDomain'    => $emailDomain,
            'rulesetVersion' => $this->settings->getInt('cockpit_ruleset_version', 0) ?: null,
        ], static fn ($v) => $v !== null);

        $ok = $this->send('/api/v1/feedback', $payload);

        // Fail-open: Meldung ist best-effort. Klare, ehrliche Rückmeldung an den Operator.
        return $ok
            ? ['success' => true,  'message' => 'Ans Cockpit-Trainingszentrum gemeldet']
            : ['success' => false, 'message' => 'Cockpit derzeit nicht erreichbar – bitte später erneut melden'];
    }

    /**
     * Pseudonyme Merkmale aus den Roh-Request-Daten ableiten (kein Klartext nach außen).
     * Normalisierung identisch zur Telemetrie, damit das Cockpit Feedback↔Event über
     * contentFp korrelieren kann.
     *
     * @return array{0:?string,1:?string}  [contentFp, emailDomain]
     */
    private function derive(string $requestDataJson): array
    {
        $data = json_decode($requestDataJson, true);
        if (!is_array($data)) {
            return [null, null];
        }
        $texts       = [];
        $emailDomain = null;
        foreach ($this->flatten($data) as $key => $value) {
            $lkey = strtolower((string)$key);
            foreach (self::IGNORE_FIELDS as $skip) {
                if (str_contains($lkey, $skip)) {
                    continue 2;
                }
            }
            if ($emailDomain === null && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $parts       = explode('@', $value);
                $emailDomain = strtolower(end($parts));
                continue;
            }
            if ($value !== '') {
                $texts[] = $value;
            }
        }
        $combined = trim(implode(' ', $texts));
        $fp = $combined !== ''
            ? hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', $combined) ?? $combined, 'UTF-8'))
            : null;

        return [$fp, $emailDomain];
    }

    /**
     * @param array<mixed> $data
     * @param array<string,string> $out
     * @return array<string,string>
     */
    private function flatten(array $data, array &$out = []): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $this->flatten($v, $out);
            } elseif (is_string($v) || is_numeric($v)) {
                $out[(string)$k] = (string)$v;
            }
        }
        return $out;
    }

    private function toIso(string $mysqlDt): string
    {
        try {
            return (new \DateTimeImmutable($mysqlDt, new \DateTimeZone('UTC')))->format('c');
        } catch (\Throwable) {
            return gmdate('c');
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function send(string $path, array $payload): bool
    {
        $endpoint = rtrim($this->settings->get('cockpit_endpoint'), '/');
        $secret   = $this->settings->get('cockpit_secret');
        if ($endpoint === '' || $secret === '') {
            return false;
        }
        $instanceId = '';
        try {
            $instanceId = (new LicenseService($this->db, $this->settings))->instanceId();
        } catch (\Throwable) {
        }

        $body     = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $signedAt = (string)time();
        $sig      = hash_hmac('sha256', $body . '|' . $signedAt, $secret);

        $ch = curl_init($endpoint . $path);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Cockpit-Instance: ' . $instanceId,
                'X-Signed-At: ' . $signedAt,
                'X-Signature: ' . $sig,
                'User-Agent: bbfdesign-captcha-feedback/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            if ($this->settings->getBool('debug_mode')) {
                try {
                    Shop::Container()->getLogService()->warning('BBF Captcha feedback: HTTP ' . $code . ($err !== '' ? ' (' . $err . ')' : ''));
                } catch (\Throwable) {
                }
            }
            return false;
        }
        return true;
    }
}
