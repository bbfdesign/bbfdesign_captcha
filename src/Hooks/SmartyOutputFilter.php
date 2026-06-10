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
     * Honeypot + Timing in ein phpQuery-Dokument injizieren (JTL 5.6/5.7).
     *
     * Ab JTL 5.6 übergibt HOOK_SMARTY_OUTPUTFILTER ein phpQuery-Dokument statt
     * eines HTML-Strings. Die Felder werden hier per Selektor in jedes <form>
     * eingehängt (als erste Kinder, entspricht dem String-Verhalten).
     */
    public function filterDocument(object $doc): void
    {
        if (!$this->settings->getBool('global_enabled')) {
            return;
        }

        $honeypotOn = $this->settings->getBool('honeypot_enabled')
            && $this->settings->getBool('honeypot_inject_all_forms');
        $timingOn   = $this->settings->getBool('timing_enabled');
        if (!$honeypotOn && !$timingOn) {
            return;
        }

        $forms = $doc->find('form');
        $count = $forms->count();
        for ($i = 0; $i < $count; $i++) {
            $form   = $forms->eq($i);
            $fields = '';

            if ($timingOn && strpos($form->htmlOuter(), TimingService::getFieldName()) === false) {
                $fields .= $this->timing->renderField();
            }
            if ($honeypotOn) {
                $fields .= $this->honeypot->renderFields('auto_' . ($i + 1));
            }

            if ($fields !== '') {
                $form->prepend($fields);
            }
        }
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
