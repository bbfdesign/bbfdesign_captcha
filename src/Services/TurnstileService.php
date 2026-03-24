<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Cloudflare Turnstile Integration
 *
 * - Kostenlos, privacy-friendly Alternative zu reCAPTCHA
 * - Invisible / Managed / Non-Interactive Modi
 * - WCAG 2.2 AAA konform
 * - Consent nötig (externes Script von Cloudflare)
 * - Server-Validierung via challenges.cloudflare.com
 */
class TurnstileService
{
    private Setting $settings;

    private const FIELD_NAME   = 'cf-turnstile-response';
    private const VERIFY_URL   = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const SCRIPT_URL   = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Prüfe ob Turnstile konfiguriert ist
     */
    public function isConfigured(): bool
    {
        return $this->settings->getBool('turnstile_enabled')
            && !empty($this->settings->get('turnstile_site_key'))
            && !empty($this->settings->get('turnstile_secret_key'));
    }

    /**
     * Widget-HTML rendern
     */
    public function renderWidget(): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $siteKey = $this->settings->get('turnstile_site_key');
        $mode    = $this->settings->get('turnstile_mode', 'managed');

        $html  = '<div class="bbf-captcha-widget bbf-captcha-turnstile"';
        $html .= ' data-bbf-consent="bbfdesign_captcha_turnstile">';
        $html .= '<div class="cf-turnstile"'
                . ' data-sitekey="' . htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8') . '"';

        if ($mode === 'invisible') {
            $html .= ' data-appearance="interaction-only"';
        }

        $html .= ' data-language="de"';
        $html .= ' data-callback="bbfTurnstileCallback"';
        $html .= ' data-error-callback="bbfTurnstileError"';
        $html .= '></div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Script-Tag für Turnstile JS (nur laden bei Consent!)
     */
    public function getScriptTag(): string
    {
        return '<script src="' . self::SCRIPT_URL . '?render=explicit" async defer></script>';
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
                'reason' => 'Turnstile-Token fehlt',
                'score'  => 50,
            ];
        }

        $secretKey = $this->settings->get('turnstile_secret_key');

        if (empty($secretKey)) {
            return [
                'valid'  => false,
                'reason' => 'Turnstile Secret Key nicht konfiguriert',
                'score'  => 0,
            ];
        }

        $response = $this->verifyToken($token, $secretKey);

        if ($response === null) {
            return [
                'valid'  => false,
                'reason' => 'Turnstile-Verifizierung fehlgeschlagen (Netzwerkfehler)',
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
            'reason' => 'Turnstile-Verifizierung fehlgeschlagen: ' . $errorCodes,
            'score'  => 70,
        ];
    }

    /**
     * Token bei Cloudflare verifizieren
     */
    private function verifyToken(string $token, string $secretKey): ?array
    {
        $postFields = http_build_query([
            'secret'   => $secretKey,
            'response' => $token,
            'remoteip' => \Plugin\bbfdesign_captcha\src\Helpers\PluginHelper::getClientIp(),
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
