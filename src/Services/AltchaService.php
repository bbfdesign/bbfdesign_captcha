<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * ALTCHA Proof-of-Work Integration
 *
 * - 100% self-hosted, KEINE externen Aufrufe
 * - DSGVO/CCPA/PIPL konform
 * - Keine Cookies, kein Tracking, kein Fingerprinting
 * - WCAG 2.2 AA konform
 * - Server generiert Challenge, Browser löst sie (PoW)
 * - Für Bots: CPU-Kosten machen Massenspam unwirtschaftlich
 */
class AltchaService
{
    private Setting $settings;

    /** Name des Hidden-Fields */
    private const FIELD_NAME = 'bbf_altcha';

    /** Name des POST-Feldes mit der ALTCHA-Lösung (für Pflicht-Prüfungen von außen). */
    public static function fieldName(): string
    {
        return self::FIELD_NAME;
    }

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
    }

    /**
     * HMAC-Key holen (mit automatischer Rotation)
     */
    public function getHmacKey(): string
    {
        $key = $this->settings->get('altcha_hmac_key');

        if (empty($key)) {
            $key = bin2hex(random_bytes(32));
            $this->settings->set('altcha_hmac_key', $key, 'altcha');
            $this->settings->set('altcha_hmac_rotated_at', date('Y-m-d H:i:s'), 'altcha');
        }

        // Wöchentliche Key-Rotation prüfen
        $rotatedAt = $this->settings->get('altcha_hmac_rotated_at');
        if (!empty($rotatedAt)) {
            $rotatedTime = strtotime($rotatedAt);
            if ($rotatedTime !== false && (time() - $rotatedTime) > 604800) { // 7 Tage
                $key = bin2hex(random_bytes(32));
                $this->settings->set('altcha_hmac_key', $key, 'altcha');
                $this->settings->set('altcha_hmac_rotated_at', date('Y-m-d H:i:s'), 'altcha');
            }
        }

        return $key;
    }

    /**
     * Neue Challenge generieren
     */
    public function createChallenge(): array
    {
        $hmacKey   = $this->getHmacKey();
        $maxNumber = $this->settings->getInt('altcha_maxnumber', 100000);
        $maxNumber = max(1000, $maxNumber);

        $salt      = bin2hex(random_bytes(12));
        $number    = random_int(0, $maxNumber);
        $challenge = hash('sha256', $salt . (string)$number);
        $signature = hash_hmac('sha256', $challenge, $hmacKey);

        return [
            'algorithm'  => 'SHA-256',
            'challenge'  => $challenge,
            'maxnumber'  => $maxNumber,
            'salt'       => $salt,
            'signature'  => $signature,
        ];
    }

    /**
     * Challenge als JSON-Response (für API-Endpoint)
     */
    public function createChallengeJson(): string
    {
        return json_encode($this->createChallenge());
    }

    /**
     * Solution verifizieren
     */
    public function verifySolution(string $payload): bool
    {
        if (empty($payload)) {
            return false;
        }

        // Payload ist Base64-encoded JSON
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            // Versuche direkt als JSON zu parsen
            $decoded = $payload;
        }

        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return false;
        }

        $algorithm = $data['algorithm'] ?? '';
        $challenge = $data['challenge'] ?? '';
        $number    = $data['number'] ?? -1;
        $salt      = $data['salt'] ?? '';
        $signature = $data['signature'] ?? '';

        if ($algorithm !== 'SHA-256' || empty($challenge) || empty($salt)) {
            return false;
        }

        $hmacKey = $this->getHmacKey();

        // Challenge verifizieren: hash(salt + number) === challenge
        $expectedChallenge = hash('sha256', $salt . (string)$number);
        if (!hash_equals($expectedChallenge, $challenge)) {
            return false;
        }

        // Signatur verifizieren: hmac(challenge, key) === signature
        $expectedSignature = hash_hmac('sha256', $challenge, $hmacKey);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * ALTCHA-Felder validieren
     *
     * @return array{valid: bool, reason: string, score: int}
     */
    public function validate(array $postData): array
    {
        $payload = $postData[self::FIELD_NAME] ?? '';

        if (empty($payload)) {
            // FAIL-OPEN (oberste Regel: echte Kunden nie aussperren): eine
            // FEHLENDE Lösung bedeutet "das Widget/JS lief nicht" – z. B. weil
            // das Theme das ALTCHA-Widget nicht rendert, ein Asset blockiert
            // wird oder das Formular vor dem Proof-of-Work absendet. Das ist KEIN
            // verlässliches Bot-Signal und darf legitime Anmeldungen niemals
            // blockieren. Bots werden weiterhin über Honeypot, Timing und den
            // Smart-Spamfilter erkannt. Nur eine PRÄSENTE, aber ungültige Lösung
            // (gefälscht/manipuliert) bleibt ein echtes Spam-Signal.
            return [
                'valid'  => true,
                'reason' => '',
                'score'  => 0,
            ];
        }

        if ($this->verifySolution($payload)) {
            return [
                'valid'  => true,
                'reason' => '',
                'score'  => 0,
            ];
        }

        return [
            'valid'  => false,
            'reason' => 'ALTCHA-Lösung ungültig',
            'score'  => 80,
        ];
    }

    /**
     * ALTCHA Widget HTML rendern (Web Component)
     *
     * Das Widget ist self-hosted → kein Consent nötig!
     * challengeurl zeigt auf den lokalen Challenge-Endpoint.
     */
    public function renderWidget(string $challengeUrl): string
    {
        $html  = '<div class="bbf-captcha-widget bbf-captcha-altcha">';
        $html .= '<altcha-widget'
                . ' challengeurl="' . htmlspecialchars($challengeUrl, ENT_QUOTES, 'UTF-8') . '"'
                . ' name="' . self::FIELD_NAME . '"'
                // auto="onfocus": Proof-of-Work läuft automatisch, sobald der
                // Nutzer das Formular fokussiert – ohne Klick. NICHT "onload":
                // das altcha-Script wird als ES-Modul deferred geladen und wertet
                // das (server-injizierte) Element erst nach dem window-load-Event
                // auf → ein onload-Auto-Trigger verpasst das Event und löst nie.
                // onfocus hängt am Formular-Fokus (feuert immer nach dem Upgrade);
                // bis zum Absenden ist die Lösung da. Keine Expiry-Prüfung (nur
                // HMAC) → keine Staleness-Gefahr.
                . ' auto="onfocus"'
                . ' language="de"'
                . ' hidefooter'
                . '></altcha-widget>';
        $html .= '</div>';

        return $html;
    }

    /**
     * ALTCHA Widget JS-Tag (self-hosted)
     */
    public function getWidgetScriptTag(string $frontendUrl): string
    {
        // WICHTIG: altcha.min.js ist ein ES-Modul (enthält customElements.define).
        // Als klassisches <script> wird die Modul-Syntax nicht ausgeführt → das
        // Custom-Element <altcha-widget> registriert nie und das Widget bleibt
        // tot. Daher type="module" (Module sind per Default deferred).
        return '<script type="module" src="'
             . htmlspecialchars($frontendUrl . 'js/vendor/altcha.min.js', ENT_QUOTES, 'UTF-8')
             . '"></script>';
    }

    /**
     * Feldname
     */
    public static function getFieldName(): string
    {
        return self::FIELD_NAME;
    }
}
