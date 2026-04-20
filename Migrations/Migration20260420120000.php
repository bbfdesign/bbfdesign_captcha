<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Shop;
use JTL\Update\IMigration;

/**
 * Phase 1 Hardening-Audit: Settings-Integritaet verifizieren.
 *
 * - Verifiziert, dass `bbf_captcha_settings` eine PRIMARY KEY oder UNIQUE
 *   auf `setting_key` hat (acts as uq_setting equivalent, VARCHAR(100) NOT NULL).
 * - Bereinigt vorhandene Duplikate (id <): hierfuer ist eine id-Spalte noetig.
 *   Da `bbf_captcha_settings` aktuell KEINE id-Spalte hat (setting_key IST PK),
 *   ist eine Duplikat-Bereinigung per id nicht noetig — Duplikate sind durch die
 *   PK ausgeschlossen. Der Migrationsschritt ist dann No-Op.
 * - Legt eine UNIQUE KEY `uq_setting` an, falls weder PK noch UNIQUE auf
 *   `setting_key` existiert (Fallback fuer Installationen ohne PK).
 *
 * Idempotent: jede Pruefung per information_schema; bereits-existierende
 * Constraints werden still uebersprungen.
 */
class Migration20260420120000 extends Migration implements IMigration
{
    private const TABLE = 'bbf_captcha_settings';

    public function up(): void
    {
        $db    = Shop::Container()->getDB();
        $table = self::TABLE;

        // 1) Gibt es ueberhaupt eine unique-property (PK oder UNIQUE) auf setting_key?
        $idx = $db->queryPrepared(
            "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name   = :t
               AND column_name  = 'setting_key'
               AND non_unique   = 0",
            ['t' => $table],
            1
        );
        $hasUnique = (int)($idx->c ?? 0) > 0;

        if (!$hasUnique) {
            // Defensive Duplikat-Bereinigung: falls keine id-Spalte da ist,
            // per temporaerer Gruppierung
            $hasId = $db->queryPrepared(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                 WHERE table_schema = DATABASE()
                   AND table_name   = :t
                   AND column_name  = 'id'",
                ['t' => $table],
                1
            );

            if ((int)($hasId->c ?? 0) > 0) {
                $this->execute("
                    DELETE t1 FROM `{$table}` t1
                    INNER JOIN `{$table}` t2
                        ON t1.setting_key = t2.setting_key
                       AND t1.id < t2.id
                ");
            } else {
                // Ohne id-Spalte: neue Tabelle bauen, DISTINCT uebernehmen
                $this->execute("CREATE TABLE IF NOT EXISTS `{$table}_dedupe` LIKE `{$table}`");
                $this->execute("
                    INSERT INTO `{$table}_dedupe`
                    SELECT * FROM `{$table}` GROUP BY `setting_key`
                ");
                $this->execute("DROP TABLE `{$table}`");
                $this->execute("RENAME TABLE `{$table}_dedupe` TO `{$table}`");
            }

            // UNIQUE KEY anlegen (Fallback-Index, neben ggf. bereits vorhandener PK)
            try {
                $this->execute("ALTER TABLE `{$table}` ADD UNIQUE KEY `uq_setting` (`setting_key`)");
            } catch (\Throwable $e) {
                // Index existierte schon
            }
        }

        // 2) setting_key NOT NULL sicherstellen
        try {
            $this->execute("ALTER TABLE `{$table}` MODIFY `setting_key` VARCHAR(100) NOT NULL");
        } catch (\Throwable $e) {
            // Spaltentyp passt bereits
        }
    }

    public function down(): void
    {
        // Reines Hardening — kein Rollback.
    }
}
