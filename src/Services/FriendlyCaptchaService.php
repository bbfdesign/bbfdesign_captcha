<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;

/**
 * Friendly Captcha Integration
 *
 * - Europäischer Anbieter (DSGVO-freundlich, EU-Datenspeicherung)
 * - Proof-of-Work basiert (ähnlich ALTCHA aber SaaS)
 * - Invisible, kein Puzzle für Nutzer
 * - WCAG 2.2 AA konform
 * - Free-Tier für kleine Websites
 */
class FriendlyCaptchaService
{
    private Setting $settings;

    private const FIELD_NAME  = 'frc-captcha-solution';
    private const VERIFY_URL  = 'https://api.friendlycaptcha.com/api/v1/siteverify';
    private const SCRIPT_URL  = 'https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.14/widget.module.min.js';

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
    }

    public function isConfigured(): bool
    {
        return $this->settings->getBool('friendly_captcha_enabled')
            && !empty($this->settings->get('friendly_captcha_site_key'))
            && !empty($this->settings->get('friendly_captcha_api_key'));
    }

    /**
     * Widget-HTML rendern
     */
    public function renderWidget(): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $siteKey = $this->settings->get('friendly_captcha_site_key');

        $html  = '<div class="bbf-captcha-widget bbf-captcha-friendly"';
        $html .= ' data-bbf-consent="bbfdesign_captcha_friendly_captcha">';
        $html .= '<div class="frc-captcha"'
                . ' data-sitekey="' . htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-lang="de"'
                . '></div>';
        $html .= '</div>';

        return $html;
    }

    public function getScriptTag(): string
    {
        return '<script type="module" src="' . self::SCRIPT_URL . '" async defer></script>';
    }

    /**
     * Server-seitige Validierung
     *
     * @return array{valid: bool, reason: string, score: int}
     */
    public function validate(array $postData): array
    {
        $solution = $postData[self::FIELD_NAME] ?? '';

        if (empty($solution)) {
            return [
                'valid'  => false,
                'reason' => 'Friendly Captcha Solution fehlt',
                'score'  => 50,
            ];
        }

        // ".UNSTARTED" oder ".UNFINISHED" sind Fehler
        if (str_starts_with($solution, '.')) {
            return [
                'valid'  => false,
                'reason' => 'Friendly Captcha nicht gelöst',
                'score'  => 60,
            ];
        }

        $siteKey = $this->settings->get('friendly_captcha_site_key');
        $apiKey  = $this->settings->get('friendly_captcha_api_key');

        $response = $this->verifySolution($solution, $siteKey, $apiKey);

        if ($response === null) {
            return [
                'valid'  => false,
                'reason' => 'Friendly Captcha Verifizierung fehlgeschlagen (Netzwerkfehler)',
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

        $errors = implode(', ', $response['errors'] ?? ['unknown']);
        return [
            'valid'  => false,
            'reason' => 'Friendly Captcha ungültig: ' . $errors,
            'score'  => 70,
        ];
    }

    private function verifySolution(string $solution, string $siteKey, string $apiKey): ?array
    {
        $payload = json_encode([
            'solution' => $solution,
            'secret'   => $apiKey,
            'sitekey'  => $siteKey,
        ]);

        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
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
