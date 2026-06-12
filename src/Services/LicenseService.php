<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * ForgePush-Lizenzprüfung (Outbound) für BBF Captcha.
 *
 * Das Plugin ruft selbst gegen ForgePush und verifiziert die signierte Antwort:
 *   POST https://forgepush.bbfdesign.de/api/v1/licenses/check
 *
 * Modi: nur instanceId + host (Auto-Licensing-by-Domain) oder zusätzlich
 * licenseKey (klassische Key-Validierung). Die Antwort wird per HMAC-SHA256
 * über "<raw-body>|<X-Signed-At>" mit dem produkt-spezifischen Signing-Secret
 * verifiziert; zusätzlich Anti-Replay (±5 min).
 *
 * Robustheit (live-sicher):
 *  - Netz-/Server-Fehler oder ungültige Signatur → FAIL-OPEN über lokalen Cache
 *    (max. 24 h seit dem letzten gültigen Verdikt). Danach „unverified".
 *  - Klares Negativ-Verdikt (revoked/expired/suspended/domain_mismatch/…)
 *    → FAIL-CLOSED (als ungültig gespeichert). Das DEAKTIVIERT aber NICHT den
 *    Spam-Schutz – es wird nur im Backend gemeldet (Enforcement = informativ).
 *
 * Secrets liegen nie im Repo: bevorzugt Konstante FORGEPUSH_SIGNING_SECRET
 * (z. B. in der Shop-Konfiguration definiert), sonst Plugin-Setting
 * `forgepush_signing_secret`. Analog FORGEPUSH_LICENSE_KEY.
 */
final class LicenseService
{
    private const ENDPOINT        = 'https://forgepush.bbfdesign.de/api/v1/licenses/check';
    private const FAIL_OPEN_TTL   = 86400;  // 24 h Kulanz bei transienten Fehlern
    private const CHECK_INTERVAL  = 43200;  // 12 h zwischen zwei Cron-Checks
    private const HTTP_TIMEOUT     = 8;
    private const SIGNED_SKEW       = 300;   // ±5 min Anti-Replay

    /** Verdikte, die eine Lizenz eindeutig als ungültig kennzeichnen (Fail-closed). */
    private const HARD_NEGATIVE = [
        'revoked', 'expired', 'suspended', 'domain_mismatch', 'instance_limit_exceeded',
    ];

    /** Verdikte, die „noch keine gültige Lizenz" bedeuten (kein harter Fehler). */
    private const PENDING = ['unlicensed', 'ambiguous_domain'];

    public function __construct(
        private DbInterface $db,
        private Setting $settings
    ) {
    }

    // ─── Identität / Konfiguration ──────────────────────────────────────

    /** Stabile, pro-Installation eindeutige UUID (einmal generiert, persistiert). */
    public function instanceId(): string
    {
        $id = $this->settings->get('forgepush_instance_id');
        if ($id === '') {
            $id = bin2hex(random_bytes(16));
            $this->settings->set('forgepush_instance_id', $id, 'license');
        }
        return $id;
    }

    /**
     * Server-Fingerprint: stabil-aber-server-spezifisch. Ändert sich beim Umzug
     * auf eine andere Maschine, auch wenn die instanceId mitgenommen wird.
     */
    public function serverFingerprint(): string
    {
        return hash('sha256', implode('|', [
            PHP_VERSION,
            $_SERVER['SERVER_SOFTWARE'] ?? '',
            gethostname() ?: '',
        ]));
    }

    /**
     * Kanonischer Shop-Host für Auto-Licensing-by-Domain. Bewusst aus der
     * konfigurierten Shop-URL (stabil) statt HTTP_HOST – konsistent zwischen
     * Web- und Cron-Kontext und nicht durch Host-Header manipulierbar.
     */
    public function host(): string
    {
        try {
            $host = parse_url(Shop::getURL(), PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        } catch (\Throwable) {
            // Fallback unten
        }
        return (string)($_SERVER['HTTP_HOST'] ?? (gethostname() ?: ''));
    }

    private function signingSecret(): string
    {
        if (defined('FORGEPUSH_SIGNING_SECRET') && (string)\FORGEPUSH_SIGNING_SECRET !== '') {
            return (string)\FORGEPUSH_SIGNING_SECRET;
        }
        return $this->settings->get('forgepush_signing_secret');
    }

    private function licenseKey(): string
    {
        if (defined('FORGEPUSH_LICENSE_KEY') && (string)\FORGEPUSH_LICENSE_KEY !== '') {
            return (string)\FORGEPUSH_LICENSE_KEY;
        }
        return $this->settings->get('forgepush_license_key');
    }

    private function productSlug(): string
    {
        if (defined('FORGEPUSH_PRODUCT_SLUG') && (string)\FORGEPUSH_PRODUCT_SLUG !== '') {
            return (string)\FORGEPUSH_PRODUCT_SLUG;
        }
        return $this->settings->get('forgepush_product_slug');
    }

    /** Ohne Signing-Secret lässt sich die Antwort nicht verifizieren → unkonfiguriert. */
    public function isConfigured(): bool
    {
        return $this->signingSecret() !== '';
    }

    // ─── Check ──────────────────────────────────────────────────────────

    /** Vom Cron aufgerufen: führt den Check nur aus, wenn das Intervall abgelaufen ist. */
    public function checkIfDue(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $last = (int)$this->settings->get('forgepush_checked_at', '0');
        if ($last > 0 && (time() - $last) < self::CHECK_INTERVAL) {
            return false;
        }
        $this->check();
        return true;
    }

    /**
     * Führt den Lizenz-Check aus und cached das (effektive) Ergebnis.
     *
     * @return array{valid:bool,verdict:string,pluginMoved:?array}
     */
    public function check(): array
    {
        if (!$this->isConfigured()) {
            $result = ['valid' => false, 'verdict' => 'unconfigured', 'pluginMoved' => null];
            $this->storeResult($result, false);
            return $this->getStatus();
        }

        $raw = $this->performRemoteCheck();
        $this->storeResult($raw, true);
        return $this->getStatus();
    }

    /**
     * Roher Remote-Check inkl. Signaturprüfung.
     *
     * @return array{valid:bool,verdict:string,pluginMoved:?array}
     */
    private function performRemoteCheck(): array
    {
        $body = [
            'instanceId'        => $this->instanceId(),
            'host'              => $this->host(),
            'serverFingerprint' => $this->serverFingerprint(),
        ];
        $slug = $this->productSlug();
        if ($slug !== '') {
            $body['productSlug'] = $slug;
        }
        $key = $this->licenseKey();
        if ($key !== '') {
            $body['licenseKey'] = $key;
        }
        $payload = (string)json_encode($body);

        $headers = [];
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$headers) {
                if (preg_match('/^([^:]+):\s*(.*?)\r?\n$/', $line, $m)) {
                    $headers[strtolower($m[1])] = $m[2];
                }
                return strlen($line);
            },
        ]);
        $responseBody = curl_exec($ch);
        $status       = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || !is_string($responseBody) || $responseBody === '') {
            return ['valid' => false, 'verdict' => 'network_error', 'pluginMoved' => null];
        }

        $sigHeader = $headers['x-signature'] ?? '';
        $signedAt  = $headers['x-signed-at'] ?? '';
        if (strncmp($sigHeader, 'sha256=', 7) !== 0 || $signedAt === '') {
            return ['valid' => false, 'verdict' => 'unsigned', 'pluginMoved' => null];
        }
        $sig = substr($sigHeader, 7);

        // HMAC über RAW-Body + "|" + X-Signed-At (kein decode/encode – das würde
        // Whitespace ändern und die Signatur brechen).
        $expected = hash_hmac('sha256', $responseBody . '|' . $signedAt, $this->signingSecret());
        if (!hash_equals($expected, $sig)) {
            return ['valid' => false, 'verdict' => 'signature_mismatch', 'pluginMoved' => null];
        }

        // Anti-Replay: max. 5 min Differenz zur eigenen Uhr.
        $ts = strtotime($signedAt);
        if ($ts === false || abs(time() - $ts) > self::SIGNED_SKEW) {
            return ['valid' => false, 'verdict' => 'stale_response', 'pluginMoved' => null];
        }

        $data = json_decode($responseBody, true);
        if (!is_array($data)) {
            return ['valid' => false, 'verdict' => 'unknown', 'pluginMoved' => null];
        }

        $moved = (isset($data['pluginMoved']) && is_array($data['pluginMoved'])) ? $data['pluginMoved'] : null;

        return [
            'valid'       => !empty($data['valid']),
            'verdict'     => is_string($data['verdict'] ?? null) ? $data['verdict'] : 'unknown',
            'pluginMoved' => $moved,
        ];
    }

    /**
     * Wendet die Fail-open/closed-Logik an und persistiert das effektive Ergebnis.
     *
     * @param array{valid:bool,verdict:string,pluginMoved:?array} $raw
     */
    private function storeResult(array $raw, bool $remoteAttempted): void
    {
        $verdict = $raw['verdict'];
        $now     = time();

        $signedVerdicts = ['valid'];                  // klar positiv
        $isTransient    = !in_array($verdict, array_merge($signedVerdicts, self::HARD_NEGATIVE, self::PENDING, ['unconfigured']), true);

        if ($verdict === 'valid') {
            $effectiveValid = true;
            $this->settings->set('forgepush_last_good_at', (string)$now, 'license');
        } elseif (in_array($verdict, self::HARD_NEGATIVE, true)) {
            $effectiveValid = false; // Fail-closed (nur Anzeige, kein Schutz-Abschalten)
        } elseif ($isTransient && $remoteAttempted) {
            // Fail-open über Cache: letztes gültiges Verdikt jünger als 24 h?
            $lastGood       = (int)$this->settings->get('forgepush_last_good_at', '0');
            $effectiveValid = $lastGood > 0 && ($now - $lastGood) < self::FAIL_OPEN_TTL;
        } else {
            // unlicensed / ambiguous_domain / unconfigured
            $effectiveValid = false;
        }

        $this->settings->set('forgepush_verdict', $verdict, 'license');
        $this->settings->set('forgepush_valid', $effectiveValid ? '1' : '0', 'license');
        $this->settings->set('forgepush_checked_at', (string)$now, 'license');
        $this->settings->set(
            'forgepush_plugin_moved',
            $raw['pluginMoved'] !== null ? (string)json_encode($raw['pluginMoved']) : '',
            'license'
        );
    }

    // ─── Status / Anzeige ───────────────────────────────────────────────

    /**
     * Gecachten Lizenzstatus für Anzeige/Logik (ohne Secrets).
     *
     * @return array{valid:bool,verdict:string,pluginMoved:?array,checkedAt:int,configured:bool,host:string,instanceId:string}
     */
    public function getStatus(): array
    {
        $movedRaw = $this->settings->get('forgepush_plugin_moved');
        $moved    = $movedRaw !== '' ? (json_decode($movedRaw, true) ?: null) : null;

        return [
            'valid'      => $this->settings->getBool('forgepush_valid'),
            'verdict'    => $this->settings->get('forgepush_verdict', 'unknown'),
            'pluginMoved' => is_array($moved) ? $moved : null,
            'checkedAt'  => (int)$this->settings->get('forgepush_checked_at', '0'),
            'configured' => $this->isConfigured(),
            'host'       => $this->host(),
            'instanceId' => $this->instanceId(),
        ];
    }

    /** True, wenn das Verdikt eine harte, im Backend zu meldende Lizenzverletzung ist. */
    public function hasHardViolation(): bool
    {
        return in_array($this->settings->get('forgepush_verdict'), self::HARD_NEGATIVE, true);
    }
}
