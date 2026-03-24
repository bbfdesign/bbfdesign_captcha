<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Hooks;

use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Services\HoneypotService;
use Plugin\bbfdesign_captcha\src\Services\TimingService;

/**
 * Smarty Output Filter Hook (HOOK_SMARTY_OUTPUTFILTER / Hook 140)
 *
 * Injiziert Honeypot-Felder und Timing-Token automatisch in alle <form> Tags.
 * Damit greifen die Schutzmechanismen auch bei Drittanbieter-Plugins.
 */
class SmartyOutputFilter
{
    private Setting $settings;
    private HoneypotService $honeypot;
    private TimingService $timing;

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
        $this->honeypot = new HoneypotService($settings);
        $this->timing   = new TimingService($settings);
    }

    /**
     * HTML-Output filtern: Honeypot + Timing in Formulare injizieren
     */
    public function filter(string $html): string
    {
        if (!$this->settings->getBool('global_enabled')) {
            return $html;
        }

        // Honeypot in alle Formulare injizieren (wenn aktiviert)
        if ($this->settings->getBool('honeypot_enabled') && $this->settings->getBool('honeypot_inject_all_forms')) {
            $html = $this->honeypot->injectIntoForms($html);
        }

        // Timing-Token in alle Formulare injizieren (wenn aktiviert)
        if ($this->settings->getBool('timing_enabled')) {
            $html = $this->injectTimingTokens($html);
        }

        return $html;
    }

    /**
     * Timing-Token in alle <form> Tags injizieren
     */
    private function injectTimingTokens(string $html): string
    {
        $timingField = $this->timing->renderField();

        $pattern = '/(<form\b[^>]*>)/i';

        return preg_replace_callback($pattern, function (array $matches) use ($timingField) {
            // Prüfe ob bereits ein Timing-Token vorhanden ist
            if (strpos($matches[0], TimingService::getFieldName()) !== false) {
                return $matches[0];
            }
            return $matches[1] . "\n" . $timingField;
        }, $html);
    }
}
