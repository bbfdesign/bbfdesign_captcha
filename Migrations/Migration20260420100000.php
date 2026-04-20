<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

/**
 * Migration 3: LLM-Zweitpruefung fuer Smart-Spamfilter.
 *
 * Fuegt Default-Settings fuer den optionalen LLM-Aufruf hinzu.
 * Provider: none (Default), ollama, openai, claude, gemini.
 */
class Migration20260420100000 extends Migration implements IMigration
{
    public function up(): void
    {
        $defaults = [
            ['llm_enabled',          '0',     'llm'],
            ['llm_provider',         'none',  'llm'],
            ['llm_api_key',          '',      'llm'],
            ['llm_model',            '',      'llm'],
            ['llm_endpoint',         'http://localhost:11434', 'llm'],
            ['llm_only_borderline',  '1',     'llm'],
            ['llm_timeout',          '8',     'llm'],
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
        $keys = [
            'llm_enabled', 'llm_provider', 'llm_api_key', 'llm_model',
            'llm_endpoint', 'llm_only_borderline', 'llm_timeout',
        ];
        foreach ($keys as $key) {
            $this->execute(
                "DELETE FROM `bbf_captcha_settings` WHERE `setting_key` = '" . addslashes($key) . "'"
            );
        }
    }
}
