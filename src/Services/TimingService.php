<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;

/**
 * Timing-basierter Schutz
 *
 * - Misst die Zeit zwischen Formular-Laden und Absenden
 * - Timestamp wird als HMAC-signierter Token im Hidden-Field gespeichert
 * - Minimum-Zeit konfigurierbar (Standard: 3 Sekunden)
 * - Maximum-Zeit konfigurierbar (Standard: 3600 Sekunden / 1h)
 * - Zu schnell → Bot-Verdacht
 * - Zu langsam → Session-Timeout
 */
class TimingService
{
    private Setting $settings;

    /** Name des Hidden-Fields */
    private const FIELD_NAME = 'bbf_ct';

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
    }

    /**
     * HMAC-Key für Timing-Tokens
     */
    private function getHmacKey(): string
    {
        $key = $this->settings->get('altcha_hmac_key');
        if (empty($key)) {
            // Fallback auf Session-basiertes Secret
            if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['bbf_captcha_hp_salt'])) {
                return $_SESSION['bbf_captcha_hp_salt'];
            }
            return 'bbf_timing_fallback_key';
        }
        return $key;
    }

    /**
     * Timing-Token generieren (verschlüsselter Timestamp)
     */
    public function generateToken(): string
    {
        $timestamp = time();
        $hmacKey   = $this->getHmacKey();
        $payload   = (string)$timestamp;
        $signature = hash_hmac('sha256', $payload, $hmacKey);

        // Base64-encoded: timestamp.signature
        return base64_encode($payload . '.' . $signature);
    }

    /**
     * Hidden-Field HTML rendern
     */
    public function renderField(): string
    {
        $token = $this->generateToken();
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="'
             . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Timing-Token validieren
     *
     * @return array{valid: bool, reason: string, score: int}
     */
    public function validate(array $postData): array
    {
        $token = $postData[self::FIELD_NAME] ?? '';

        if (empty($token)) {
            return [
                'valid'  => false,
                'reason' => 'Timing-Token fehlt',
                'score'  => 50,
            ];
        }

        // Token decodieren
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return [
                'valid'  => false,
                'reason' => 'Timing-Token ungültig',
                'score'  => 80,
            ];
        }

        $parts = explode('.', $decoded, 2);
        if (count($parts) !== 2) {
            return [
                'valid'  => false,
                'reason' => 'Timing-Token Format ungültig',
                'score'  => 80,
            ];
        }

        [$payload, $signature] = $parts;
        $timestamp = (int)$payload;

        // HMAC verifizieren
        $hmacKey           = $this->getHmacKey();
        $expectedSignature = hash_hmac('sha256', $payload, $hmacKey);

        if (!hash_equals($expectedSignature, $signature)) {
            return [
                'valid'  => false,
                'reason' => 'Timing-Token Signatur ungültig (manipuliert)',
                'score'  => 100,
            ];
        }

        // Zeit prüfen
        $now        = time();
        $elapsed    = $now - $timestamp;
        $minSeconds = $this->settings->getInt('timing_min_seconds', 3);
        $maxSeconds = $this->settings->getInt('timing_max_seconds', 3600);

        if ($elapsed < $minSeconds) {
            return [
                'valid'  => false,
                'reason' => 'Formular zu schnell abgesendet (' . $elapsed . 's < ' . $minSeconds . 's)',
                'score'  => 70 + min(30, ($minSeconds - $elapsed) * 10),
            ];
        }

        if ($elapsed > $maxSeconds) {
            return [
                'valid'  => false,
                'reason' => 'Formular-Session abgelaufen (' . $elapsed . 's > ' . $maxSeconds . 's)',
                'score'  => 20, // Niedriger Score, könnte auch ein langsamer Nutzer sein
            ];
        }

        return [
            'valid'  => true,
            'reason' => '',
            'score'  => 0,
        ];
    }

    /**
     * Feldname für externe Nutzung
     */
    public static function getFieldName(): string
    {
        return self::FIELD_NAME;
    }
}
