<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Rate Limiting (Sliding Window Counter)
 *
 * - Max. Formular-Submissions pro IP pro Zeitfenster
 * - Konfigurierbar pro Formular-Typ
 * - Sliding Window (nicht Fixed Window)
 * - Auto-Cleanup per Pseudo-Cron (max 1x/Minute)
 */
class RateLimitService
{
    private DbInterface $db;
    private Setting $settings;

    /** Pseudo-Cron: Letzte Cleanup-Zeit */
    private static ?int $lastCleanup = null;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    /**
     * Rate Limit prüfen
     *
     * @return array{valid: bool, reason: string, score: int}
     */
    public function validate(string $ip, string $formType): array
    {
        if (!$this->settings->getBool('rate_limit_enabled')) {
            return ['valid' => true, 'reason' => '', 'score' => 0];
        }

        $maxRequests  = $this->settings->getInt('rate_limit_max_requests', 10);
        $windowMinutes = $this->settings->getInt('rate_limit_window_minutes', 5);

        // Pseudo-Cron: Alte Einträge aufräumen (max 1x/Minute)
        $this->pseudoCleanup($windowMinutes);

        // Aktuelle Requests im Fenster zählen (Sliding Window)
        $windowStart = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        $count = $this->db->queryPrepared(
            "SELECT SUM(`request_count`) AS total FROM `bbf_captcha_rate_limits`
             WHERE `ip_address` = :ip AND `form_type` = :form AND `window_start` >= :start",
            ['ip' => $ip, 'form' => $formType, 'start' => $windowStart],
            1
        );

        $currentCount = (int)($count->total ?? 0);

        // Request zählen
        $this->incrementCounter($ip, $formType);

        if ($currentCount >= $maxRequests) {
            return [
                'valid'  => false,
                'reason' => 'Rate Limit überschritten: ' . $currentCount . '/' . $maxRequests
                          . ' in ' . $windowMinutes . ' Min.',
                'score'  => 60,
            ];
        }

        return ['valid' => true, 'reason' => '', 'score' => 0];
    }

    /**
     * Request-Counter inkrementieren
     */
    private function incrementCounter(string $ip, string $formType): void
    {
        // Aktuelles Minuten-Fenster (für Sliding Window Bucket)
        $currentMinute = date('Y-m-d H:i:00');

        $this->db->queryPrepared(
            "INSERT INTO `bbf_captcha_rate_limits` (`ip_address`, `form_type`, `window_start`, `request_count`)
             VALUES (:ip, :form, :start, 1)
             ON DUPLICATE KEY UPDATE `request_count` = `request_count` + 1",
            ['ip' => $ip, 'form' => $formType, 'start' => $currentMinute]
        );
    }

    /**
     * Pseudo-Cron: Alte Rate-Limit-Einträge aufräumen
     * Wird maximal 1x pro Minute ausgeführt.
     */
    private function pseudoCleanup(int $windowMinutes): void
    {
        $now = time();

        if (self::$lastCleanup !== null && ($now - self::$lastCleanup) < 60) {
            return;
        }

        self::$lastCleanup = $now;

        // Einträge älter als 2x das Zeitfenster löschen
        $cutoff = date('Y-m-d H:i:s', $now - ($windowMinutes * 120));

        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_rate_limits` WHERE `window_start` < :cutoff",
            ['cutoff' => $cutoff]
        );
    }

    /**
     * Rate-Limit-Status für eine IP abrufen
     */
    public function getStatus(string $ip, string $formType): array
    {
        $maxRequests   = $this->settings->getInt('rate_limit_max_requests', 10);
        $windowMinutes = $this->settings->getInt('rate_limit_window_minutes', 5);
        $windowStart   = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        $count = $this->db->queryPrepared(
            "SELECT SUM(`request_count`) AS total FROM `bbf_captcha_rate_limits`
             WHERE `ip_address` = :ip AND `form_type` = :form AND `window_start` >= :start",
            ['ip' => $ip, 'form' => $formType, 'start' => $windowStart],
            1
        );

        $current = (int)($count->total ?? 0);

        return [
            'current'   => $current,
            'limit'     => $maxRequests,
            'window'    => $windowMinutes,
            'remaining' => max(0, $maxRequests - $current),
            'exceeded'  => $current >= $maxRequests,
        ];
    }
}
