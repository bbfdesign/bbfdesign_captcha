<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Models;

use JTL\DB\DbInterface;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;

class IPEntry
{
    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Prüfe ob eine IP auf der Blacklist steht
     */
    public function isBlacklisted(string $ip): bool
    {
        // Exakte IP-Treffer
        $result = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_ip_entries`
             WHERE `entry_type` = 'blacklist'
             AND `ip_address` = :ip
             AND (`expires_at` IS NULL OR `expires_at` > NOW())",
            ['ip' => $ip],
            1
        );

        if ((int)($result->cnt ?? 0) > 0) {
            return true;
        }

        // CIDR-Range-Treffer
        $ranges = $this->db->queryPrepared(
            "SELECT `ip_address`, `ip_range` FROM `bbf_captcha_ip_entries`
             WHERE `entry_type` = 'blacklist'
             AND `ip_range` IS NOT NULL
             AND (`expires_at` IS NULL OR `expires_at` > NOW())",
            [],
            2
        );

        if (is_array($ranges)) {
            foreach ($ranges as $range) {
                $cidr = $range->ip_address . ($range->ip_range ?: '');
                if (PluginHelper::ipInCidr($ip, $cidr)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Prüfe ob eine IP auf der Whitelist steht
     */
    public function isWhitelisted(string $ip): bool
    {
        $result = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_ip_entries`
             WHERE `entry_type` = 'whitelist' AND `ip_address` = :ip",
            ['ip' => $ip],
            1
        );

        return (int)($result->cnt ?? 0) > 0;
    }

    /**
     * IP zur Blacklist hinzufügen (auto)
     */
    public function autoBlock(string $ip, int $durationMinutes, string $reason): void
    {
        $expiresAt = $durationMinutes > 0
            ? date('Y-m-d H:i:s', time() + ($durationMinutes * 60))
            : null;

        $this->db->queryPrepared(
            "INSERT IGNORE INTO `bbf_captcha_ip_entries`
             (`ip_address`, `entry_type`, `reason`, `auto_added`, `expires_at`)
             VALUES (:ip, 'blacklist', :reason, 1, :expires)",
            ['ip' => $ip, 'reason' => $reason, 'expires' => $expiresAt]
        );
    }

    /**
     * Abgelaufene Einträge entfernen.
     * LIMIT verhindert, dass eine grosse Aufraeumung einen Request blockiert.
     */
    public function cleanupExpired(): void
    {
        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_ip_entries`
             WHERE `expires_at` IS NOT NULL AND `expires_at` < NOW()
             LIMIT 1000",
            []
        );
    }
}
