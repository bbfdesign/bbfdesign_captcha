<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Shop;
use JTL\Update\IMigration;

/**
 * Phase 3 Performance-Follow-up:
 * Composite-Index fuer kombinierte Spam-Log-Filter.
 *
 * Der getSpamLog-Query im Admin kombiniert bis zu 5 Spalten in der
 * WHERE-Klausel: form_type, detection_method, action_taken, ip_address,
 * created_at. Die vorhandenen Einzel-Indizes + die Composites aus
 * Migration20260420121000 decken zwei typische Zugriffs-Muster ab:
 *   (form_type, created_at)      — Dashboard-Trend, Top-Forms
 *   (action_taken, created_at)   — KPI-Queries
 *
 * Fuer den offenen Filter-Fall (alle 5 Spalten auf einmal) hilft ein
 * breiterer Composite-Index. MySQL nutzt Left-Prefix-Matching; die
 * Spaltenreihenfolge spiegelt die Selektivitaet wider:
 *   form_type (hoch) → action_taken (hoch) → detection_method (mittel)
 *   → created_at (fuer Range-Filter am Ende).
 *
 * Idempotent via information_schema.
 */
class Migration20260420123000 extends Migration implements IMigration
{
    public function up(): void
    {
        $this->ensureIndex(
            'bbf_captcha_spam_log',
            'idx_filter_combo',
            '(`form_type`, `action_taken`, `detection_method`, `created_at`)'
        );
    }

    public function down(): void
    {
        // Hardening — kein Rollback.
    }

    private function ensureIndex(string $table, string $indexName, string $columns): void
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
        if ((int)($row->c ?? 0) > 0) {
            return;
        }
        try {
            $this->execute("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` {$columns}");
        } catch (\Throwable $e) {
            // Ignore — parallel angelegt oder nicht moeglich
        }
    }
}
