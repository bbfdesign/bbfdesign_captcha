<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\Plugin\PluginInterface;
use JTL\DB\DbInterface;
use JTL\Services\JTL\CaptchaServiceInterface;
use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Models\Setting;
use Smarty\Smarty;

/**
 * Adapter, der BBF Captcha als NATIVEN JTL-Captcha-Dienst registriert.
 *
 * JTL ruft an vielen Core-Stellen `Shop::Container()->getCaptchaService()` auf
 * (Kontakt, Widerruf, Registrierung, Passwort-vergessen u. a. – jeweils wenn das
 * passende Shop-Setting `*_abfragen_captcha` aktiv ist). Auch Fremd-Plugins, die
 * `Form::validateCaptcha()` nutzen, gehen über diesen Dienst.
 *
 * Indem das Bootstrap diesen Adapter in den Container bindet, übernimmt BBF
 * Captcha damit den nativen Captcha-Pfad shopweit – ein zentraler Punkt statt
 * vieler einzelner Form-Hooks.
 *
 * WICHTIG – fail-open (oberste Regel: echte Kunden nie aussperren):
 * - `validate()` blockt nur bei einem echten Spam-Signal; jeder Fehler führt zu
 *   "bestanden" (true), niemals zu einer Sperre.
 * - Eine fehlende ALTCHA-Lösung gilt nicht als Bot (siehe AltchaService).
 */
class JtlCaptchaAdapter implements CaptchaServiceInterface
{
    /** Formulartyp für Logging/Config im nativen Pfad. */
    private const FORM_TYPE = 'native';

    private PluginInterface $plugin;
    private DbInterface $db;
    private Setting $settings;
    private ?CaptchaService $captcha = null;

    public function __construct(PluginInterface $plugin, DbInterface $db, Setting $settings)
    {
        $this->plugin   = $plugin;
        $this->db       = $db;
        $this->settings = $settings;
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled();
    }

    public function isEnabled(): bool
    {
        return $this->settings->getBool('global_enabled')
            && $this->settings->getBool('native_captcha_integration');
    }

    public function getHeadMarkup(Smarty|\Smarty $smarty): string
    {
        // Frontend-Assets (inkl. altcha.min.js) werden bereits über IncludeAssets
        // injiziert; hier ist kein zusätzliches Head-Markup nötig.
        return '';
    }

    public function getBodyMarkup(Smarty|\Smarty $smarty): string
    {
        try {
            return $this->captcha()->getAltchaWidgetHtml(self::FORM_TYPE);
        } catch (\Throwable $e) {
            $this->logDebug('getBodyMarkup: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * @param array<string, mixed> $requestData
     */
    public function validate(array $requestData): bool
    {
        // Schutz global aus → nativen Captcha-Pfad nicht blockieren (durchlassen).
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            return $this->captcha()->validate($requestData, self::FORM_TYPE)->isValid();
        } catch (\Throwable $e) {
            // Fail-open: ein Fehler darf legitime Absendungen niemals blockieren.
            $this->logDebug('validate: ' . $e->getMessage());

            return true;
        }
    }

    private function captcha(): CaptchaService
    {
        if ($this->captcha === null) {
            $this->captcha = new CaptchaService($this->plugin, $this->db, $this->settings);
        }

        return $this->captcha;
    }

    private function logDebug(string $msg): void
    {
        if (!$this->settings->getBool('debug_mode')) {
            return;
        }
        try {
            Shop::Container()->getLogService()->warning('BBF Captcha native adapter: ' . $msg);
        } catch (\Throwable) {
            // Logging darf den Hotpath nie brechen.
        }
    }
}
