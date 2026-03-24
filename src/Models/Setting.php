<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Models;

use JTL\DB\DbInterface;

class Setting
{
    private DbInterface $db;
    private array $cache = [];
    private bool $loaded = false;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Alle Einstellungen laden (cached)
     */
    public function getAll(): array
    {
        if (!$this->loaded) {
            $this->loadAll();
        }
        return $this->cache;
    }

    /**
     * Einzelne Einstellung lesen
     */
    public function get(string $key, string $default = ''): string
    {
        if (!$this->loaded) {
            $this->loadAll();
        }
        return $this->cache[$key] ?? $default;
    }

    /**
     * Boolean-Einstellung lesen
     */
    public function getBool(string $key): bool
    {
        return $this->get($key, '0') === '1';
    }

    /**
     * Integer-Einstellung lesen
     */
    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->get($key);
        return $val !== '' ? (int)$val : $default;
    }

    /**
     * Float-Einstellung lesen
     */
    public function getFloat(string $key, float $default = 0.0): float
    {
        $val = $this->get($key);
        return $val !== '' ? (float)$val : $default;
    }

    /**
     * Einstellung speichern
     */
    public function set(string $key, string $value, string $group = 'general'): void
    {
        $this->db->queryPrepared(
            "INSERT INTO `bbf_captcha_settings` (`setting_key`, `setting_value`, `setting_group`)
             VALUES (:key, :val, :grp)
             ON DUPLICATE KEY UPDATE `setting_value` = :val2, `setting_group` = :grp2",
            [
                'key'  => $key,
                'val'  => $value,
                'grp'  => $group,
                'val2' => $value,
                'grp2' => $group,
            ]
        );
        $this->cache[$key] = $value;
    }

    /**
     * Mehrere Einstellungen auf einmal speichern
     */
    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, (string)$value);
        }
    }

    /**
     * Cache invalidieren
     */
    public function invalidateCache(): void
    {
        $this->cache  = [];
        $this->loaded = false;
    }

    /**
     * Alle Einstellungen aus DB laden
     */
    private function loadAll(): void
    {
        $rows = $this->db->queryPrepared(
            "SELECT `setting_key`, `setting_value` FROM `bbf_captcha_settings`",
            [],
            2 // RETURN_TYPE_ARRAY
        );

        $this->cache = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $this->cache[$row->setting_key] = $row->setting_value ?? '';
            }
        }
        $this->loaded = true;
    }
}
