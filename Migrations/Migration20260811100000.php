<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration 5 (CAP-17): Nachträgliche Zustellung blockierter Nachrichten.
 *
 * Ein Fehlalarm kostet heute die Anfrage eines echten Kunden – sie wird
 * abgewiesen und ist weg. Mit `delivered_at` lässt sich im Spam-Log festhalten,
 * dass eine blockierte Einreichung nachträglich zugestellt wurde, damit sie
 * nicht versehentlich mehrfach rausgeht.
 *
 * Spalte wird nur angelegt, wenn sie fehlt (idempotent, MySQL kennt kein
 * ADD COLUMN IF NOT EXISTS). Nullable ohne Default – kein Datenrisiko.
 */
class Migration20260811100000 extends Migration implements IMigration
{
    public function up(): void
    {
        if (!$this->columnExists('bbf_captcha_spam_log', 'delivered_at')) {
            $this->execute(
                "ALTER TABLE `bbf_captcha_spam_log` ADD COLUMN `delivered_at` DATETIME NULL DEFAULT NULL"
            );
        }
        $this->execute(
            "INSERT IGNORE INTO `bbf_captcha_settings` (`setting_key`, `setting_value`, `setting_group`) " .
            "VALUES ('delivery_recipient', '', 'general')"
        );
    }

    public function down(): void
    {
        // Spalte bewusst NICHT entfernen – ein Downgrade darf keine Nachweise
        // über bereits erfolgte Zustellungen vernichten.
        $this->execute(
            "DELETE FROM `bbf_captcha_settings` WHERE `setting_key` = 'delivery_recipient'"
        );
    }

    /** Tabellen- und Spaltenname sind Konstanten im Code – keine Fremdeingabe. */
    private function columnExists(string $table, string $column): bool
    {
        try {
            $rows = $this->fetchAll("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'");

            return is_array($rows) && count($rows) > 0;
        } catch (\Throwable) {
            // Im Zweifel nicht anlegen – ein fehlgeschlagenes ALTER wäre schlimmer.
            return true;
        }
    }
}
