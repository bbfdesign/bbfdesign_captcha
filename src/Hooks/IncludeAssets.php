<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Hooks;

use JTL\Plugin\PluginInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Frontend JS/CSS einbinden
 *
 * PERFORMANCE: Nur laden wenn tatsächlich ein Formular auf der Seite ist!
 * Nicht pauschal auf jeder Seite.
 */
class IncludeAssets
{
    private PluginInterface $plugin;
    private Setting $settings;

    public function __construct(PluginInterface $plugin, Setting $settings)
    {
        $this->plugin   = $plugin;
        $this->settings = $settings;
    }

    /**
     * Frontend-Assets einbinden
     *
     * Wird im HOOK_SMARTY_OUTPUTFILTER aufgerufen.
     * Prüft ob die Seite ein Formular enthält und fügt nur dann Assets hinzu.
     */
    public function includeIfNeeded(string $html): string
    {
        if (!$this->settings->getBool('global_enabled')) {
            return $html;
        }

        // Prüfe ob die Seite ein <form> enthält
        if (stripos($html, '<form') === false) {
            return $html;
        }

        $frontendUrl = $this->plugin->getPaths()->getFrontendURL();
        $assets      = '';

        // CSS (minimal, immer laden wenn Formular vorhanden)
        $assets .= '<link rel="stylesheet" href="'
                 . htmlspecialchars($frontendUrl . 'css/bbfdesign-captcha.css', ENT_QUOTES, 'UTF-8')
                 . '" media="all">' . "\n";

        // Custom CSS (aus Admin-Einstellungen)
        $customCss = $this->settings->get('custom_css');
        if (!empty(trim($customCss))) {
            $assets .= '<style>' . strip_tags($customCss) . '</style>' . "\n";
        }

        // JS (async/defer, blockiert nicht!)
        $assets .= '<script src="'
                 . htmlspecialchars($frontendUrl . 'js/bbfdesign-captcha.js', ENT_QUOTES, 'UTF-8')
                 . '" async defer></script>' . "\n";

        // ALTCHA Widget JS (self-hosted → kein Consent nötig)
        if ($this->settings->getBool('altcha_enabled')) {
            $altchaJs = $frontendUrl . 'js/vendor/altcha.min.js';
            $assets  .= '<script src="'
                      . htmlspecialchars($altchaJs, ENT_QUOTES, 'UTF-8')
                      . '" async defer></script>' . "\n";
        }

        // Externe Captcha-Scripts: NUR bei Consent laden!
        // Die Scripts werden per JS nachgeladen wenn der Consent erteilt wird.
        // Hier fügen wir nur die Consent-Konfig als data-Attribute ein.
        $consentConfig = [];
        if ($this->settings->getBool('turnstile_enabled') && !empty($this->settings->get('turnstile_site_key'))) {
            $consentConfig['turnstile'] = [
                'consent' => 'bbfdesign_captcha_turnstile',
                'script'  => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
            ];
        }
        if ($this->settings->getBool('recaptcha_enabled') && !empty($this->settings->get('recaptcha_site_key'))) {
            $version = $this->settings->get('recaptcha_version', 'v3');
            $siteKey = $this->settings->get('recaptcha_site_key');
            $consentConfig['recaptcha'] = [
                'consent' => 'bbfdesign_captcha_recaptcha',
                'script'  => $version === 'v3'
                    ? 'https://www.google.com/recaptcha/api.js?render=' . $siteKey
                    : 'https://www.google.com/recaptcha/api.js?hl=de',
            ];
        }
        if ($this->settings->getBool('hcaptcha_enabled') && !empty($this->settings->get('hcaptcha_site_key'))) {
            $consentConfig['hcaptcha'] = [
                'consent' => 'bbfdesign_captcha_hcaptcha',
                'script'  => 'https://js.hcaptcha.com/1/api.js?hl=de',
            ];
        }
        if ($this->settings->getBool('friendly_captcha_enabled') && !empty($this->settings->get('friendly_captcha_site_key'))) {
            $consentConfig['friendly_captcha'] = [
                'consent' => 'bbfdesign_captcha_friendly_captcha',
                'script'  => 'https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.14/widget.module.min.js',
            ];
        }

        if (!empty($consentConfig)) {
            $assets .= '<script>window.bbfCaptchaConsent=' . json_encode($consentConfig, JSON_HEX_TAG) . ';</script>' . "\n";
        }

        // Assets vor </head> einfügen
        $pos = strripos($html, '</head>');
        if ($pos !== false) {
            $html = substr($html, 0, $pos) . $assets . substr($html, $pos);
        } else {
            // Fallback: An den Anfang des <body>
            $html = $assets . $html;
        }

        return $html;
    }
}
