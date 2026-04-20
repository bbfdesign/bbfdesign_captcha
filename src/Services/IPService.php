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

    /**
     * Prozess-weiter In-Memory-Cache fuer Lookups.
     * Struktur:
     *   ['blacklist_exact'  => array<string, true>,   // ip_address => true
     *    'blacklist_cidr'   => array<int, string>,     // cidr-strings
     *    'whitelist_exact'  => array<string, true>,
     *    'whitelist_cidr'   => array<int, string>,
     *    'loaded_at'        => int (unix-ts)]
     */
    private static ?array $cache = null;

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
     * IP gegen Blacklist prüfen (mit In-Memory-Cache)
     */
    public function isBlacklisted(string $ip): bool
    {
        $cache = $this->loadCache();
        if (isset($cache['blacklist_exact'][$ip])) {
            return true;
        }
        foreach ($cache['blacklist_cidr'] as $cidr) {
            if (PluginHelper::ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * IP gegen Whitelist prüfen (mit In-Memory-Cache)
     */
    public function isWhitelisted(string $ip): bool
    {
        $cache = $this->loadCache();
        if (isset($cache['whitelist_exact'][$ip])) {
            return true;
        }
        foreach ($cache['whitelist_cidr'] as $cidr) {
            if (PluginHelper::ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Einmalig alle nicht-abgelaufenen IP-Eintraege laden.
     * Ergebnis wird prozess-weit (Static) gecacht — passt fuer kurzlebige
     * PHP-Requests. Invalidierung bei jedem Write via invalidateCache().
     *
     * @return array{blacklist_exact:array<string,bool>,blacklist_cidr:array<int,string>,whitelist_exact:array<string,bool>,whitelist_cidr:array<int,string>,loaded_at:int}
     */
    private function loadCache(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = $this->db->queryPrepared(
            "SELECT `ip_address`, `ip_range`, `entry_type`
               FROM `bbf_captcha_ip_entries`
              WHERE `expires_at` IS NULL OR `expires_at` > NOW()",
            [],
            2
        );

        $blExact = [];
        $blCidr  = [];
        $wlExact = [];
        $wlCidr  = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $ip   = (string)$row->ip_address;
                $rng  = (string)($row->ip_range ?? '');
                $type = (string)$row->entry_type;

                $isCidr = $rng !== '';
                if ($type === 'blacklist') {
                    if ($isCidr) {
                        $blCidr[] = $ip . $rng;
                    } else {
                        $blExact[$ip] = true;
                    }
                } elseif ($type === 'whitelist') {
                    if ($isCidr) {
                        $wlCidr[] = $ip . $rng;
                    } else {
                        $wlExact[$ip] = true;
                    }
                }
            }
        }

        self::$cache = [
            'blacklist_exact' => $blExact,
            'blacklist_cidr'  => $blCidr,
            'whitelist_exact' => $wlExact,
            'whitelist_cidr'  => $wlCidr,
            'loaded_at'       => time(),
        ];
        return self::$cache;
    }

    /**
     * IP sperren (manuell)
     */
    public function blockIp(string $ip, string $reason = '', ?int $durationMinutes = null): void
    {
        $duration = $durationMinutes ?? $this->settings->getInt('ip_auto_block_duration', 1440);
        $this->ipEntry->autoBlock($ip, $duration, $reason ?: 'Manual block');
        self::invalidateCache();
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
        self::invalidateCache();
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
        self::invalidateCache();
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
            self::invalidateCache();
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
        self::invalidateCache();
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

    /**
     * Cache zuruecksetzen. Wird nach jedem Write (block/unblock/whitelist) aufgerufen.
     * Static, damit parallele IPService-Instanzen denselben Zustand sehen.
     */
    public static function invalidateCache(): void
    {
        self::$cache = null;
    }
}
