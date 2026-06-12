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

        $loggedTotal = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log` WHERE `action_taken` = 'logged'",
            [],
            1
        );

        // Ø Spam-Score und Anzahl eindeutiger geblockter IPs (eine Abfrage)
        $blockedAgg = $this->db->queryPrepared(
            "SELECT ROUND(AVG(`spam_score`), 0) AS avg_score, COUNT(DISTINCT `ip_address`) AS ips
             FROM `bbf_captcha_spam_log` WHERE `action_taken` = 'blocked'",
            [],
            1
        );

        return [
            'blocked_today'  => (int)($blockedToday->cnt ?? 0),
            'blocked_total'  => $blockedCount,
            'logged_total'   => (int)($loggedTotal->cnt ?? 0),
            'total_entries'  => $totalCount,
            'detection_rate' => $totalCount > 0 ? round(($blockedCount / $totalCount) * 100, 1) : 0,
            'avg_score'      => (int)($blockedAgg->avg_score ?? 0),
            'unique_ips'     => (int)($blockedAgg->ips ?? 0),
        ];
    }

    /**
     * Aktivität nach Tageszeit (0–23 Uhr) – wann Bots am aktivsten sind.
     * Liefert immer 24 Buckets (fehlende Stunden = 0).
     */
    public function getHourlyDistribution(int $days = 30): array
    {
        $rows = $this->db->queryPrepared(
            "SELECT HOUR(`created_at`) AS hour, COUNT(*) AS cnt
             FROM `bbf_captcha_spam_log`
             WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY HOUR(`created_at`)",
            ['days' => $days],
            2
        );

        $buckets = array_fill(0, 24, 0);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $h = (int)($row->hour ?? -1);
            if ($h >= 0 && $h <= 23) {
                $buckets[$h] = (int)($row->cnt ?? 0);
            }
        }

        return $buckets;
    }

    /**
     * Verteilung nach getroffener Aktion (blocked/logged) im Zeitraum.
     */
    public function getActionSplit(int $days = 30): array
    {
        $rows = $this->db->queryPrepared(
            "SELECT `action_taken`, COUNT(*) AS cnt
             FROM `bbf_captcha_spam_log`
             WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY `action_taken`",
            ['days' => $days],
            2
        );

        return is_array($rows) ? $rows : [];
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
             LIMIT " . (int)$limit,
            ['days' => $days],
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
             LIMIT " . (int)$limit,
            [],
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
            "SELECT `ip_address`, COUNT(*) AS cnt, MAX(`created_at`) AS last_seen
             FROM `bbf_captcha_spam_log`
             WHERE `action_taken` = 'blocked'
             AND `created_at` >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY `ip_address`
             ORDER BY cnt DESC
             LIMIT " . (int)$limit,
            ['days' => $days],
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
     * Spam-Welle-Benachrichtigung über das native JTL-Shop-Mailsystem senden.
     */
    private function sendSpamWaveEmail(string $to, int $blockedCount, int $window): void
    {
        $shopName = \JTL\Shop::getSettingValue(\CONF_GLOBAL, 'global_shopname') ?: 'JTL-Shop';
        $shopUrl  = \JTL\Shop::getURL();

        $subject = '[' . $shopName . '] Spam-Welle erkannt – ' . $blockedCount . ' Blocks';

        $topIPs = $this->getTopBlockedIPs(1, 5); // Letzte 24h, Top 5
        $rows   = '';
        foreach ($topIPs as $ip) {
            $rows .= '<tr>'
                  . '<td style="padding:4px 16px 4px 0;font-family:monospace;border-bottom:1px solid #f0f0f0;">'
                  . htmlspecialchars((string)($ip->ip_address ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                  . '<td style="padding:4px 0;color:#dc2626;border-bottom:1px solid #f0f0f0;">'
                  . (int)($ip->cnt ?? 0) . ' Versuche</td>'
                  . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="2" style="padding:4px 0;color:#888;">(keine Daten)</td></tr>';
        }

        $esc      = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2430;max-width:560px;">'
              . '<h2 style="color:#db2e87;margin:0 0 12px;">Spam-Welle erkannt</h2>'
              . '<p style="margin:0 0 16px;">Im Shop <strong>' . $esc($shopName) . '</strong> wurden '
              . '<strong>' . $blockedCount . '</strong> Spam-Versuche in den letzten '
              . '<strong>' . $window . ' Minuten</strong> geblockt.</p>'
              . '<table style="border-collapse:collapse;margin:0 0 16px;width:100%;max-width:480px;">'
              . '<thead><tr>'
              . '<th style="text-align:left;padding:0 16px 6px 0;border-bottom:2px solid #e5e7eb;">IP-Adresse</th>'
              . '<th style="text-align:left;padding:0 0 6px;border-bottom:2px solid #e5e7eb;">Treffer</th>'
              . '</tr></thead><tbody>' . $rows . '</tbody></table>'
              . '<p style="margin:0 0 4px;font-weight:bold;">Empfehlung</p>'
              . '<ul style="margin:0 0 16px;padding-left:18px;">'
              . '<li>Spam-Log im Plugin-Admin pr&uuml;fen</li>'
              . '<li>ALTCHA-Schwierigkeitsgrad ggf. erh&ouml;hen</li>'
              . '<li>Zus&auml;tzliche Schutzmethoden aktivieren</li>'
              . '</ul>'
              . '<p style="margin:0;color:#888;font-size:12px;">'
              . '<a href="' . $esc($shopUrl) . '" style="color:#db2e87;">' . $esc($shopUrl) . '</a><br>'
              . 'Diese Benachrichtigung wird h&ouml;chstens einmal pro Stunde versendet &middot; BBF Captcha &amp; Spam-Schutz'
              . '</p></div>';

        $this->sendViaShopMailer($to, $subject, $html);
    }

    /**
     * Versendet eine HTML-Mail über das native JTL-Shop-Mailsystem
     * (\JTL\Mail\Mailer) – kein PHP-mail() mehr. Absender stammt aus der
     * Shop-Konfiguration (Master-Absender). Fehler werden nur geloggt; ein
     * fehlgeschlagener Versand darf nichts kippen (Fail-open).
     *
     * Orientiert an der Mailversand-Umsetzung des BBF-Ticket-Plugins.
     */
    private function sendViaShopMailer(string $to, string $subject, string $html): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $mailer = \JTL\Shop::Container()->get(\JTL\Mail\Mailer::class);

            $fromMail = trim((string)(\JTL\Shop::getSettingValue(\CONF_EMAILS, 'email_master_absender') ?? ''));
            $fromName = trim((string)(\JTL\Shop::getSettingValue(\CONF_EMAILS, 'email_master_absender_name') ?? ''));
            if ($fromName === '') {
                $fromName = trim((string)(\JTL\Shop::getSettingValue(\CONF_GLOBAL, 'global_shopname') ?? ''));
            }

            $mail = new \JTL\Mail\Mail\Mail();
            $mail->setToMail($to);
            $mail->setSubject($subject);
            // JTL rendert freie Mail-Bodys über Smarty – Inline-CSS mit {…} würde
            // sonst als Template-Tag interpretiert. {literal} erhält das HTML.
            $mail->setBodyHTML('{literal}' . $html . '{/literal}');
            if ($fromMail !== '' && filter_var($fromMail, FILTER_VALIDATE_EMAIL)) {
                $mail->setFromMail($fromMail);
            }
            if ($fromName !== '') {
                $mail->setFromName($fromName);
            }

            return $mailer->send($mail) !== false;
        } catch (\Throwable $e) {
            try {
                \JTL\Shop::Container()->getLogService()->warning(
                    'BBF Captcha Mailversand fehlgeschlagen: ' . $e->getMessage()
                );
            } catch (\Throwable) {
                // bewusst still
            }
            return false;
        }
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
     * Vollständige geplante Bereinigung: Spam-Log (Aufbewahrung), alte
     * Rate-Limit-Einträge und abgelaufene IP-Auto-Blocks. Für Cron-Aufruf und
     * den automatischen Fallback. Gibt die gelöschten Mengen zurück.
     *
     * @return array{spam_log:int,rate_limits:int}
     */
    public function runScheduledCleanup(): array
    {
        $retentionDays = max(1, $this->settings->getInt('log_retention_days', 90));
        $spamDeleted   = $this->spamLog->cleanup($retentionDays);

        // Rate-Limit-Fenster älter als 1 Tag sind irrelevant.
        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_rate_limits` WHERE `window_start` < DATE_SUB(NOW(), INTERVAL 1 DAY)",
            []
        );

        // Abgelaufene IP-Auto-Blocks entfernen.
        $ipEntry = new \Plugin\bbfdesign_captcha\src\Models\IPEntry($this->db);
        $ipEntry->cleanupExpired();

        $this->settings->set('cleanup_last_run', (string)time());

        return ['spam_log' => (int)$spamDeleted];
    }

    /**
     * Automatischer Fallback: führt die Bereinigung höchstens einmal pro Intervall
     * aus (Standard 24 h), getriggert durch normalen Shop-Traffic – unabhängig
     * davon, ob ein echter Cron eingerichtet ist.
     */
    public function runIfDue(): bool
    {
        if (!$this->settings->getBool('auto_cleanup')) {
            return false;
        }
        $intervalHours = max(1, $this->settings->getInt('cleanup_interval_hours', 24));
        $lastRun       = (int)$this->settings->get('cleanup_last_run', '0');
        if ($lastRun > 0 && (time() - $lastRun) < $intervalHours * 3600) {
            return false;
        }

        $this->runScheduledCleanup();
        return true;
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
             LIMIT " . (int)$limit,
            [],
            2
        );

        return is_array($rows) ? $rows : [];
    }
}
