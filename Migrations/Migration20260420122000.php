<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Phase 3 Hardening-Audit: Retention-Settings.
 *
 * - spam_log_max_rows         Harte Obergrenze der Spam-Log-Tabelle
 * - retention_last_run        Timestamp des letzten Pseudo-Cron-Runs
 * - retention_last_run_stats  JSON mit deleted-Count pro Tabelle
 */
class Migration20260420122000 extends Migration implements IMigration
{
    public function up(): void
    {
        $defaults = [
            ['spam_log_max_rows',        '100000', 'retention'],
            ['retention_last_run',       '',       'retention'],
            ['retention_last_run_stats', '',       'retention'],
        ];

        foreach ($defaults as [$key, $value, $group]) {
            $this->execute(
                "INSERT IGNORE INTO `bbf_captcha_settings`
                 (`setting_key`, `setting_value`, `setting_group`) VALUES ('" .
                addslashes($key) . "', '" . addslashes($value) . "', '" . addslashes($group) . "')"
            );
        }
    }

    public function down(): void
    {
        foreach (['spam_log_max_rows', 'retention_last_run', 'retention_last_run_stats'] as $k) {
            $this->execute(
                "DELETE FROM `bbf_captcha_settings` WHERE `setting_key` = '" . addslashes($k) . "'"
            );
        }
    }
}
