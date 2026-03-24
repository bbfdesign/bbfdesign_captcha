<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;

/**
 * Google reCAPTCHA v2/v3 Integration
 *
 * - v2: Checkbox "Ich bin kein Roboter"
 * - v3: Invisible mit Score (0.0 = Bot, 1.0 = Mensch)
 * - ACHTUNG: Nicht DSGVO-konform ohne Consent! Daten gehen an Google.
 * - Consent PFLICHT
 */
class RecaptchaService
{
    private Setting $settings;

    private const FIELD_NAME_V2 = 'g-recaptcha-response';
    private const FIELD_NAME_V3 = 'g-recaptcha-response';
    private const VERIFY_URL    = 'https://www.google.com/recaptcha/api/siteverify';
    private const SCRIPT_URL_V2 = 'https://www.google.com/recaptcha/api.js';
    private const SCRIPT_URL_V3 = 'https://www.google.com/recaptcha/api.js?render=';

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
    }

    public function isConfigured(): bool
    {
        return $this->settings->getBool('recaptcha_enabled')
            && !empty($this->settings->get('recaptcha_site_key'))
            && !empty($this->settings->get('recaptcha_secret_key'));
    }

    public function getVersion(): string
    {
        return $this->settings->get('recaptcha_version', 'v3');
    }

    /**
     * Widget-HTML rendern
     */
    public function renderWidget(): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $siteKey = $this->settings->get('recaptcha_site_key');
        $version = $this->getVersion();

        $html  = '<div class="bbf-captcha-widget bbf-captcha-recaptcha"';
        $html .= ' data-bbf-consent="bbfdesign_captcha_recaptcha">';

        if ($version === 'v2') {
            $html .= '<div class="g-recaptcha"'
                    . ' data-sitekey="' . htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8') . '"'
                    . ' data-callback="bbfRecaptchaCallback"'
                    . ' data-expired-callback="bbfRecaptchaExpired"'
                    . '></div>';
        } else {
            // v3: Invisible, Token wird per JS generiert
            $html .= '<input type="hidden" name="g-recaptcha-response" id="bbf-recaptcha-token" value="">';
            $html .= '<input type="hidden" name="bbf_recaptcha_action" value="submit">';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Script-Tag
     */
    public function getScriptTag(): string
    {
        $siteKey = $this->settings->get('recaptcha_site_key');
        $version = $this->getVersion();

        if ($version === 'v3') {
            return '<script src="' . self::SCRIPT_URL_V3
                 . htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8')
                 . '" async defer></script>';
        }

        return '<script src="' . self::SCRIPT_URL_V2 . '?hl=de" async defer></script>';
    }

    /**
     * v3 Inline-Script für Token-Generierung
     */
    public function getV3InlineScript(): string
    {
        if ($this->getVersion() !== 'v3') {
            return '';
        }

        $siteKey = $this->settings->get('recaptcha_site_key');

        return '<script>'
             . 'grecaptcha.ready(function(){'
             . 'grecaptcha.execute("' . addslashes($siteKey) . '",{action:"submit"})'
             . '.then(function(token){'
             . 'var el=document.getElementById("bbf-recaptcha-token");'
             . 'if(el)el.value=token;'
             . '});'
             . '});'
             . '</script>';
    }

    /**
     * Server-seitige Validierung
     *
     * @return array{valid: bool, reason: string, score: int}
     */
    public function validate(array $postData): array
    {
        $token = $postData[self::FIELD_NAME_V2] ?? '';

        if (empty($token)) {
            return [
                'valid'  => false,
                'reason' => 'reCAPTCHA-Token fehlt',
                'score'  => 50,
            ];
        }

        $secretKey = $this->settings->get('recaptcha_secret_key');

        if (empty($secretKey)) {
            return [
                'valid'  => false,
                'reason' => 'reCAPTCHA Secret Key nicht konfiguriert',
                'score'  => 0,
            ];
        }

        $response = $this->verifyToken($token, $secretKey);

        if ($response === null) {
            return [
                'valid'  => false,
                'reason' => 'reCAPTCHA-Verifizierung fehlgeschlagen (Netzwerkfehler)',
                'score'  => 30,
            ];
        }

        if ($response['success'] !== true) {
            $errorCodes = implode(', ', $response['error-codes'] ?? ['unknown']);
            return [
                'valid'  => false,
                'reason' => 'reCAPTCHA ungültig: ' . $errorCodes,
                'score'  => 70,
            ];
        }

        // v3: Score prüfen
        if ($this->getVersion() === 'v3' && isset($response['score'])) {
            $threshold = $this->settings->getFloat('recaptcha_score_threshold', 0.5);
            $score     = (float)$response['score'];

            if ($score < $threshold) {
                return [
                    'valid'  => false,
                    'reason' => 'reCAPTCHA Score zu niedrig (' . $score . ' < ' . $threshold . ')',
                    'score'  => (int)((1.0 - $score) * 100),
                ];
            }
        }

        return [
            'valid'  => true,
            'reason' => '',
            'score'  => 0,
        ];
    }

    private function verifyToken(string $token, string $secretKey): ?array
    {
        $postFields = http_build_query([
            'secret'   => $secretKey,
            'response' => $token,
            'remoteip' => PluginHelper::getClientIp(),
        ]);

        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($result === false || !empty($error)) {
            return null;
        }

        $decoded = json_decode($result, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function getFieldName(): string
    {
        return self::FIELD_NAME_V2;
    }
}
