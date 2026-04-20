<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Models\SpamLog;

/**
 * Logging & Statistiken + E-Mail-Benachrichtigung bei Spam-Welle
 *
 * - Spam-Log Verwaltung (lesen, filtern, exportieren, bereinigen)
 * - Dashboard-Statistiken (KPIs, Charts, Trends)
 * - E-Mail-Alert bei Spam-Welle (>X Blocks in Y Minuten)
 * - Auto-Cleanup (Einträge älter als X Tage)
 */
class SpamLogService
{
    private DbInterface $db;
    private Setting $settings;
    private SpamLog $spamLog;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
        $this->spamLog  = new SpamLog($db);
    }

    // ─── Dashboard-Statistiken ──────────────────────────────

    /**
     * KPI-Daten für das Dashboard
     */
    public function getKPIs(): array
    {
        $today = date('Y-m-d');

        $blockedToday = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log`
             WHERE `action_taken` = 'blocked' AND DATE(`created_at`) = :today",
            ['today' => $today],
            1
        );

        $blockedTotal = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log` WHERE `action_taken` = 'blocked'",
            [],
            1
        );

        $totalEntries = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log`",
            [],
            1
        );

        $totalCount   = (int)($totalEntries->cnt ?? 0);
        $blockedCount = (int)($blockedTotal->cnt ?? 0);

        return [
            'blocked_today'  => (int)($blockedToday->cnt ?? 0),
            'blocked_total'  => $blockedCount,
            'total_entries'  => $totalCount,
            'detection_rate' => $totalCount > 0 ? round(($blockedCount / $totalCount) * 100, 1) : 0,
        ];
    }

    /**
     * Spam-Verlauf für Charts (nach Tagen gruppiert)
     */
    public function getSpamHistory(int $days = 30): array
    {
        $rows = $this->db->queryPrepared(
            "SELECT DATE(`created_at`) AS date, `detection_method`, COUNT(*) AS cnt
             FROM `bbf_captcha_spam_log`
             WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(`created_at`), `detection_method`
             ORDER BY date ASC",
            ['days' => $days],
            2
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Verteilung nach Methode
     */
    public function getMethodDistribution(int $days = 30): array
    {
        $rows = $this->db->queryPrepared(
            "SELECT `detection_method`, COUNT(*) AS cnt
             FROM `bbf_captcha_spam_log`
             WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY `detection_method`
             ORDER BY cnt DESC",
            ['days' => $days],
            2
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Top-Formulare nach Spam-Aufkommen
     */
    public function getTopForms(int $days = 30, int $limit = 10): array
    {
        $rows = $this->db->queryPrepared(
            "SELECT `form_type`, COUNT(*) AS cnt
             FROM `bbf_captcha_spam_log`
             WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY `form_type`
             ORDER BY cnt DESC
             LIMIT :lim",
            ['days' => $days, 'lim' => max(1, $limit)],
            2
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Letzte Spam-Versuche
     */
    public function getRecentSpam(int $limit = 20): array
    {
        $rows = $this->db->queryPrepared(
            "SELECT `id`, `ip_address`, `form_type`, `detection_method`, `spam_score`,
                    `action_taken`, `created_at`
             FROM `bbf_captcha_spam_log`
             ORDER BY `created_at` DESC
             LIMIT :lim",
            ['lim' => max(1, $limit)],
            2
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Trend-Daten: Vergleich der letzten 7 Tage mit den 7 davor
     */
    public function getTrend(): array
    {
        $current = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log`
             WHERE `action_taken` = 'blocked'
             AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [],
            1
        );

        $previous = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log`
             WHERE `action_taken` = 'blocked'
             AND `created_at` >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             AND `created_at` < DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [],
            1
        );

        $cur  = (int)($current->cnt ?? 0);
        $prev = (int)($previous->cnt ?? 0);

        $change = 0;
        if ($prev > 0) {
            $change = round((($cur - $prev) / $prev) * 100, 1);
        }

        return [
            'current_week'  => $cur,
            'previous_week' => $prev,
            'change_percent' => $change,
            'direction'      => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
        ];
    }

    /**
     * Top geblockte IPs
     */
    public function getTopBlockedIPs(int $days = 30, int $limit = 10): array
    {
        $rows = $this->db->queryPrepared(
            "SELECT `ip_address`, COUNT(*) AS cnt
             FROM `bbf_captcha_spam_log`
             WHERE `action_taken` = 'blocked'
             AND `created_at` >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY `ip_address`
             ORDER BY cnt DESC
             LIMIT :lim",
            ['days' => $days, 'lim' => max(1, $limit)],
            2
        );

        return is_array($rows) ? $rows : [];
    }

    // ─── E-Mail-Benachrichtigung ────────────────────────────

    /**
     * Prüfe ob eine Spam-Welle vorliegt und sende ggf. E-Mail
     *
     * Wird nach jedem geblockten Spam-Versuch aufgerufen.
     */
    public function checkSpamWaveAlert(): void
    {
        if (!$this->settings->getBool('email_alert_enabled')) {
            return;
        }

        $address   = $this->settings->get('email_alert_address');
        $threshold = $this->settings->getInt('email_alert_threshold', 50);
        $window    = $this->settings->getInt('email_alert_window', 60);

        if (empty($address)) {
            return;
        }

        // Bereits kürzlich gesendet? (Cooldown: 1 Stunde)
        $lastAlert = $this->settings->get('email_alert_last_sent');
        if (!empty($lastAlert) && (time() - strtotime($lastAlert)) < 3600) {
            return;
        }

        // Blocks im Zeitfenster zählen
        $count = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log`
             WHERE `action_taken` = 'blocked'
             AND `created_at` >= DATE_SUB(NOW(), INTERVAL :window MINUTE)",
            ['window' => $window],
            1
        );

        if ((int)($count->cnt ?? 0) < $threshold) {
            return;
        }

        // E-Mail senden
        $this->sendSpamWaveEmail($address, (int)$count->cnt, $window);

        // Cooldown setzen
        $this->settings->set('email_alert_last_sent', date('Y-m-d H:i:s'), 'general');
    }

    /**
     * Spam-Welle E-Mail senden
     */
    private function sendSpamWaveEmail(string $to, int $blockedCount, int $window): void
    {
        $shopName = \JTL\Shop::getSettingValue(\CONF_GLOBAL, 'global_shopname') ?: 'JTL-Shop';
        $shopUrl  = \JTL\Shop::getURL();

        $subject = '[' . $shopName . '] Spam-Welle erkannt – ' . $blockedCount . ' Blocks';

        $topIPs = $this->getTopBlockedIPs(1, 5); // Letzte 24h, Top 5
        $ipList = '';
        foreach ($topIPs as $ip) {
            $ipList .= '  - ' . $ip->ip_address . ' (' . $ip->cnt . ' Versuche)' . "\n";
        }

        $body = "Spam-Welle erkannt!\n\n"
              . "Shop: " . $shopName . "\n"
              . "URL: " . $shopUrl . "\n\n"
              . "Es wurden " . $blockedCount . " Spam-Versuche in den letzten " . $window . " Minuten geblockt.\n\n"
              . "Top geblockte IPs:\n"
              . ($ipList ?: "  (keine Daten)\n")
              . "\n"
              . "Empfehlung:\n"
              . "- Prüfen Sie das Spam-Log im Plugin-Admin\n"
              . "- Erhöhen Sie ggf. den ALTCHA-Schwierigkeitsgrad\n"
              . "- Aktivieren Sie zusätzliche Schutzmethoden\n\n"
              . "Diese E-Mail wird maximal 1x pro Stunde versendet.\n"
              . "---\n"
              . "BBF Captcha & Spam-Schutz Plugin";

        $headers = 'From: ' . $shopName . ' <noreply@' . parse_url($shopUrl, PHP_URL_HOST) . '>' . "\r\n"
                 . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

        @mail($to, $subject, $body, $headers);
    }

    // ─── Cleanup ────────────────────────────────────────────

    /**
     * Auto-Cleanup: Alte Log-Einträge löschen
     *
     * @return int Anzahl gelöschter Einträge
     */
    public function cleanup(): int
    {
        if (!$this->settings->getBool('auto_cleanup')) {
            return 0;
        }

        $retentionDays = $this->settings->getInt('log_retention_days', 90);
        return $this->spamLog->cleanup($retentionDays);
    }

    /**
     * Manuelle Bereinigung
     */
    public function clearOlderThan(int $days): int
    {
        return $this->spamLog->cleanup($days);
    }

    /**
     * Komplettes Log leeren
     */
    public function clearAll(): void
    {
        $this->db->queryPrepared("TRUNCATE TABLE `bbf_captcha_spam_log`", []);
    }

    // ─── Export ─────────────────────────────────────────────

    /**
     * Spam-Log als CSV-Daten exportieren
     */
    public function exportCsv(int $limit = 10000): array
    {
        $rows = $this->db->queryPrepared(
            "SELECT `id`, `ip_address`, `form_type`, `detection_method`, `spam_score`,
                    `action_taken`, `user_agent`, `is_false_positive`, `created_at`
             FROM `bbf_captcha_spam_log`
             ORDER BY `created_at` DESC
             LIMIT :lim",
            ['lim' => max(1, $limit)],
            2
        );

        return is_array($rows) ? $rows : [];
    }
}
