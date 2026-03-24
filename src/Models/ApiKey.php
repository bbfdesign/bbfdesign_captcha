<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Models;

use JTL\DB\DbInterface;

class ApiKey
{
    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /**
     * API-Key anhand des Raw-Keys validieren
     */
    public function validateKey(string $rawKey): ?object
    {
        $hash = hash('sha256', $rawKey);

        $key = $this->db->queryPrepared(
            "SELECT * FROM `bbf_captcha_api_keys`
             WHERE `key_hash` = :hash AND `is_active` = 1",
            ['hash' => $hash],
            1
        );

        if ($key === null || !isset($key->id)) {
            return null;
        }

        // Letzte Nutzung aktualisieren
        $this->db->queryPrepared(
            "UPDATE `bbf_captcha_api_keys` SET `last_used_at` = NOW() WHERE `id` = :id",
            ['id' => $key->id]
        );

        return $key;
    }

    /**
     * Prüfe ob der Key eine bestimmte Berechtigung hat
     */
    public function hasPermission(object $key, string $permission): bool
    {
        $permissions = json_decode($key->permissions ?? '[]', true);
        return is_array($permissions) && in_array($permission, $permissions, true);
    }
}
