<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Meldet Spam-Log-Ereignisse DSGVO-minimiert an das zentrale CaptchaCockpit.
 *
 * Grundsätze:
 * - Default AUS (cockpit_enabled). Ohne Endpoint/Secret passiert nichts.
 * - Fail-open: jeder Fehler/Timeout wird verschluckt; der Schutz des Shops hängt
 *   NICHT vom Cockpit ab. Der Cursor rückt nur bei erfolgreichem Versand vor.
 * - Datenminimierung: es verlassen den Shop NUR pseudonyme/aggregierte Merkmale
 *   (ipHash, contentFp/contentShape, E-Mail-DOMAIN) – keine Klar-IP, kein
 *   Klartext-Inhalt, kein Name, keine volle E-Mail-Adresse.
 *
 * Vertrag: ~/captchacockpit/docs/API-CONTRACT.md (POST /api/v1/ingest).
 */
class CockpitTelemetryService
{
    private const BATCH_SIZE      = 500;
    private const RUN_INTERVAL    = 900;   // s – höchstens alle 15 min senden
    private const HTTP_TIMEOUT    = 8;     // s
    private const IGNORE_FIELDS   = ['password', 'passwort', 'pass', 'pass2', 'token', 'jtl_token', 'bbf_altcha', 'bbf_ct', 'hp_', 'jtl_hp'];

    private DbInterface $db;
    private Setting $settings;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    /** Gedrosselter Aufruf aus dem Boot-/Cron-Pfad. */
    public function runIfDue(): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $last = $this->settings->getInt('cockpit_last_run', 0);
        if ($last > 0 && (time() - $last) < self::RUN_INTERVAL) {
            return;
        }
        $this->settings->set('cockpit_last_run', (string)time(), 'cockpit');
        try {
            $this->flush();
        } catch (\Throwable $e) {
            $this->logDebug('flush: ' . $e->getMessage());
        }
    }

    public function isEnabled(): bool
    {
        return $this->settings->getBool('cockpit_enabled')
            && $this->settings->get('cockpit_endpoint') !== ''
            && $this->settings->get('cockpit_secret') !== '';
    }

    /** Sendet einen Batch neuer Ereignisse; rückt den Cursor nur bei Erfolg. */
    public function flush(): void
    {
        $cursor = $this->settings->getInt('cockpit_cursor_id', 0);
        $rows   = $this->db->queryPrepared(
            'SELECT `id`, `ip_address`, `form_type`, `detection_method`, `spam_score`,
                    `action_taken`, `user_agent`, `request_data`, `reason`, `created_at`
             FROM `bbf_captcha_spam_log`
             WHERE `id` > :cursor
             ORDER BY `id` ASC
             LIMIT ' . self::BATCH_SIZE,
            ['cursor' => $cursor],
            2
        );
        $rows = is_array($rows) ? $rows : [];
        if ($rows === []) {
            return;
        }

        $pepper = $this->pepper();
        $shareIpPrefix = $this->settings->getBool('cockpit_share_ip_prefix');
        $events = [];
        $maxId  = $cursor;
        foreach ($rows as $row) {
            $maxId    = max($maxId, (int)$row->id);
            $events[] = $this->mapEvent($row, $pepper, $shareIpPrefix);
        }

        $payload = [
            'batchId' => bin2hex(random_bytes(16)),
            'events'  => $events,
        ];

        if ($this->send('/api/v1/ingest', $payload)) {
            $this->settings->set('cockpit_cursor_id', (string)$maxId, 'cockpit');
        }
        // Bei Misserfolg: Cursor bleibt → Retry beim nächsten Lauf (fail-open).
    }

    /**
     * Mappt eine Log-Zeile auf ein anonymisiertes Ingest-Event.
     *
     * @return array<string,mixed>
     */
    private function mapEvent(object $row, string $pepper, bool $shareIpPrefix): array
    {
        $ip      = (string)($row->ip_address ?? '');
        $reasons = array_values(array_filter(array_map('trim', explode(';', (string)($row->reason ?? '')))));

        [$contentFp, $contentShape, $emailDomain] = $this->deriveContent((string)($row->request_data ?? ''));

        $event = [
            'occurredAt'      => $this->toIso((string)($row->created_at ?? '')),
            'formType'        => (string)($row->form_type ?? ''),
            'action'          => strtoupper((string)($row->action_taken ?? 'logged')),
            'score'           => (int)($row->spam_score ?? 0),
            'detectionMethod' => (string)($row->detection_method ?? ''),
            'reasons'         => $reasons,
            'ipHash'          => $ip !== '' ? hash_hmac('sha256', $ip, $pepper) : null,
            'contentFp'       => $contentFp,
            'contentShape'    => $contentShape,
            'emailDomain'     => $emailDomain,
            'userAgentHash'   => !empty($row->user_agent) ? hash('sha256', (string)$row->user_agent) : null,
        ];
        if ($shareIpPrefix && $ip !== '') {
            $event['ipPrefix'] = $this->ipPrefix($ip);
        }

        return $event;
    }

    /**
     * Leitet aus den (rohen) Request-Daten NUR pseudonyme Merkmale ab.
     * Gibt [contentFp, contentShape, emailDomain] zurück – niemals Klartext.
     *
     * @return array{0:?string,1:array<string,mixed>,2:?string}
     */
    private function deriveContent(string $requestDataJson): array
    {
        $data = json_decode($requestDataJson, true);
        if (!is_array($data)) {
            return [null, [], null];
        }

        $texts       = [];
        $emailDomain = null;
        $flat        = $this->flatten($data);
        foreach ($flat as $key => $value) {
            $lkey = strtolower((string)$key);
            foreach (self::IGNORE_FIELDS as $skip) {
                if (str_contains($lkey, $skip)) {
                    continue 2;
                }
            }
            if ($emailDomain === null && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $parts = explode('@', $value);
                $emailDomain = strtolower(end($parts));
                continue;
            }
            if ($value !== '') {
                $texts[] = $value;
            }
        }

        $combined = trim(implode(' ', $texts));
        if ($combined === '') {
            return [null, [], $emailDomain];
        }

        $len         = mb_strlen($combined, 'UTF-8');
        $upper       = preg_match_all('/[A-ZÄÖÜ]/u', $combined);
        $digits      = preg_match_all('/\d/', $combined);
        $urls        = preg_match_all('#https?://#i', $combined);
        $transitions = $this->caseTransitions($combined);

        $shape = [
            'len'         => $len,
            'upperRatio'  => $len > 0 ? round($upper / $len, 3) : 0,
            'transitions' => $transitions,
            'digits'      => (int)$digits,
            'urls'        => (int)$urls,
        ];

        // Fingerprint des normalisierten Inhalts (Kampagnen-Erkennung), nicht umkehrbar.
        $fp = hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', $combined) ?? $combined, 'UTF-8'));

        return [$fp, $shape, $emailDomain];
    }

    private function caseTransitions(string $text): int
    {
        $letters = preg_replace('/[^A-Za-zÄÖÜäöüß]/u', '', $text) ?? '';
        $chars   = preg_split('//u', $letters, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $t       = 0;
        for ($i = 1, $n = count($chars); $i < $n; $i++) {
            $p = $chars[$i - 1];
            $c = $chars[$i];
            $pu = ($p === mb_strtoupper($p, 'UTF-8') && $p !== mb_strtolower($p, 'UTF-8'));
            $cu = ($c === mb_strtoupper($c, 'UTF-8') && $c !== mb_strtolower($c, 'UTF-8'));
            if ($pu !== $cu) {
                $t++;
            }
        }
        return $t;
    }

    /**
     * @param array<mixed> $data
     * @param array<string,string> $out
     * @return array<string,string>
     */
    private function flatten(array $data, array &$out = []): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $this->flatten($v, $out);
            } elseif (is_string($v) || is_numeric($v)) {
                $out[(string)$k] = (string)$v;
            }
        }
        return $out;
    }

    private function ipPrefix(string $ip): string
    {
        if (str_contains($ip, ':')) {
            // IPv6 → /48
            $bin = @inet_pton($ip);
            if ($bin !== false) {
                $masked = substr($bin, 0, 6) . str_repeat("\0", strlen($bin) - 6);
                return (string)@inet_ntop($masked) . '/48';
            }
            return $ip;
        }
        // IPv4 → /24
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
        }
        return $ip;
    }

    private function toIso(string $mysqlDt): string
    {
        try {
            return (new \DateTimeImmutable($mysqlDt, new \DateTimeZone('UTC')))->format('c');
        } catch (\Throwable) {
            return gmdate('c');
        }
    }

    private function pepper(): string
    {
        $pepper = $this->settings->get('cockpit_pepper');
        if ($pepper === '') {
            $pepper = bin2hex(random_bytes(32));
            $this->settings->set('cockpit_pepper', $pepper, 'cockpit');
        }
        return $pepper;
    }

    /**
     * HMAC-signierter POST an das Cockpit. true bei 2xx, sonst false (fail-open).
     *
     * @param array<string,mixed> $payload
     */
    private function send(string $path, array $payload): bool
    {
        $endpoint = rtrim($this->settings->get('cockpit_endpoint'), '/');
        $secret   = $this->settings->get('cockpit_secret');
        if ($endpoint === '' || $secret === '') {
            return false;
        }

        $instanceId = '';
        try {
            $instanceId = (new LicenseService($this->db, $this->settings))->instanceId();
        } catch (\Throwable) {
            // instanceId optional – ohne sie akzeptiert das Cockpit den Request nicht,
            // aber ein Fehler hier darf nichts brechen.
        }

        $body     = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $signedAt = (string)time();
        $sig      = hash_hmac('sha256', $body . '|' . $signedAt, $secret);

        $ch = curl_init($endpoint . $path);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Cockpit-Instance: ' . $instanceId,
                'X-Signed-At: ' . $signedAt,
                'X-Signature: ' . $sig,
                'User-Agent: bbfdesign-captcha-telemetry/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            $this->logDebug('send ' . $path . ' → HTTP ' . $code . ($err !== '' ? ' (' . $err . ')' : ''));
            return false;
        }
        return true;
    }

    private function logDebug(string $msg): void
    {
        if (!$this->settings->getBool('debug_mode')) {
            return;
        }
        try {
            Shop::Container()->getLogService()->warning('BBF Captcha Cockpit-Telemetrie: ' . $msg);
        } catch (\Throwable) {
            // egal
        }
    }
}
