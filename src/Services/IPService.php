<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Models\IPEntry;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;

/**
 * IP-basierter Schutz
 *
 * - IP-Blacklist (manuell + automatisch)
 * - IP-Whitelist (eigene IPs, Zahlungsanbieter)
 * - CIDR-Range Support (192.168.0.0/24)
 * - Auto-Blacklist nach X Spam-Versuchen in Y Minuten
 * - Temporäre Sperre (konfigurierbar)
 * - GeoIP vorbereitet (lokal, keine externen Lookups)
 * - In-Memory Cache nach erstem Laden
 */
class IPService
{
    private DbInterface $db;
    private Setting $settings;
    private IPEntry $ipEntry;

    /** In-Memory Cache */
    private ?array $blacklistCache = null;
    private ?array $whitelistCache = null;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
        $this->ipEntry  = new IPEntry($db);
    }

    /**
     * IP validieren
     *
     * @return array{valid: bool, reason: string, score: int}
     */
    public function validate(string $ip): array
    {
        // Whitelist hat Vorrang
        if ($this->isWhitelisted($ip)) {
            return ['valid' => true, 'reason' => '', 'score' => 0];
        }

        // Blacklist prüfen
        if ($this->isBlacklisted($ip)) {
            return [
                'valid'  => false,
                'reason' => 'IP ist auf der Blacklist',
                'score'  => 100,
            ];
        }

        return ['valid' => true, 'reason' => '', 'score' => 0];
    }

    /**
     * IP gegen Blacklist prüfen (mit Cache)
     */
    public function isBlacklisted(string $ip): bool
    {
        return $this->ipEntry->isBlacklisted($ip);
    }

    /**
     * IP gegen Whitelist prüfen (mit Cache)
     */
    public function isWhitelisted(string $ip): bool
    {
        return $this->ipEntry->isWhitelisted($ip);
    }

    /**
     * IP sperren (manuell)
     */
    public function blockIp(string $ip, string $reason = '', ?int $durationMinutes = null): void
    {
        $duration = $durationMinutes ?? $this->settings->getInt('ip_auto_block_duration', 1440);
        $this->ipEntry->autoBlock($ip, $duration, $reason ?: 'Manual block');
        $this->invalidateCache();
    }

    /**
     * IP entsperren
     */
    public function unblockIp(string $ip): void
    {
        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_ip_entries` WHERE `ip_address` = :ip AND `entry_type` = 'blacklist'",
            ['ip' => $ip]
        );
        $this->invalidateCache();
    }

    /**
     * IP zur Whitelist hinzufügen
     */
    public function whitelistIp(string $ip, string $reason = ''): void
    {
        $this->db->queryPrepared(
            "INSERT IGNORE INTO `bbf_captcha_ip_entries` (`ip_address`, `entry_type`, `reason`, `auto_added`)
             VALUES (:ip, 'whitelist', :reason, 0)",
            ['ip' => $ip, 'reason' => $reason]
        );
        $this->invalidateCache();
    }

    /**
     * Auto-Block prüfen: Nach X Versuchen in Y Minuten → Sperre
     */
    public function checkAutoBlock(string $ip): bool
    {
        if (!$this->settings->getBool('ip_auto_block_enabled')) {
            return false;
        }

        $attempts = $this->settings->getInt('ip_auto_block_attempts', 5);
        $window   = $this->settings->getInt('ip_auto_block_window', 10);
        $duration = $this->settings->getInt('ip_auto_block_duration', 1440);

        $count = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log`
             WHERE `ip_address` = :ip
             AND `action_taken` = 'blocked'
             AND `created_at` >= DATE_SUB(NOW(), INTERVAL :window MINUTE)",
            ['ip' => $ip, 'window' => $window],
            1
        );

        if ((int)($count->cnt ?? 0) >= $attempts) {
            $this->ipEntry->autoBlock(
                $ip,
                $duration,
                'Auto-Block: ' . $attempts . '+ Versuche in ' . $window . 'min'
            );
            $this->invalidateCache();
            return true;
        }

        return false;
    }

    /**
     * Abgelaufene Einträge bereinigen
     */
    public function cleanupExpired(): int
    {
        $this->ipEntry->cleanupExpired();
        $this->invalidateCache();
        return 0;
    }

    /**
     * Statistiken
     */
    public function getStats(): array
    {
        $blackCount = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_ip_entries`
             WHERE `entry_type` = 'blacklist' AND (`expires_at` IS NULL OR `expires_at` > NOW())",
            [],
            1
        );
        $whiteCount = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_ip_entries` WHERE `entry_type` = 'whitelist'",
            [],
            1
        );

        return [
            'blacklist_count' => (int)($blackCount->cnt ?? 0),
            'whitelist_count' => (int)($whiteCount->cnt ?? 0),
        ];
    }

    private function invalidateCache(): void
    {
        $this->blacklistCache = null;
        $this->whitelistCache = null;
    }
}
