<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

/**
 * Resolves the user-facing CAPTCHA language for frontend widgets.
 */
class CaptchaLocaleService
{
    /** @var array<string, bool> */
    private const SUPPORTED_LANGUAGES = [
        'bg' => true,
        'cs' => true,
        'da' => true,
        'de' => true,
        'el' => true,
        'en' => true,
        'es' => true,
        'et' => true,
        'fi' => true,
        'fr' => true,
        'hr' => true,
        'hu' => true,
        'it' => true,
        'ja' => true,
        'ko' => true,
        'lt' => true,
        'lv' => true,
        'nl' => true,
        'no' => true,
        'pl' => true,
        'pt' => true,
        'ro' => true,
        'ru' => true,
        'sk' => true,
        'sl' => true,
        'sv' => true,
        'tr' => true,
        'uk' => true,
        'zh' => true,
    ];

    /**
     * Browser language wins, JTL frontend language is the fallback, German is the shop-safe default.
     */
    public static function currentLanguage(): string
    {
        $browserLanguage = self::fromAcceptLanguage((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if ($browserLanguage !== null) {
            return $browserLanguage;
        }

        $shopLanguage = self::fromJtlLanguage((string)($_SESSION['cISOSprache'] ?? ''));
        return $shopLanguage ?? 'de';
    }

    /**
     * JTL localization keys use GER/ENG style values in this plugin.
     */
    public static function currentJtlLanguageKey(): string
    {
        $language = self::currentLanguage();
        return $language === 'en' ? 'eng' : 'ger';
    }

    /**
     * ALTCHA 1.5.1 does not observe a language attribute, so we pass explicit strings.
     *
     * @return array<string, string>
     */
    public static function altchaStrings(): array
    {
        if (self::currentLanguage() === 'de') {
            return [
                'ariaLinkLabel' => 'Altcha.org besuchen',
                'error' => 'Verifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.',
                'expired' => 'Verifizierung abgelaufen. Bitte erneut versuchen.',
                'footer' => '',
                'label' => 'Ich bin kein Roboter',
                'verified' => 'Verifiziert',
                'verifying' => 'Verifiziere...',
                'waitAlert' => 'Verifizierung laeuft. Bitte warten.',
            ];
        }

        return [
            'ariaLinkLabel' => 'Visit Altcha.org',
            'error' => 'Verification failed. Please try again.',
            'expired' => 'Verification expired. Please try again.',
            'footer' => '',
            'label' => "I'm not a robot",
            'verified' => 'Verified',
            'verifying' => 'Verifying...',
            'waitAlert' => 'Verifying... please wait.',
        ];
    }

    private static function fromAcceptLanguage(string $header): ?string
    {
        if (trim($header) === '') {
            return null;
        }

        $candidates = [];
        foreach (explode(',', $header) as $index => $part) {
            $segments = array_map('trim', explode(';', $part));
            $language = self::normalizeLanguage($segments[0] ?? '');
            if ($language === null) {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($segments, 1) as $segment) {
                if (preg_match('/^q=([0-9.]+)$/i', $segment, $matches) === 1) {
                    $quality = max(0.0, min(1.0, (float)$matches[1]));
                    break;
                }
            }

            if ($quality > 0.0) {
                $candidates[] = [
                    'language' => $language,
                    'quality' => $quality,
                    'index' => $index,
                ];
            }
        }

        usort(
            $candidates,
            static fn(array $left, array $right): int =>
                $right['quality'] <=> $left['quality'] ?: $left['index'] <=> $right['index']
        );

        return $candidates[0]['language'] ?? null;
    }

    private static function fromJtlLanguage(string $language): ?string
    {
        return self::normalizeLanguage($language);
    }

    private static function normalizeLanguage(string $language): ?string
    {
        $language = strtolower(trim(str_replace('_', '-', $language)));
        if ($language === '') {
            return null;
        }

        $aliases = [
            'ger' => 'de',
            'deu' => 'de',
            'eng' => 'en',
        ];
        $language = $aliases[$language] ?? $language;

        $primary = explode('-', $language, 2)[0];
        return isset(self::SUPPORTED_LANGUAGES[$primary]) ? $primary : null;
    }
}
