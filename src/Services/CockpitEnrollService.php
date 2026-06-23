<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use JTL\Shop;
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

    /**
     * Führt die Selbst-Anmeldung aus und speichert bei Erfolg das pro-Shop-Secret.
     *
     * @return array{success:bool,message:string}
     */
    public function enroll(): array
    {
        $endpoint        = rtrim($this->settings->get('cockpit_endpoint'), '/');
        $enrollmentKey   = $this->settings->get('cockpit_enrollment_secret');
        if ($endpoint === '') {
            return ['success' => false, 'message' => 'Bitte zuerst den Cockpit-Endpoint eintragen.'];
        }
        if ($enrollmentKey === '') {
            return ['success' => false, 'message' => 'Bitte den Enrollment-Key (Anmelde-Schlüssel) eintragen.'];
        }

        $license = new LicenseService($this->db, $this->settings);
        $pluginVersion = '';
        try {
            $pluginVersion = (string)(\JTL\Plugin\Helper::getPluginById('bbfdesign_captcha')?->getCurrentVersion() ?? '');
        } catch (\Throwable) {
        }

        $payload = [
            'instanceId'    => $license->instanceId(),
            'domain'        => $license->host(),
            'pluginVersion' => $pluginVersion,
            'shopVersion'   => defined('APPLICATION_VERSION') ? \APPLICATION_VERSION : '',
        ];
        $body     = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $signedAt = (string)time();
        $sig      = hash_hmac('sha256', $body . '|' . $signedAt, $enrollmentKey);

        $ch = curl_init($endpoint . '/api/v1/enroll');
        if ($ch === false) {
            return ['success' => false, 'message' => 'Interner Fehler (curl).'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Cockpit-Instance: ' . $payload['instanceId'],
                'X-Signed-At: ' . $signedAt,
                'X-Signature: ' . $sig,
                'User-Agent: bbfdesign-captcha-enroll/1.0',
            ],
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
            return ['success' => false, 'message' => 'Cockpit nicht erreichbar' . ($err !== '' ? ' (' . $err . ')' : '') . '.'];
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

        $data   = json_decode((string)$resp, true);
        $secret = is_array($data) ? (string)($data['shopSecret'] ?? '') : '';
        if ($secret === '') {
            return ['success' => false, 'message' => 'Cockpit-Antwort ohne Secret.'];
        }

        // Pro-Shop-Secret übernehmen → ab jetzt per-Shop-HMAC für ingest/ruleset/feedback.
        $this->settings->set('cockpit_secret', $secret, 'cockpit');
        if (isset($data['rulesetVersion'])) {
            $this->settings->set('cockpit_ruleset_version', (string)(int)$data['rulesetVersion'], 'cockpit');
        }
        $this->settings->invalidateCache();

        if ($this->settings->getBool('debug_mode')) {
            try {
                Shop::Container()->getLogService()->notice('BBF Captcha: Cockpit-Auto-Anmeldung erfolgreich.');
            } catch (\Throwable) {
            }
        }

        return ['success' => true, 'message' => 'Automatisch am Cockpit angemeldet – Secret übernommen.'];
    }
}
