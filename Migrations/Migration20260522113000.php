<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration 5: DB-Constraints fuer API-Key- und Rate-Limit-Integritaet.
 */
class Migration20260522113000 extends Migration implements IMigration
{
    public function up(): void
    {
        $this->execute("
            DELETE duplicate_key
            FROM `bbf_captcha_api_keys` duplicate_key
            INNER JOIN `bbf_captcha_api_keys` keep_key
                ON keep_key.`key_hash` = duplicate_key.`key_hash`
                AND keep_key.`id` < duplicate_key.`id`
        ");

        $this->execute("
            UPDATE `bbf_captcha_rate_limits` keep_bucket
            INNER JOIN (
                SELECT MIN(`id`) AS keep_id, SUM(`request_count`) AS total_count
                FROM `bbf_captcha_rate_limits`
                GROUP BY `ip_address`, `form_type`, `window_start`
                HAVING COUNT(*) > 1
            ) duplicate_buckets ON duplicate_buckets.keep_id = keep_bucket.`id`
            SET keep_bucket.`request_count` = duplicate_buckets.total_count
        ");

        $this->execute("
            DELETE duplicate_bucket
            FROM `bbf_captcha_rate_limits` duplicate_bucket
            INNER JOIN `bbf_captcha_rate_limits` keep_bucket
                ON keep_bucket.`ip_address` = duplicate_bucket.`ip_address`
                AND keep_bucket.`form_type` = duplicate_bucket.`form_type`
                AND keep_bucket.`window_start` = duplicate_bucket.`window_start`
                AND keep_bucket.`id` < duplicate_bucket.`id`
        ");

        $this->execute("
            ALTER TABLE `bbf_captcha_api_keys`
            ADD UNIQUE KEY `uk_bbf_captcha_api_key_hash` (`key_hash`)
        ");

        $this->execute("
            ALTER TABLE `bbf_captcha_rate_limits`
            ADD UNIQUE KEY `uk_bbf_captcha_rate_bucket` (`ip_address`, `form_type`, `window_start`)
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE `bbf_captcha_rate_limits`
            DROP INDEX `uk_bbf_captcha_rate_bucket`
        ");

        $this->execute("
            ALTER TABLE `bbf_captcha_api_keys`
            DROP INDEX `uk_bbf_captcha_api_key_hash`
        ");
    }
}
