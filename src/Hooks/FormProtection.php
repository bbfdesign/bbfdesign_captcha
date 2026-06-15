<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Hooks;

use JTL\DB\DbInterface;
use JTL\Plugin\PluginInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Services\CaptchaService;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;

/**
 * FormProtection: Hooks für alle JTL-Shop Formulare
 *
 * Validiert bei POST-Requests die aktiven Schutzmethoden
 * und blockiert/loggt Spam-Versuche.
 */
class FormProtection
{
    private PluginInterface $plugin;
    private DbInterface $db;
    private Setting $settings;
    private CaptchaService $captcha;

    public function __construct(PluginInterface $plugin, DbInterface $db, Setting $settings)
    {
        $this->plugin   = $plugin;
        $this->db       = $db;
        $this->settings = $settings;
        $this->captcha  = new CaptchaService($plugin, $db, $settings);
    }

    /**
     * Formular-Hook verarbeiten
     *
     * Wird von Bootstrap für jeden Formular-Hook aufgerufen.
     * Prüft ob ein POST-Request vorliegt und validiert ihn.
     */
    public function handleFormHook(string $formType, array $args): void
    {
        if (!$this->settings->getBool('global_enabled')) {
            return;
        }

        // Widget-HTML für Templates bereitstellen (GET + POST)
        $this->assignWidget($formType, $args);

        // Theme-unabhängige ALTCHA-Widget-Platzierung vormerken: viele Themes
        // rendern den {$bbfCaptchaWidget}-Slot nicht (z. B. NOVA-Child), daher
        // injiziert der SmartyOutputFilter das <altcha-widget> direkt ins passende
        // Formular. NUR Typen mit ALTCHA als aktiver Methode (Registrierung/
        // Kontakt/Bewertung) – Checkout, Login und Suche werden per Design nie
        // erreicht (kein Signatur-Match, kein aktives ALTCHA).
        if (SmartyOutputFilter::supportsWidgetInjection($formType)) {
            $widget = $this->captcha->getAltchaWidgetHtml($formType);
            if ($widget !== '') {
                SmartyOutputFilter::$pendingAltchaWidgets[$formType] = $widget;
            }
        }

        // Registrierung UND Kontakt werden über ihren jeweiligen PLAUSI-Hook
        // validiert UND geblockt (Registrierung: …REGISTRIEREN_PLAUSI/nReturnValue;
        // Kontakt: HOOK_KONTAKT_PAGE_PLAUSI/jtl_hp_input). Nur dort lässt sich der
        // Vorgang wirklich verhindern – HOOK_REGISTRIEREN_PAGE/HOOK_KONTAKT_PAGE
        // feuern zu spät. Hier daher nur das Widget zuweisen, keine (doppelte,
        // wirkungslose) Validierung/Protokollierung.
        if ($formType === 'registration' || $formType === 'contact') {
            return;
        }

        // Nur bei POST-Requests validieren
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // AJAX-Requests ignorieren
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            return;
        }

        $result = $this->captcha->validate($_POST, $formType);

        if (!$result->isValid()) {
            // Spam erkannt – Fehlermeldung setzen
            $this->setFormError($formType, $args);
        }
    }

    /**
     * Captcha-Widget-HTML in Smarty verfügbar machen.
     *
     * Templates können {$bbfCaptchaWidget nofilter} einbinden.
     */
    private function assignWidget(string $formType, array $args): void
    {
        if (!isset($args['smarty']) || !($args['smarty'] instanceof \JTL\Smarty\JTLSmarty)) {
            return;
        }
        try {
            $html = $this->captcha->renderWidget($formType);
        } catch (\Throwable $e) {
            // Render-Fail-safe: Der serverseitige Schutz (Honeypot/Timing via
            // SmartyOutputFilter) und die POST-Validierung laufen unabhängig vom
            // sichtbaren Widget weiter – ein Render-Fehler öffnet das Formular nicht,
            // er macht das Widget nur unsichtbar. Fehler nur zur Diagnose loggen.
            $html = '';
            if ($this->settings->getBool('debug_mode')) {
                try {
                    \JTL\Shop::Container()->getLogService()->warning(
                        'BBF Captcha: Widget-Render fehlgeschlagen (' . $formType . '): ' . $e->getMessage()
                    );
                } catch (\Throwable $ignored) {
                    // Logging darf den Hotpath nie stören.
                }
            }
        }
        $args['smarty']->assign('bbfCaptchaWidget', $html);
    }

    /**
     * Fehlermeldung im Shop-Frontend setzen
     *
     * Nutzt das JTL-Standard-Alerting über Smarty-Variablen.
     */
    private function setFormError(string $formType, array $args): void
    {
        $langVars = $this->plugin->getLocalization();
        // Shop-Frontend-Sprache (cISOSprache setzt JTL auf Frontend-ISO, z.B. 'ger'/'eng')
        $shopLang = $_SESSION['cISOSprache'] ?? 'ger';
        $errorMsg = $langVars->getTranslation('captcha_failed', $shopLang)
                 ?: 'Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.';

        // JTL-Standard: Fehler über die globale Alertbox
        if (isset($args['smarty']) && $args['smarty'] instanceof \JTL\Smarty\JTLSmarty) {
            $args['smarty']->assign('cFehler', $errorMsg);
        }

        // Alternative: Über $_SESSION Fehlermeldung setzen (JTL-üblich)
        if (!isset($_SESSION['cFehler'])) {
            $_SESSION['cFehler'] = $errorMsg;
        }

        // Formular-spezifische Fehlerbehandlung
        switch ($formType) {
            case 'contact':
                // Kontaktformular: Absenden verhindern
                if (isset($args['nReturnValue'])) {
                    $args['nReturnValue'] = 0;
                }
                break;

            case 'registration':
                // Registrierung: Fehler setzen
                $_SESSION['Registrieren'] = false;
                break;

            case 'newsletter':
                // Newsletter: Fehler setzen
                if (isset($GLOBALS['cFehler'])) {
                    $GLOBALS['cFehler'] = $errorMsg;
                }
                break;

            case 'review':
                // Bewertung verhindern
                if (isset($args['nReturnValue'])) {
                    $args['nReturnValue'] = 0;
                }
                break;

            default:
                break;
        }
    }
}
