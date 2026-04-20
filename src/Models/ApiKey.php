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

    /** Mindestabstand zwischen zwei last_used_at-Updates (Sekunden) */
    private const LAST_USED_THROTTLE_SECONDS = 60;

    /** Untere/obere Latency-Grenze für den Miss-Pfad (Mikrosekunden) */
    private const MISS_JITTER_MIN_US = 50_000;
    private const MISS_JITTER_MAX_US = 150_000;

    /**
     * API-Key anhand des Raw-Keys validieren.
     *
     * Defense in Depth:
     *  - DB-Lookup via SHA256 (indexed, bcrypt-free path)
     *  - timing-safe hash_equals gegen das zurueckgelieferte Hash
     *  - bei Miss / leerem Key: künstlicher Jitter 50–150ms, um Timing-Attacken
     *    auf "existiert der Key?" zu verhindern
     *  - last_used_at wird nicht bei jedem Request geschrieben (Write-Storm-Schutz);
     *    stattdessen erst, wenn der letzte Update >= 60s her ist
     */
    public function validateKey(string $rawKey): ?object
    {
        if ($rawKey === '') {
            $this->timingJitter();
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
            $this->timingJitter();
            return null;
        }

        // Timing-safe Vergleich – schützt gegen hypothetische DB-Vergleichs-Leaks
        if (!hash_equals((string)$key->key_hash, $hash)) {
            $this->timingJitter();
            return null;
        }

        $this->maybeUpdateLastUsed($key);

        return $key;
    }

    /**
     * last_used_at-Update mit Throttle.
     * Vermeidet, dass jeder API-Request einen DB-Write auslöst.
     */
    private function maybeUpdateLastUsed(object $key): void
    {
        $last = isset($key->last_used_at) && $key->last_used_at !== null
            ? (int)strtotime((string)$key->last_used_at)
            : 0;

        if ($last > 0 && (time() - $last) < self::LAST_USED_THROTTLE_SECONDS) {
            return;
        }

        $this->db->queryPrepared(
            "UPDATE `bbf_captcha_api_keys` SET `last_used_at` = NOW() WHERE `id` = :id",
            ['id' => $key->id]
        );
    }

    /**
     * Konstanter-ish Delay, um die Response-Zeit von Miss und Hit anzugleichen.
     * Nutzt random_int + usleep — kein kryptographisch exakter Equal-Time-Pfad,
     * aber gut genug, um die Differenz zwischen "Key existiert" und "Key existiert nicht"
     * in Rauschen zu verstecken.
     */
    private function timingJitter(): void
    {
        try {
            usleep(random_int(self::MISS_JITTER_MIN_US, self::MISS_JITTER_MAX_US));
        } catch (\Throwable $e) {
            usleep(self::MISS_JITTER_MIN_US);
        }
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
