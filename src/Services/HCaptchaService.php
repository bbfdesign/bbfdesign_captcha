<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;

/**
 * hCaptcha Integration
 *
 * - Privacy-fokussierte Alternative zu reCAPTCHA
 * - Visuelles Challenge (Bilder auswählen)
 * - Consent nötig (externes Script)
 */
class HCaptchaService
{
    private Setting $settings;

    private const FIELD_NAME  = 'h-captcha-response';
    private const VERIFY_URL  = 'https://api.hcaptcha.com/siteverify';
    private const SCRIPT_URL  = 'https://js.hcaptcha.com/1/api.js';

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
    }

    public function isConfigured(): bool
    {
        return $this->settings->getBool('hcaptcha_enabled')
            && !empty($this->settings->get('hcaptcha_site_key'))
            && !empty($this->settings->get('hcaptcha_secret_key'));
    }

    /**
     * Widget-HTML rendern
     */
    public function renderWidget(): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $siteKey = $this->settings->get('hcaptcha_site_key');

        $html  = '<div class="bbf-captcha-widget bbf-captcha-hcaptcha"';
        $html .= ' data-bbf-consent="bbfdesign_captcha_hcaptcha">';
        $html .= '<div class="h-captcha"'
                . ' data-sitekey="' . htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-callback="bbfHcaptchaCallback"'
                . ' data-expired-callback="bbfHcaptchaExpired"'
                . '></div>';
        $html .= '</div>';

        return $html;
    }

    public function getScriptTag(): string
    {
        return '<script src="' . self::SCRIPT_URL . '?hl=de" async defer></script>';
    }

    /**
     * Server-seitige Validierung
     *
     * @return array{valid: bool, reason: string, score: int}
     */
    public function validate(array $postData): array
    {
        $token = $postData[self::FIELD_NAME] ?? '';

        if (empty($token)) {
            return [
                'valid'  => false,
                'reason' => 'hCaptcha-Token fehlt',
                'score'  => 50,
            ];
        }

        $secretKey = $this->settings->get('hcaptcha_secret_key');

        if (empty($secretKey)) {
            return [
                'valid'  => false,
                'reason' => 'hCaptcha Secret Key nicht konfiguriert',
                'score'  => 0,
            ];
        }

        $response = $this->verifyToken($token, $secretKey);

        if ($response === null) {
            return [
                'valid'  => false,
                'reason' => 'hCaptcha-Verifizierung fehlgeschlagen (Netzwerkfehler)',
                'score'  => 30,
            ];
        }

        if ($response['success'] === true) {
            return [
                'valid'  => true,
                'reason' => '',
                'score'  => 0,
            ];
        }

        $errorCodes = implode(', ', $response['error-codes'] ?? ['unknown']);
        return [
            'valid'  => false,
            'reason' => 'hCaptcha ungültig: ' . $errorCodes,
            'score'  => 70,
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
        return self::FIELD_NAME;
    }
}
