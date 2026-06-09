<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration 4: DSGVO – optionale IP-Anonymisierung im Spam-Log.
 *
 * Registriert den Schalter `log_ip_anonymize` mit Default '0' (Opt-in, aus).
 * Bei Aktivierung kürzt das Plugin gespeicherte IPs auf /24 (IPv6 /48); der
 * Auto-Block-Zähler arbeitet dann auf derselben Granularität.
 */
class Migration20260609100000 extends Migration implements IMigration
{
    public function up(): void
    {
        $this->execute(
            "INSERT IGNORE INTO `bbf_captcha_settings` (`setting_key`, `setting_value`, `setting_group`) " .
            "VALUES ('log_ip_anonymize', '0', 'privacy')"
        );
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM `bbf_captcha_settings` WHERE `setting_key` = 'log_ip_anonymize'"
        );
    }
}
