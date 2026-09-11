<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration 8: Cockpit-gesteuerte Login/Admin-Firewall.
 *
 * Default ist Monitor, damit echte Kunden- und Admin-Logins nicht versehentlich
 * ausgesperrt werden. Enforce ist ein bewusstes Betreiber-Setting.
 */
class Migration20260911100000 extends Migration implements IMigration
{
    public function up(): void
    {
        $defaults = [
            ['cockpit_auth_firewall_mode', 'monitor', 'cockpit'],
            ['cockpit_auth_firewall_max_attempts', '8', 'cockpit'],
            ['cockpit_auth_firewall_window_seconds', '300', 'cockpit'],
            ['cockpit_auth_firewall_lockout_seconds', '900', 'cockpit'],
        ];

        foreach ($defaults as [$key, $value, $group]) {
            $this->execute(
                "INSERT IGNORE INTO `bbf_captcha_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES ('" .
                addslashes($key) . "', '" . addslashes($value) . "', '" . addslashes($group) . "')"
            );
        }
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM `bbf_captcha_settings`
             WHERE `setting_key` IN (
                'cockpit_auth_firewall_mode',
                'cockpit_auth_firewall_max_attempts',
                'cockpit_auth_firewall_window_seconds',
                'cockpit_auth_firewall_lockout_seconds'
             )"
        );
    }
}
