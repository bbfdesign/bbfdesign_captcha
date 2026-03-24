<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Initiale Migration: Alle Tabellen für BBF Captcha & Spam-Schutz Plugin
 */
class Migration20260324100000 extends Migration implements IMigration
{
    public function up(): void
    {
        // ── Plugin-Einstellungen ──
        $this->execute("
            CREATE TABLE IF NOT EXISTS `bbf_captcha_settings` (
                `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                `setting_value` TEXT DEFAULT NULL,
                `setting_group` VARCHAR(50) DEFAULT 'general',
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Formular-Konfiguration ──
        $this->execute("
            CREATE TABLE IF NOT EXISTS `bbf_captcha_form_config` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `form_type` VARCHAR(50) NOT NULL,
                `form_identifier` VARCHAR(100) DEFAULT NULL,
                `methods` JSON NOT NULL,
                `score_threshold` INT DEFAULT 60,
                `action_on_spam` ENUM('block','log','both') DEFAULT 'both',
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_form` (`form_type`, `form_identifier`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Spam-Log ──
        $this->execute("
            CREATE TABLE IF NOT EXISTS `bbf_captcha_spam_log` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL,
                `form_type` VARCHAR(50) NOT NULL,
                `detection_method` VARCHAR(30) NOT NULL,
                `spam_score` INT DEFAULT 0,
                `action_taken` ENUM('blocked','logged','allowed') DEFAULT 'blocked',
                `user_agent` VARCHAR(500) DEFAULT NULL,
                `request_data` TEXT DEFAULT NULL,
                `is_false_positive` TINYINT(1) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_created` (`created_at`),
                INDEX `idx_ip` (`ip_address`),
                INDEX `idx_form` (`form_type`),
                INDEX `idx_method` (`detection_method`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── IP-Verwaltung ──
        $this->execute("
            CREATE TABLE IF NOT EXISTS `bbf_captcha_ip_entries` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL,
                `ip_range` VARCHAR(18) DEFAULT NULL,
                `entry_type` ENUM('blacklist','whitelist') NOT NULL,
                `reason` VARCHAR(255) DEFAULT NULL,
                `auto_added` TINYINT(1) DEFAULT 0,
                `expires_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_ip` (`ip_address`),
                INDEX `idx_type` (`entry_type`),
                INDEX `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Rate Limiting ──
        $this->execute("
            CREATE TABLE IF NOT EXISTS `bbf_captcha_rate_limits` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL,
                `form_type` VARCHAR(50) NOT NULL,
                `window_start` TIMESTAMP NOT NULL,
                `request_count` INT DEFAULT 1,
                INDEX `idx_lookup` (`ip_address`, `form_type`, `window_start`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── API-Keys ──
        $this->execute("
            CREATE TABLE IF NOT EXISTS `bbf_captcha_api_keys` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `key_name` VARCHAR(100) NOT NULL,
                `key_hash` VARCHAR(64) NOT NULL,
                `permissions` JSON DEFAULT NULL,
                `rate_limit` INT DEFAULT 60,
                `is_active` TINYINT(1) DEFAULT 1,
                `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_hash` (`key_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── KI-Filter: Spam-Wörter ──
        $this->execute("
            CREATE TABLE IF NOT EXISTS `bbf_captcha_spam_words` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `word` VARCHAR(100) NOT NULL,
                `category` ENUM('spam','ham') DEFAULT 'spam',
                `weight` INT DEFAULT 25,
                `auto_learned` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_word` (`word`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Disposable Email Domains ──
        $this->execute("
            CREATE TABLE IF NOT EXISTS `bbf_captcha_disposable_domains` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `domain` VARCHAR(255) NOT NULL,
                UNIQUE KEY `uk_domain` (`domain`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Default-Einstellungen einfügen ──
        $defaults = [
            ['global_enabled', '1', 'general'],
            ['default_action', 'both', 'general'],
            ['log_retention_days', '90', 'general'],
            ['auto_cleanup', '1', 'general'],
            ['debug_mode', '0', 'general'],
            ['email_alert_enabled', '0', 'general'],
            ['email_alert_threshold', '50', 'general'],
            ['email_alert_window', '60', 'general'],
            ['email_alert_address', '', 'general'],
            // Honeypot
            ['honeypot_enabled', '1', 'honeypot'],
            ['honeypot_field_count', '3', 'honeypot'],
            ['honeypot_inject_all_forms', '1', 'honeypot'],
            // Timing
            ['timing_enabled', '1', 'timing'],
            ['timing_min_seconds', '3', 'timing'],
            ['timing_max_seconds', '3600', 'timing'],
            // ALTCHA
            ['altcha_enabled', '1', 'altcha'],
            ['altcha_maxnumber', '100000', 'altcha'],
            ['altcha_hmac_key', '', 'altcha'],
            ['altcha_hmac_rotated_at', '', 'altcha'],
            // Turnstile
            ['turnstile_enabled', '0', 'turnstile'],
            ['turnstile_site_key', '', 'turnstile'],
            ['turnstile_secret_key', '', 'turnstile'],
            ['turnstile_mode', 'managed', 'turnstile'],
            // reCAPTCHA
            ['recaptcha_enabled', '0', 'recaptcha'],
            ['recaptcha_version', 'v3', 'recaptcha'],
            ['recaptcha_site_key', '', 'recaptcha'],
            ['recaptcha_secret_key', '', 'recaptcha'],
            ['recaptcha_score_threshold', '0.5', 'recaptcha'],
            // Friendly Captcha
            ['friendly_captcha_enabled', '0', 'friendly_captcha'],
            ['friendly_captcha_site_key', '', 'friendly_captcha'],
            ['friendly_captcha_api_key', '', 'friendly_captcha'],
            // hCaptcha
            ['hcaptcha_enabled', '0', 'hcaptcha'],
            ['hcaptcha_site_key', '', 'hcaptcha'],
            ['hcaptcha_secret_key', '', 'hcaptcha'],
            // KI-Filter
            ['ai_filter_enabled', '1', 'ai_filter'],
            ['ai_threshold_ok', '30', 'ai_filter'],
            ['ai_threshold_suspicious', '60', 'ai_filter'],
            ['ai_threshold_spam', '100', 'ai_filter'],
            ['ai_check_language', '1', 'ai_filter'],
            ['ai_check_disposable_email', '1', 'ai_filter'],
            // IP-Schutz
            ['ip_protection_enabled', '1', 'ip'],
            ['ip_auto_block_enabled', '1', 'ip'],
            ['ip_auto_block_attempts', '5', 'ip'],
            ['ip_auto_block_window', '10', 'ip'],
            ['ip_auto_block_duration', '1440', 'ip'],
            // Rate Limiting
            ['rate_limit_enabled', '1', 'rate_limit'],
            ['rate_limit_max_requests', '10', 'rate_limit'],
            ['rate_limit_window_minutes', '5', 'rate_limit'],
            // Bot Detection
            ['bot_detection_enabled', '1', 'bot_detection'],
            ['bot_js_challenge', '1', 'bot_detection'],
            // Custom CSS
            ['custom_css', '', 'css'],
        ];

        foreach ($defaults as [$key, $value, $group]) {
            $this->execute(
                "INSERT IGNORE INTO `bbf_captcha_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES ('" .
                addslashes($key) . "', '" . addslashes($value) . "', '" . addslashes($group) . "')"
            );
        }

        // ── Default Formular-Konfigurationen ──
        $forms = [
            ['contact', '["honeypot","timing","altcha","ai_filter"]', 60, 'both'],
            ['registration', '["honeypot","timing","altcha","ai_filter"]', 60, 'both'],
            ['newsletter', '["honeypot","timing"]', 50, 'both'],
            ['review', '["honeypot","timing","altcha","ai_filter"]', 60, 'both'],
            ['checkout', '["honeypot","timing"]', 80, 'log'],
            ['password_reset', '["honeypot","timing"]', 50, 'both'],
            ['wishlist', '["honeypot"]', 50, 'log'],
            ['login', '["honeypot","timing"]', 50, 'both'],
        ];

        foreach ($forms as [$type, $methods, $threshold, $action]) {
            $this->execute(
                "INSERT IGNORE INTO `bbf_captcha_form_config` (`form_type`, `methods`, `score_threshold`, `action_on_spam`) " .
                "VALUES ('" . addslashes($type) . "', '" . addslashes($methods) . "', " . $threshold . ", '" . $action . "')"
            );
        }

        // ── Default Spam-Wörter ──
        $spamWords = [
            ['viagra', 30], ['cialis', 30], ['casino', 25], ['poker', 20],
            ['lottery', 25], ['jackpot', 25], ['bitcoin', 15], ['crypto', 15],
            ['free money', 30], ['click here', 20], ['buy now', 15],
            ['limited offer', 20], ['act now', 20], ['congratulations', 15],
            ['winner', 15], ['earn money', 25], ['work from home', 20],
            ['make money online', 25], ['SEO', 10], ['backlink', 15],
            ['cheap', 10], ['discount', 10], ['order now', 15],
        ];

        foreach ($spamWords as [$word, $weight]) {
            $this->execute(
                "INSERT IGNORE INTO `bbf_captcha_spam_words` (`word`, `category`, `weight`) " .
                "VALUES ('" . addslashes($word) . "', 'spam', " . $weight . ")"
            );
        }
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `bbf_captcha_disposable_domains`");
        $this->execute("DROP TABLE IF EXISTS `bbf_captcha_spam_words`");
        $this->execute("DROP TABLE IF EXISTS `bbf_captcha_api_keys`");
        $this->execute("DROP TABLE IF EXISTS `bbf_captcha_rate_limits`");
        $this->execute("DROP TABLE IF EXISTS `bbf_captcha_ip_entries`");
        $this->execute("DROP TABLE IF EXISTS `bbf_captcha_spam_log`");
        $this->execute("DROP TABLE IF EXISTS `bbf_captcha_form_config`");
        $this->execute("DROP TABLE IF EXISTS `bbf_captcha_settings`");
    }
}
