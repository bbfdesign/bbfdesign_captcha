<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration 2: Disposable Email Domains befüllen (~500 Domains)
 */
class Migration20260324100001 extends Migration implements IMigration
{
    public function up(): void
    {
        // Domains aus PHP-Datei laden
        $filePath = dirname(__DIR__) . '/src/Data/disposable_domains.php';
        if (!file_exists($filePath)) {
            return;
        }

        $domains = require $filePath;
        if (!is_array($domains)) {
            return;
        }

        // In Batches von 50 einfügen
        $batch = [];
        foreach ($domains as $domain) {
            $domain = trim(strtolower($domain));
            if (empty($domain)) {
                continue;
            }
            $batch[] = "('" . addslashes($domain) . "')";

            if (count($batch) >= 50) {
                $this->execute(
                    "INSERT IGNORE INTO `bbf_captcha_disposable_domains` (`domain`) VALUES " . implode(',', $batch)
                );
                $batch = [];
            }
        }

        // Rest einfügen
        if (!empty($batch)) {
            $this->execute(
                "INSERT IGNORE INTO `bbf_captcha_disposable_domains` (`domain`) VALUES " . implode(',', $batch)
            );
        }
    }

    public function down(): void
    {
        $this->execute("TRUNCATE TABLE `bbf_captcha_disposable_domains`");
    }
}
