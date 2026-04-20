<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Shop;
use JTL\Update\IMigration;

/**
 * Phase 2 Hardening-Audit: Hot-Path-Indizes auf Content-Tabellen.
 *
 * KRITISCH: `bbf_captcha_rate_limits` hatte nur einen normalen INDEX auf
 * (ip_address, form_type, window_start) statt UNIQUE. Der Code nutzt
 * `INSERT ... ON DUPLICATE KEY UPDATE`, erkennt den Konflikt aber nie —
 * jeder Request hat eine neue Zeile erzeugt. Bugfix via UNIQUE KEY.
 *
 * Weitere Verbesserungen (Composite-Indizes fuer Dashboard/Retention):
 * - spam_log: (form_type, created_at), (action_taken, created_at)
 * - ip_entries: (entry_type, ip_address) fuer den Blacklist-Check
 *
 * Idempotent via information_schema-Checks.
 */
class Migration20260420121000 extends Migration implements IMigration
{
    public function up(): void
    {
        // ── bbf_captcha_rate_limits: UNIQUE KEY fuer funktionierenden Upsert ──
        $this->ensureUniqueKey(
            'bbf_captcha_rate_limits',
            'uq_rate_bucket',
            '(`ip_address`, `form_type`, `window_start`)'
        );

        // Alter redundanter Index idx_lookup kann entfernt werden, sobald
        // die neue UNIQUE existiert (selbe Spaltenkombi). Defensive: drop ignored if missing.
        $this->dropIndexIfExists('bbf_captcha_rate_limits', 'idx_lookup');

        // ── bbf_captcha_spam_log: Composite-Indizes fuer Dashboard-Queries ──
        $this->ensureIndex(
            'bbf_captcha_spam_log',
            'idx_form_created',
            '(`form_type`, `created_at`)'
        );
        $this->ensureIndex(
            'bbf_captcha_spam_log',
            'idx_action_created',
            '(`action_taken`, `created_at`)'
        );

        // ── bbf_captcha_ip_entries: Composite fuer Blacklist-Lookup ──
        // WHERE entry_type = 'blacklist' AND ip_address = :ip
        $this->ensureIndex(
            'bbf_captcha_ip_entries',
            'idx_type_ip',
            '(`entry_type`, `ip_address`)'
        );
    }

    public function down(): void
    {
        // Hardening — kein Rollback.
    }

    private function ensureUniqueKey(string $table, string $indexName, string $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }
        try {
            $this->execute("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$indexName}` {$columns}");
        } catch (\Throwable $e) {
            // Duplikate in Spalten? Bei rate_limits waere das die Summe der Request-Counts
            // pro (ip, form, window) — dedupe, dann erneut versuchen.
            if ($table === 'bbf_captcha_rate_limits') {
                $this->dedupeRateLimits();
                try {
                    $this->execute("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$indexName}` {$columns}");
                } catch (\Throwable $e2) {
                    // still — nicht fatal, naechster Deploy versucht es wieder
                }
            }
        }
    }

    private function ensureIndex(string $table, string $indexName, string $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }
        try {
            $this->execute("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` {$columns}");
        } catch (\Throwable $e) {
            // Ignore — z.B. wenn parallel angelegt
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }
        try {
            $this->execute("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $db  = Shop::Container()->getDB();
        $row = $db->queryPrepared(
            "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name   = :t
               AND index_name   = :i",
            ['t' => $table, 'i' => $indexName],
            1
        );
        return (int)($row->c ?? 0) > 0;
    }

    /**
     * Aggregiert Duplikate in rate_limits bevor UNIQUE angelegt wird.
     * Summiert request_count pro (ip, form, window) und erhaelt id_max.
     */
    private function dedupeRateLimits(): void
    {
        $this->execute("
            CREATE TEMPORARY TABLE `_rl_dedupe` AS
            SELECT MAX(`id`) AS keep_id,
                   SUM(`request_count`) AS total,
                   `ip_address`, `form_type`, `window_start`
              FROM `bbf_captcha_rate_limits`
             GROUP BY `ip_address`, `form_type`, `window_start`
            HAVING COUNT(*) > 1
        ");
        $this->execute("
            UPDATE `bbf_captcha_rate_limits` rl
              INNER JOIN `_rl_dedupe` d ON d.keep_id = rl.id
               SET rl.`request_count` = d.total
        ");
        $this->execute("
            DELETE rl FROM `bbf_captcha_rate_limits` rl
              INNER JOIN `_rl_dedupe` d
                 ON rl.`ip_address`   = d.`ip_address`
                AND rl.`form_type`    = d.`form_type`
                AND rl.`window_start` = d.`window_start`
                AND rl.`id` <> d.keep_id
        ");
        $this->execute("DROP TEMPORARY TABLE IF EXISTS `_rl_dedupe`");
    }
}
