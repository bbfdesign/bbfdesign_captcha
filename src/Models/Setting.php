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
     * Mehrere Einstellungen gefiltert laden (Hot-Path-Optimierung).
     *
     * Laedt nur die angeforderten Keys in den Prozess-Cache und vermeidet
     * SELECT * auf die Tabelle. Wiederholte Aufrufe nutzen den Cache.
     * Nicht vorhandene Keys werden mit Default belegt.
     *
     * @param array<int, string> $keys
     * @return array<string, string>
     */
    public function getMany(array $keys, string $default = ''): array
    {
        $out  = [];
        $miss = [];
        foreach ($keys as $k) {
            if (!is_string($k) || $k === '') {
                continue;
            }
            if (array_key_exists($k, $this->cache)) {
                $out[$k] = $this->cache[$k];
            } else {
                $miss[] = $k;
            }
        }
        if ($miss === [] || $this->loaded) {
            foreach ($miss as $k) {
                $out[$k] = $default;
            }
            return $out;
        }

        $placeholders = [];
        $params       = [];
        foreach ($miss as $i => $k) {
            $ph              = ':k' . $i;
            $placeholders[]  = $ph;
            $params[substr($ph, 1)] = $k;
        }
        $rows = $this->db->queryPrepared(
            'SELECT `setting_key`, `setting_value` FROM `bbf_captcha_settings` WHERE `setting_key` IN (' . implode(',', $placeholders) . ')',
            $params,
            2
        );
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $this->cache[$row->setting_key] = $row->setting_value ?? '';
                $out[$row->setting_key]         = $row->setting_value ?? '';
            }
        }
        foreach ($miss as $k) {
            if (!array_key_exists($k, $out)) {
                $out[$k]          = $default;
                $this->cache[$k]  = $default;
            }
        }
        return $out;
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
