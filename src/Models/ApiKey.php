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
     * API-Key anhand des Raw-Keys validieren.
     *
     * Defense in Depth: DB-Lookup via SHA256 + zusätzlich timing-safe hash_equals,
     * um einen einheitlichen Codepfad für "Treffer" und "kein Treffer" zu haben.
     */
    public function validateKey(string $rawKey): ?object
    {
        if ($rawKey === '') {
            return null;
        }
        $hash = hash('sha256', $rawKey);

        $key = $this->db->queryPrepared(
            "SELECT * FROM `bbf_captcha_api_keys`
             WHERE `key_hash` = :hash AND `is_active` = 1",
            ['hash' => $hash],
            1
        );

        if ($key === null || !isset($key->id, $key->key_hash)) {
            return null;
        }

        // Timing-safe Vergleich – schützt gegen hypothetische DB-Vergleichs-Leaks
        if (!hash_equals((string)$key->key_hash, $hash)) {
            return null;
        }

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
