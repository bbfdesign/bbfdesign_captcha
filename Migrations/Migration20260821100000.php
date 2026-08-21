<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration 7: Opt-in für Cockpit-Review-Vorschauen.
 *
 * Default bleibt AUS. Nur bei expliziter Aktivierung sendet die Telemetrie
 * kurze, redigierte Snippets für Spam-/Quarantäne-Reviews ans CaptchaCockpit.
 */
class Migration20260821100000 extends Migration implements IMigration
{
    public function up(): void
    {
        $this->execute(
            "INSERT IGNORE INTO `bbf_captcha_settings` (`setting_key`, `setting_value`, `setting_group`) " .
            "VALUES ('cockpit_review_enabled', '0', 'cockpit')"
        );
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM `bbf_captcha_settings` WHERE `setting_key` = 'cockpit_review_enabled'"
        );
    }
}
