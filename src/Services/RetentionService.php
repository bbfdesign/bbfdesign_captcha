<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Zentrale Retention-Policy fuer alle wachstums-kritischen Tabellen.
 *
 * - bbf_captcha_rate_limits:  alles aelter als 1 Stunde weg (rollierend)
 * - bbf_captcha_spam_log:     konfigurierbar via log_retention_days (Default 30)
 *                             + harte Obergrenze via spam_log_max_rows
 * - bbf_captcha_ip_entries:   abgelaufene Blacklist-Eintraege entfernen
 *
 * Alle DELETEs mit LIMIT, damit ein Request nicht die DB blockiert.
 * Aufruf erfolgt per Pseudo-Cron aus FormProtection / Bootstrap —
 * frequency-gated per Setting `retention_last_run` (mind. 60s Abstand).
 */
class RetentionService
{
    private const SPAM_LOG_LIMIT      = 5000;
    private const RATE_LIMIT_LIMIT    = 1000;
    private const IP_EXPIRED_LIMIT    = 1000;
    private const SPAM_LOG_HARD_CAP_OVERSHOOT = 10000;

    private DbInterface $db;
    private Setting $settings;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    /**
     * Pseudo-Cron-Einstieg. Laeuft nur, wenn der letzte Run >= 60 s her ist.
     *
     * @return array{ran: bool, deleted: array<string, int>}
     */
    public function maybeRun(int $minIntervalSeconds = 60): array
    {
        $last = (int)strtotime($this->settings->get('retention_last_run', '') ?: '1970-01-01');
        if ($last > 0 && (time() - $last) < $minIntervalSeconds) {
            return ['ran' => false, 'deleted' => []];
        }

        // Lock-of-sorts: Timestamp setzen BEVOR wir arbeiten, damit parallele
        // Requests in dieser Minute nicht alle aufraeumen. 10-s Race bleibt.
        $this->settings->set('retention_last_run', date('Y-m-d H:i:s'), 'retention');

        return [
            'ran'     => true,
            'deleted' => $this->runAll(),
        ];
    }

    /**
     * Fuehrt alle Cleanups aus (ohne Gate). Fuer manuelle Aufrufe / Crons.
     *
     * @return array<string, int> deleted-Count pro Tabelle
     */
    public function runAll(): array
    {
        $out = [
            'rate_limits' => $this->cleanRateLimits(),
            'spam_log'    => $this->cleanSpamLog(),
            'ip_entries'  => $this->cleanExpiredIps(),
        ];
        $this->settings->set('retention_last_run_stats', json_encode($out), 'retention');
        return $out;
    }

    /**
     * Rate-Limit-Buckets aelter als 1 Stunde entfernen.
     * Fachlich nutzlos nach dem Sliding-Window-Verfahren (max. Fenster ist Minuten).
     */
    public function cleanRateLimits(): int
    {
        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_rate_limits`
             WHERE `window_start` < DATE_SUB(NOW(), INTERVAL 1 HOUR)
             LIMIT " . self::RATE_LIMIT_LIMIT,
            []
        );
        return (int)$this->getAffectedRows();
    }

    /**
     * Spam-Log nach Retention-Tagen + harter Zeilen-Obergrenze bereinigen.
     */
    public function cleanSpamLog(): int
    {
        if (!$this->settings->getBool('auto_cleanup')) {
            return 0;
        }
        $days = $this->settings->getInt('log_retention_days', 30);
        if ($days < 1) {
            $days = 30;
        }

        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_spam_log`
             WHERE `created_at` < DATE_SUB(NOW(), INTERVAL :days DAY)
             LIMIT " . self::SPAM_LOG_LIMIT,
            ['days' => $days]
        );
        $deleted = (int)$this->getAffectedRows();

        // Harte Notbremse: wenn Tabelle trotzdem zu gross, aelteste entfernen.
        $maxRows = $this->settings->getInt('spam_log_max_rows', 100000);
        if ($maxRows > 0) {
            $row = $this->db->queryPrepared(
                "SELECT COUNT(*) AS c FROM `bbf_captcha_spam_log`",
                [],
                1
            );
            $current = (int)($row->c ?? 0);
            if ($current > $maxRows) {
                $toDelete = min($current - $maxRows, self::SPAM_LOG_HARD_CAP_OVERSHOOT);
                $this->db->queryPrepared(
                    "DELETE FROM `bbf_captcha_spam_log`
                     ORDER BY `created_at` ASC
                     LIMIT " . (int)$toDelete,
                    []
                );
                $deleted += (int)$this->getAffectedRows();
            }
        }

        return $deleted;
    }

    /**
     * Abgelaufene Blacklist-Eintraege physisch entfernen.
     */
    public function cleanExpiredIps(): int
    {
        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_ip_entries`
             WHERE `expires_at` IS NOT NULL AND `expires_at` < NOW()
             LIMIT " . self::IP_EXPIRED_LIMIT,
            []
        );
        return (int)$this->getAffectedRows();
    }

    /**
     * Aktuelle Tabellengroessen (fuer Dashboard-Widget).
     *
     * @return array<string, array{rows: int, size_bytes: int}>
     */
    public function getTableSizes(): array
    {
        $rows = $this->db->queryPrepared(
            "SELECT `table_name` AS t, `table_rows` AS r, (`data_length` + `index_length`) AS sz
             FROM information_schema.TABLES
             WHERE `table_schema` = DATABASE()
               AND `table_name` LIKE 'bbf_captcha_%'",
            [],
            2
        );
        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $out[(string)$r->t] = [
                    'rows'       => (int)$r->r,
                    'size_bytes' => (int)$r->sz,
                ];
            }
        }
        return $out;
    }

    public function getLastRun(): ?string
    {
        $v = $this->settings->get('retention_last_run');
        return $v !== '' ? $v : null;
    }

    /**
     * @return array<string, int>
     */
    public function getLastRunStats(): array
    {
        $raw = $this->settings->get('retention_last_run_stats');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getAffectedRows(): int
    {
        try {
            return (int)$this->db->getAffectedRows();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
