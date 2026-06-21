<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Zieht das zentrale, signierte Regelwerk (Ruleset) vom CaptchaCockpit und cached
 * es lokal. Ein Interpreter (siehe AISpamService/CaptchaService) wendet die rein
 * DEKLARATIVEN Felder an – niemals Code. Damit wirken neue zentrale Erkenntnisse
 * (Schwellen, Token-Heuristik, Domain-/Phrasen-Listen) OHNE Plugin-Update.
 *
 * Grundsätze:
 * - Default AUS: greift nur bei cockpit_enabled + Endpoint + Secret.
 * - fail-safe: ist das Cockpit nicht erreichbar oder die Signatur ungültig, bleibt
 *   das zuletzt gültige Ruleset (bzw. die eingebauten Defaults) aktiv. Ein Fehler
 *   schwächt den Schutz nie.
 * - Integrität: das Ruleset wird per HMAC (X-Ruleset-Signature über den Rohbody,
 *   Shop-Secret) verifiziert, bevor es gecacht/angewandt wird.
 *
 * Vertrag: ~/captchacockpit/docs/API-CONTRACT.md (GET /api/v1/ruleset).
 */
class RemoteRulesetService
{
    private const PULL_INTERVAL = 3600; // s – höchstens stündlich ziehen
    private const HTTP_TIMEOUT  = 8;

    private DbInterface $db;
    private Setting $settings;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    public function isEnabled(): bool
    {
        return $this->settings->getBool('cockpit_enabled')
            && $this->settings->get('cockpit_endpoint') !== ''
            && $this->settings->get('cockpit_secret') !== '';
    }

    /** Gedrosselter Aufruf aus dem Boot-/Cron-Pfad. */
    public function pullIfDue(): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $last = $this->settings->getInt('cockpit_ruleset_last_pull', 0);
        if ($last > 0 && (time() - $last) < self::PULL_INTERVAL) {
            return;
        }
        $this->settings->set('cockpit_ruleset_last_pull', (string)time(), 'cockpit');
        try {
            $this->pull();
        } catch (\Throwable $e) {
            $this->logDebug('pull: ' . $e->getMessage());
        }
        try {
            $this->pullBlocklist();
        } catch (\Throwable $e) {
            $this->logDebug('pullBlocklist: ' . $e->getMessage());
        }
    }

    /**
     * Zieht die zentrale IP-/Domain-Blocklist (URL aus Ruleset `ipBlocklistUrl`,
     * sonst `/api/v1/blocklist`), verifiziert die Signatur (Header
     * X-Ruleset-Signature über den Rohbody) und cached sie. Vollsnapshot (since=0),
     * damit kein Delta-Merge nötig ist. Fail-safe.
     */
    public function pullBlocklist(): bool
    {
        $endpoint = rtrim($this->settings->get('cockpit_endpoint'), '/');
        $secret   = $this->settings->get('cockpit_secret');
        if ($endpoint === '' || $secret === '') {
            return false;
        }

        $rs   = self::cached($this->settings);
        $path = (isset($rs['ipBlocklistUrl']) && is_string($rs['ipBlocklistUrl']) && $rs['ipBlocklistUrl'] !== '')
            ? $rs['ipBlocklistUrl'] : '/api/v1/blocklist';
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $url  = $endpoint . $path . (str_contains($path, '?') ? '&' : '?') . 'since=0';

        $body = $this->fetchSigned($url, $secret);
        if ($body === null) {
            return false;
        }
        $bl = json_decode($body, true);
        if (!is_array($bl)) {
            $this->logDebug('blocklist JSON ungültig – verworfen.');
            return false;
        }
        $this->settings->set('cockpit_blocklist_cache', $body, 'cockpit');
        return true;
    }

    /**
     * Signierter GET (HMAC-Auth) mit Integritätsprüfung der Antwort
     * (X-Ruleset-Signature über den Rohbody). Liefert den verifizierten Body oder null.
     */
    private function fetchSigned(string $url, string $secret): ?string
    {
        $signedAt = (string)time();
        $sig      = hash_hmac('sha256', '|' . $signedAt, $secret);
        $instanceId = '';
        try {
            $instanceId = (new LicenseService($this->db, $this->settings))->instanceId();
        } catch (\Throwable) {
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        $rawSig = '';
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-Cockpit-Instance: ' . $instanceId,
                'X-Signed-At: ' . $signedAt,
                'X-Signature: ' . $sig,
                'User-Agent: bbfdesign-captcha-ruleset/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$rawSig) {
                if (stripos($header, 'X-Ruleset-Signature:') === 0) {
                    $rawSig = trim(substr($header, strlen('X-Ruleset-Signature:')));
                }
                return strlen($header);
            },
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 304) {
            return null;
        }
        if ($code < 200 || $code >= 300 || !is_string($body) || $body === '') {
            $this->logDebug('fetchSigned HTTP ' . $code . ' (' . $url . ')');
            return null;
        }
        $expected = hash_hmac('sha256', $body, $secret);
        if ($rawSig === '' || !hash_equals($expected, $rawSig)) {
            $this->logDebug('fetchSigned Signatur ungültig – verworfen (' . $url . ').');
            return null;
        }
        return $body;
    }

    /**
     * Holt das aktuelle Ruleset, verifiziert die Signatur und cached es.
     * Wirft NICHT nach außen (fail-safe); gibt true bei Übernahme zurück.
     */
    public function pull(): bool
    {
        $endpoint = rtrim($this->settings->get('cockpit_endpoint'), '/');
        $secret   = $this->settings->get('cockpit_secret');
        if ($endpoint === '' || $secret === '') {
            return false;
        }

        $since    = $this->settings->getInt('cockpit_ruleset_version', 0);
        $signedAt = (string)time();
        $sig      = hash_hmac('sha256', '|' . $signedAt, $secret);

        $instanceId = '';
        try {
            $instanceId = (new LicenseService($this->db, $this->settings))->instanceId();
        } catch (\Throwable) {
        }

        $ch = curl_init($endpoint . '/api/v1/ruleset?since=' . $since);
        if ($ch === false) {
            return false;
        }
        $rawSig = '';
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-Cockpit-Instance: ' . $instanceId,
                'X-Signed-At: ' . $signedAt,
                'X-Signature: ' . $sig,
                'User-Agent: bbfdesign-captcha-ruleset/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$rawSig) {
                if (stripos($header, 'X-Ruleset-Signature:') === 0) {
                    $rawSig = trim(substr($header, strlen('X-Ruleset-Signature:')));
                }
                return strlen($header);
            },
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 304) {
            return false; // bereits aktuell
        }
        if ($code < 200 || $code >= 300 || !is_string($body) || $body === '') {
            $this->logDebug('ruleset HTTP ' . $code);
            return false;
        }

        // Integrität: HMAC über den Rohbody mit dem Shop-Secret.
        $expected = hash_hmac('sha256', $body, $secret);
        if ($rawSig === '' || !hash_equals($expected, $rawSig)) {
            $this->logDebug('ruleset Signatur ungültig – verworfen (fail-safe).');
            return false;
        }

        $ruleset = json_decode($body, true);
        if (!is_array($ruleset) || !isset($ruleset['version'])) {
            $this->logDebug('ruleset JSON ungültig – verworfen.');
            return false;
        }

        // Nur vorwärts: ältere Versionen nicht übernehmen.
        if ((int)$ruleset['version'] < $since) {
            return false;
        }

        $this->settings->set('cockpit_ruleset_cache', $body, 'cockpit');
        $this->settings->set('cockpit_ruleset_version', (string)(int)$ruleset['version'], 'cockpit');
        return true;
    }

    /**
     * Das aktuell gültige Ruleset (oder []), für den Interpreter.
     * Liefert NUR etwas, wenn die Cockpit-Integration aktiv ist – so wirkt das
     * Abschalten von cockpit_enabled sofort (zurück auf eingebaute Defaults).
     *
     * @return array<string,mixed>
     */
    public static function cached(Setting $settings): array
    {
        if (!$settings->getBool('cockpit_enabled')) {
            return [];
        }
        $raw = $settings->get('cockpit_ruleset_cache');
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Die aktuell gecachte zentrale Blocklist (oder []). Nur bei aktiver Integration.
     *
     * @return array<string,mixed>
     */
    public static function blocklist(Setting $settings): array
    {
        if (!$settings->getBool('cockpit_enabled')) {
            return [];
        }
        $raw = $settings->get('cockpit_blocklist_cache');
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Zentrale Fehlalarm-Allowlist aus dem Ruleset: freigegebene E-Mail-Domains
     * (exakt) oder Phrasen (Teilstring). Schlägt JEDE Blockierung (Score/Listen/
     * Blocklist) – die zentrale Fehlalarm-Bremse. Greift nur bei aktiver Integration.
     */
    public static function isAllowlisted(Setting $settings, ?string $email, string $text): bool
    {
        $al = self::cached($settings)['allowlist'] ?? null;
        if (!is_array($al)) {
            return false;
        }

        $domains = $al['emailDomains'] ?? [];
        if (is_array($domains) && $domains !== [] && $email !== null && str_contains($email, '@')) {
            $ed = strtolower(trim(substr($email, strrpos($email, '@') + 1)));
            if ($ed !== '') {
                foreach ($domains as $d) {
                    if (strtolower(trim((string)$d)) === $ed) {
                        return true;
                    }
                }
            }
        }

        $phrases = $al['phrases'] ?? [];
        if (is_array($phrases) && $phrases !== [] && $text !== '') {
            $lower = mb_strtolower($text, 'UTF-8');
            foreach ($phrases as $p) {
                $pat = is_array($p) ? (string)($p['pattern'] ?? '') : (string)$p;
                if ($pat !== '' && str_contains($lower, mb_strtolower($pat, 'UTF-8'))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function logDebug(string $msg): void
    {
        if (!$this->settings->getBool('debug_mode')) {
            return;
        }
        try {
            Shop::Container()->getLogService()->warning('BBF Captcha Ruleset: ' . $msg);
        } catch (\Throwable) {
        }
    }
}
