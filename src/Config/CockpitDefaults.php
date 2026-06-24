<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Config;

/**
 * Zentrale Defaults für die selbsttätige Anbindung an die BBF-Dienste (Cockpit-
 * Self-Enrollment + ForgePush-Auto-Lizenzierung).
 *
 * WICHTIG – KEINE Secrets im Repo (das Plugin liegt auch auf Kundenshops).
 * Geteilte Schlüssel kommen – exakt wie im ForgePush-Drop-in – als PHP-KONSTANTE
 * aus der Server-/Shop-Konfiguration (außerhalb des Plugin-Codes), z. B. in
 * `includes/config.JTL-Shop.ini.php` oder per Deploy-Tooling gesetzt:
 *
 *   define('BBFCAPTCHA_ENROLLMENT_SECRET', '…');  // = cockpit-seitiges ENROLLMENT_SECRET
 *   define('FORGEPUSH_SIGNING_SECRET',     '…');  // = bbfcaptcha-Produktsecret
 *
 * Auflösungsreihenfolge je Wert: Setting (Backend) → PHP-Konstante → leer.
 * Ist nichts gesetzt, bleibt die jeweilige Automatik schlicht AUS (fail-open).
 */
final class CockpitDefaults
{
    /** Standard-Endpoint des CaptchaCockpit (nicht geheim). */
    public const ENDPOINT = 'https://captchacockpit.bbfdesign.de';

    /** Produkt-Slug in ForgePush (nicht geheim). */
    public const FORGEPUSH_PRODUCT_SLUG = 'bbfcaptcha';

    /** Name der PHP-Konstante mit dem geteilten Enrollment-Key. */
    public const ENROLLMENT_SECRET_CONST = 'BBFCAPTCHA_ENROLLMENT_SECRET';

    /** Name der PHP-Konstante mit dem ForgePush-Signing-Secret. */
    public const FORGEPUSH_SECRET_CONST = 'FORGEPUSH_SIGNING_SECRET';

    /** Geteilter Enrollment-Key aus der Server-Konstante (oder ''). */
    public static function enrollmentSecretFromConstant(): string
    {
        $c = self::ENROLLMENT_SECRET_CONST;
        return (defined($c) && is_string(constant($c))) ? (string)constant($c) : '';
    }

    /** ForgePush-Signing-Secret aus der Server-Konstante (oder ''). */
    public static function forgepushSecretFromConstant(): string
    {
        $c = self::FORGEPUSH_SECRET_CONST;
        return (defined($c) && is_string(constant($c))) ? (string)constant($c) : '';
    }
}
