<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\Plugin\PluginInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * JTL Consent Manager Integration
 *
 * Registriert Consent-Items für externe Captcha-Dienste.
 * NUR registrieren wenn der jeweilige Dienst aktiviert ist.
 *
 * KEIN Consent für: Honeypot, Timing, ALTCHA (self-hosted), IP-Schutz, KI-Filter
 */
class ConsentService
{
    private PluginInterface $plugin;
    private Setting $settings;

    /** Consent-Item Definitionen */
    private const CONSENT_ITEMS = [
        'recaptcha' => [
            'setting_key' => 'recaptcha_enabled',
            'item_id'     => 'bbfdesign_captcha_recaptcha',
            'name_de'     => 'Google reCAPTCHA',
            'name_en'     => 'Google reCAPTCHA',
            'desc_de'     => 'Schützt Formulare vor Spam und Bots. Es werden Daten an Google übertragen.',
            'desc_en'     => 'Protects forms from spam and bots. Data is transmitted to Google.',
            'purpose_de'  => 'Spam-Schutz',
            'purpose_en'  => 'Spam protection',
            'provider'    => 'Google LLC',
            'privacy_url' => 'https://policies.google.com/privacy',
        ],
        'turnstile' => [
            'setting_key' => 'turnstile_enabled',
            'item_id'     => 'bbfdesign_captcha_turnstile',
            'name_de'     => 'Cloudflare Turnstile',
            'name_en'     => 'Cloudflare Turnstile',
            'desc_de'     => 'Schützt Formulare vor Spam und Bots. Es werden Daten an Cloudflare übertragen.',
            'desc_en'     => 'Protects forms from spam and bots. Data is transmitted to Cloudflare.',
            'purpose_de'  => 'Spam-Schutz',
            'purpose_en'  => 'Spam protection',
            'provider'    => 'Cloudflare Inc.',
            'privacy_url' => 'https://www.cloudflare.com/privacypolicy/',
        ],
        'hcaptcha' => [
            'setting_key' => 'hcaptcha_enabled',
            'item_id'     => 'bbfdesign_captcha_hcaptcha',
            'name_de'     => 'hCaptcha',
            'name_en'     => 'hCaptcha',
            'desc_de'     => 'Schützt Formulare vor Spam und Bots. Es werden Daten an hCaptcha übertragen.',
            'desc_en'     => 'Protects forms from spam and bots. Data is transmitted to hCaptcha.',
            'purpose_de'  => 'Spam-Schutz',
            'purpose_en'  => 'Spam protection',
            'provider'    => 'Intuition Machines Inc.',
            'privacy_url' => 'https://www.hcaptcha.com/privacy',
        ],
        'friendly_captcha' => [
            'setting_key' => 'friendly_captcha_enabled',
            'item_id'     => 'bbfdesign_captcha_friendly_captcha',
            'name_de'     => 'Friendly Captcha',
            'name_en'     => 'Friendly Captcha',
            'desc_de'     => 'Schützt Formulare vor Spam und Bots. Europäischer Anbieter mit Proof-of-Work Technologie.',
            'desc_en'     => 'Protects forms from spam and bots. European provider using Proof-of-Work technology.',
            'purpose_de'  => 'Spam-Schutz',
            'purpose_en'  => 'Spam protection',
            'provider'    => 'Friendly Captcha GmbH',
            'privacy_url' => 'https://friendlycaptcha.com/legal/privacy-end-users/',
        ],
    ];

    public function __construct(PluginInterface $plugin, Setting $settings)
    {
        $this->plugin   = $plugin;
        $this->settings = $settings;
    }

    /**
     * Consent-Items für den JTL Consent Manager registrieren
     *
     * Wird im CONSENT_MANAGER_GET_ACTIVE_ITEMS Hook aufgerufen.
     */
    public function registerConsentItems(array $args): void
    {
        if (!isset($args['items']) || !is_array($args['items'])) {
            return;
        }

        foreach (self::CONSENT_ITEMS as $key => $definition) {
            if (!$this->settings->getBool($definition['setting_key'])) {
                continue;
            }

            $args['items'][] = $this->createConsentItem($definition);
        }
    }

    /**
     * Consent-Item erstellen (JTL-Format)
     */
    private function createConsentItem(array $definition): object
    {
        $item = new \stdClass();
        $item->pluginID    = $this->plugin->getPluginID();
        $item->itemID      = $definition['item_id'];
        $item->company     = $definition['provider'];
        $item->privacyPolicy = $definition['privacy_url'];
        $item->purpose     = $definition['purpose_de'];

        $item->name = new \stdClass();
        $item->name->ger = $definition['name_de'];
        $item->name->eng = $definition['name_en'];

        $item->description = new \stdClass();
        $item->description->ger = $definition['desc_de'];
        $item->description->eng = $definition['desc_en'];

        return $item;
    }

    /**
     * Prüfe ob Consent für einen Dienst vorhanden ist
     *
     * Wird im Frontend geprüft bevor externe Scripts geladen werden.
     */
    public static function hasConsent(string $serviceKey): bool
    {
        $itemId = self::CONSENT_ITEMS[$serviceKey]['item_id'] ?? null;
        if ($itemId === null) {
            return false;
        }

        // JTL Consent Manager API prüfen
        if (class_exists('\JTL\Consent\Manager')) {
            try {
                $manager = \JTL\Shop::Container()->get(\JTL\Consent\ManagerInterface::class);
                if ($manager !== null) {
                    return $manager->hasConsent($itemId);
                }
            } catch (\Throwable $e) {
                // Consent Manager nicht verfügbar
            }
        }

        // Fallback: Cookie-basierte Prüfung
        $consentCookie = $_COOKIE['consent'] ?? '';
        if (!empty($consentCookie)) {
            $consented = json_decode($consentCookie, true);
            if (is_array($consented) && in_array($itemId, $consented, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consent-freie Methoden (Fallback)
     *
     * Diese Methoden brauchen KEINEN Consent und können IMMER genutzt werden.
     */
    public static function getConsentFreeMethods(): array
    {
        return ['honeypot', 'timing', 'altcha', 'ai_filter'];
    }

    /**
     * Prüfe ob eine Methode Consent benötigt
     */
    public static function requiresConsent(string $method): bool
    {
        return in_array($method, ['turnstile', 'recaptcha', 'hcaptcha', 'friendly_captcha'], true);
    }
}
