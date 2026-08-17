<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration 6: Login/Passwort-Reset bleiben fail-open.
 *
 * Ältere Seeds konnten fehlende Timing-Tokens bei Login und Passwort-Reset hart
 * blockieren. Wir ändern nur unveränderte Alt-Defaults; bewusst konfigurierte
 * Installationen bleiben unangetastet.
 */
class Migration20260817100000 extends Migration implements IMigration
{
    public function up(): void
    {
        foreach (['login', 'password_reset'] as $type) {
            $this->execute(
                "UPDATE `bbf_captcha_form_config` " .
                "SET `score_threshold` = 80, `action_on_spam` = 'log' " .
                "WHERE `form_type` = '" . $type . "' " .
                "AND `methods` = '[\"honeypot\",\"timing\"]' " .
                "AND `score_threshold` = 50 " .
                "AND `action_on_spam` = 'both'"
            );
        }
    }

    public function down(): void
    {
        // Kein automatischer Downgrade: Fail-open ist die sichere Produktionshaltung.
    }
}
