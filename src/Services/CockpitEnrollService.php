<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Config\CockpitDefaults;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Auto-Anmeldung (Self-Registration) am CaptchaCockpit – ForgePush-Stil.
 *
 * Statt im Cockpit einen Shop manuell anzulegen und das pro-Shop-Secret zu kopieren,
 * meldet sich das Plugin mit instanceId + Host selbst an: `POST /api/v1/enroll`,
 * signiert mit dem GETEILTEN Enrollment-Key (`cockpit_enrollment_secret`). Das Cockpit
 * legt den Shop automatisch an und liefert das pro-Shop-`shopSecret` zurück, das hier
 * als `cockpit_secret` gespeichert und ab dann für ingest/ruleset/feedback genutzt wird.
 *
 * Vertrag: ~/captchacockpit/docs/API-CONTRACT.md §2a.
 */
class CockpitEnrollService
{
    private const HTTP_TIMEOUT = 8;

    private DbInterface $db;
    private Setting $settings;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    /** Effektiver Endpoint: Setting → ausgelieferter Default. */
    public function effectiveEndpoint(): string
    {
        $ep = trim($this->settings->get('cockpit_endpoint'));
        return rtrim($ep !== '' ? $ep : CockpitDefaults::ENDPOINT, '/');
    }

    /** Effektiver Enrollment-Key: Setting → Server-Konstante → leer. */
    public function effectiveEnrollmentKey(): string
    {
        $k = trim($this->settings->get('cockpit_enrollment_secret'));
        return $k !== '' ? $k : CockpitDefaults::enrollmentSecretFromConstant();
    }

    /**
     * Zero-Touch-Selbstanmeldung beim Boot/Cron: wenn noch KEIN cockpit_secret
     * vorliegt und ein Enrollment-Key verfügbar ist, einmalig (gedrosselt) enrollen.
     * Fail-open, nie im Hotpath. Telemetrie bleibt davon getrennt (AVV-gated).
     */
    public function enrollIfDue(): void
    {
        // Schon angemeldet? Nichts tun.
        if ($this->settings->get('cockpit_secret') !== '') {
            return;
        }
        // Kein Key (weder Setting noch Server-Konstante) → Auto-Enroll aus.
        if ($this->effectiveEnrollmentKey() === '') {
            return;
        }
        // Fehler-Backoff: höchstens alle 6 h ein Versuch.
        $last = $this->settings->getInt('cockpit_enroll_last', 0);
        if ($last > 0 && (time() - $last) < 21600) {
            return;
        }
        $this->settings->set('cockpit_enroll_last', (string)time(), 'cockpit');
        try {
            $this->enroll();
        } catch (\Throwable $e) {
            if ($this->settings->getBool('debug_mode')) {
                try {
                    Shop::Container()->getLogService()->warning('BBF Captcha enrollIfDue: ' . $e->getMessage());
                } catch (\Throwable) {
                }
            }
        }
    }

    /**
     * CAP-15 – Kopplung per Einmal-Code (Cockpit CC-14, Vertrag §2b).
     *
     * Der Operator erzeugt im Cockpit unter „Instanzen" einen Code (BBF-XXXX-XXXX)
     * und trägt ihn hier ein. Anders als beim Enrollment (§2a) braucht es dafür
     * KEINEN geteilten Schlüssel auf dem Server – der Code selbst ist der Nachweis.
     * Antwortformat identisch: das pro-Shop-Secret wird als `cockpit_secret`
     * übernommen und ab dann für ingest/ruleset/feedback genutzt.
     *
     * @return array{success:bool,message:string}
     */
    public function pair(string $code): array
    {
        $code     = trim($code);
        $endpoint = $this->effectiveEndpoint();
        if ($code === '') {
            return ['success' => false, 'message' => 'Bitte den Kopplungs-Code aus dem Cockpit eintragen.'];
        }
        if ($endpoint === '') {
            return ['success' => false, 'message' => 'Kein Cockpit-Endpoint konfiguriert.'];
        }

        $payload = $this->identityPayload();
        $payload['code'] = $code;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        $res = $this->post($endpoint . '/api/v1/pair', $body, [
            'Content-Type: application/json',
            'User-Agent: bbfdesign-captcha-pair/1.0',
        ]);
        if ($res['error'] !== '') {
            return ['success' => false, 'message' => 'Cockpit nicht erreichbar (' . $res['error'] . ').'];
        }

        $code_ = $res['status'];
        if ($code_ === 401) {
            return ['success' => false, 'message' => 'Kopplungs-Code unbekannt. Bitte im Cockpit einen neuen erzeugen.'];
        }
        if ($code_ === 409) {
            return ['success' => false, 'message' => 'Code bereits eingelöst oder Domain gehört einer anderen Installation.'];
        }
        if ($code_ === 410) {
            return ['success' => false, 'message' => 'Kopplungs-Code abgelaufen. Bitte im Cockpit einen neuen erzeugen.'];
        }
        if ($code_ === 429) {
            return ['success' => false, 'message' => 'Zu viele Versuche – bitte einen Moment warten.'];
        }
        if ($code_ < 200 || $code_ >= 300) {
            return ['success' => false, 'message' => 'Kopplung fehlgeschlagen (HTTP ' . $code_ . ').'];
        }

        return $this->storeSecretFromResponse($res['body'], $endpoint, 'Mit dem Cockpit gekoppelt – Secret übernommen.');
    }

    /**
     * Führt die Selbst-Anmeldung aus und speichert bei Erfolg das pro-Shop-Secret.
     *
     * @return array{success:bool,message:string}
     */
    public function enroll(): array
    {
        $endpoint        = $this->effectiveEndpoint();
        $enrollmentKey   = $this->effectiveEnrollmentKey();
        if ($endpoint === '') {
            return ['success' => false, 'message' => 'Kein Cockpit-Endpoint konfiguriert.'];
        }
        if ($enrollmentKey === '') {
            return ['success' => false, 'message' => 'Kein Anmelde-Schlüssel verfügbar (Setting oder Server-Konstante BBFCAPTCHA_ENROLLMENT_SECRET).'];
        }

        $payload  = $this->identityPayload();
        $body     = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $signedAt = (string)time();
        $sig      = hash_hmac('sha256', $body . '|' . $signedAt, $enrollmentKey);

        $res = $this->post($endpoint . '/api/v1/enroll', $body, [
            'Content-Type: application/json',
            'X-Cockpit-Instance: ' . $payload['instanceId'],
            'X-Signed-At: ' . $signedAt,
            'X-Signature: ' . $sig,
            'User-Agent: bbfdesign-captcha-enroll/1.0',
        ]);
        $resp = $res['body'];
        $code = $res['status'];

        if ($res['error'] !== '') {
            return ['success' => false, 'message' => 'Cockpit nicht erreichbar (' . $res['error'] . ').'];
        }
        if ($code === 503) {
            return ['success' => false, 'message' => 'Auto-Anmeldung ist am Cockpit nicht aktiviert (Enrollment serverseitig aus).'];
        }
        if ($code === 401) {
            return ['success' => false, 'message' => 'Enrollment-Key ungültig.'];
        }
        if ($code === 409) {
            return ['success' => false, 'message' => 'Diese Domain ist bereits einer anderen Installation zugeordnet.'];
        }
        if ($code === 429) {
            return ['success' => false, 'message' => 'Zu viele Anmeldeversuche – bitte später erneut.'];
        }
        if ($code < 200 || $code >= 300) {
            return ['success' => false, 'message' => 'Anmeldung fehlgeschlagen (HTTP ' . $code . ').'];
        }

        return $this->storeSecretFromResponse($resp, $endpoint, 'Automatisch am Cockpit angemeldet – Secret übernommen.');
    }

    /**
     * Identität dieser Installation – für /enroll wie /pair identisch.
     *
     * @return array{instanceId:string,domain:string,pluginVersion:string,shopVersion:string}
     */
    private function identityPayload(): array
    {
        $license       = new LicenseService($this->db, $this->settings);
        $pluginVersion = '';
        try {
            $pluginVersion = (string)(\JTL\Plugin\Helper::getPluginById('bbfdesign_captcha')?->getCurrentVersion() ?? '');
        } catch (\Throwable) {
        }

        return [
            'instanceId'    => $license->instanceId(),
            'domain'        => $license->host(),
            'pluginVersion' => $pluginVersion,
            'shopVersion'   => defined('APPLICATION_VERSION') ? \APPLICATION_VERSION : '',
        ];
    }

    /**
     * Kleiner POST-Helfer mit harten Timeouts.
     *
     * @param string[] $headers
     * @return array{status:int,body:string,error:string}
     */
    private function post(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => '', 'error' => 'curl'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['status' => $code, 'body' => '', 'error' => $err !== '' ? $err : 'unbekannt'];
        }

        return ['status' => $code, 'body' => (string)$resp, 'error' => ''];
    }

    /**
     * Übernimmt das pro-Shop-Secret aus einer /enroll- oder /pair-Antwort.
     *
     * @return array{success:bool,message:string}
     */
    private function storeSecretFromResponse(string $resp, string $endpoint, string $okMessage): array
    {
        $data   = json_decode($resp, true);
        $secret = is_array($data) ? (string)($data['shopSecret'] ?? '') : '';
        if ($secret === '') {
            return ['success' => false, 'message' => 'Cockpit-Antwort ohne Secret.'];
        }

        // Pro-Shop-Secret übernehmen → ab jetzt per-Shop-HMAC für ingest/ruleset/feedback.
        $this->settings->set('cockpit_secret', $secret, 'cockpit');
        // Endpoint persistieren (falls über den Default gekoppelt wurde), damit
        // Telemetrie/Ruleset/Feedback denselben Endpoint verwenden.
        if (trim($this->settings->get('cockpit_endpoint')) === '') {
            $this->settings->set('cockpit_endpoint', $endpoint, 'cockpit');
        }
        if (isset($data['rulesetVersion'])) {
            $this->settings->set('cockpit_ruleset_version', (string)(int)$data['rulesetVersion'], 'cockpit');
        }
        $this->settings->invalidateCache();

        if ($this->settings->getBool('debug_mode')) {
            try {
                Shop::Container()->getLogService()->notice('BBF Captcha: Cockpit-Anbindung erfolgreich.');
            } catch (\Throwable) {
            }
        }

        return ['success' => true, 'message' => $okMessage];
    }
}
