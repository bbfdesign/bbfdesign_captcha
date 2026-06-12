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

    /**
     * Theme-unabhängige Widget-Platzierung: von den Formular-Hooks gefüllt
     * (formType => <altcha-widget>-HTML). Der OutputFilter injiziert es ins
     * passende Formular. Statisch, weil Formular-Hook und OutputFilter getrennte
     * Instanzen sind, aber denselben Request teilen.
     *
     * @var array<string,string>
     */
    public static array $pendingAltchaWidgets = [];

    /**
     * Signatur-Feld je Formulartyp zur sicheren Form-Zuordnung. NUR diese Typen
     * können das Widget bekommen – Checkout, Login, Suche etc. sind bewusst NICHT
     * enthalten, sodass deren Formulare niemals getroffen werden (Ticketverkauf-
     * Schutz). Zusätzlich muss ALTCHA für den Typ als Methode aktiv sein.
     */
    private const FORM_SIGNATURE = [
        'registration' => 'pass2',
        'contact'      => 'nachricht',
        'review'       => 'sterne',
    ];

    /** Darf für diesen Formulartyp überhaupt ein Widget injiziert werden? */
    public static function supportsWidgetInjection(string $formType): bool
    {
        return isset(self::FORM_SIGNATURE[$formType]);
    }

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

        // ALTCHA-Widget theme-unabhängig ins passende Formular injizieren.
        $html = $this->injectAltchaWidgetsString($html);

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

        if ($honeypotOn || $timingOn) {
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

        // ALTCHA-Widget theme-unabhängig ins passende Formular injizieren.
        $this->injectAltchaWidgetsDocument($doc);
    }

    /**
     * ALTCHA-Widget in das passende Formular eines phpQuery-Dokuments injizieren
     * (JTL 5.6/5.7). Trifft nur Formulare, deren Signatur-Feld passt UND für deren
     * Typ ein Widget vorgemerkt wurde – Checkout/Login werden nie erreicht.
     */
    private function injectAltchaWidgetsDocument(object $doc): void
    {
        if (empty(self::$pendingAltchaWidgets)) {
            return;
        }
        foreach (self::$pendingAltchaWidgets as $formType => $widgetHtml) {
            $sig = self::FORM_SIGNATURE[$formType] ?? '';
            if ($widgetHtml === '' || $sig === '') {
                continue;
            }
            $forms = $doc->find('form');
            $count = $forms->count();
            for ($i = 0; $i < $count; $i++) {
                $form  = $forms->eq($i);
                $outer = $form->htmlOuter();
                if (strpos($outer, 'name="' . $sig . '"') === false) {
                    continue; // nicht das Zielformular
                }
                if (strpos($outer, 'bbf-captcha-altcha') !== false) {
                    continue; // Widget bereits vorhanden (Theme rendert den Slot)
                }
                $submit = $form->find('button[type="submit"], input[type="submit"]');
                if ($submit->count() > 0) {
                    $submit->eq(0)->before($widgetHtml);
                } else {
                    $form->append($widgetHtml);
                }
                break; // nur das erste passende Formular bedienen
            }
        }
    }

    /**
     * String-Variante (ältere JTL/Output-Pfade). Bearbeitet jeden <form>-Block
     * einzeln und injiziert das Widget vor dem Submit-Button des Zielformulars.
     */
    private function injectAltchaWidgetsString(string $html): string
    {
        if (empty(self::$pendingAltchaWidgets)) {
            return $html;
        }
        foreach (self::$pendingAltchaWidgets as $formType => $widgetHtml) {
            $sig = self::FORM_SIGNATURE[$formType] ?? '';
            if ($widgetHtml === '' || $sig === '') {
                continue;
            }
            $result = preg_replace_callback('#<form\b[^>]*>.*?</form>#is', static function (array $m) use ($sig, $widgetHtml) {
                $block = $m[0];
                if (strpos($block, 'name="' . $sig . '"') === false
                    || strpos($block, 'bbf-captcha-altcha') !== false
                ) {
                    return $block;
                }
                if (preg_match('#<(?:button|input)\b[^>]*type=["\']submit["\'][^>]*>#i', $block, $sm, PREG_OFFSET_CAPTURE)) {
                    $pos = (int) $sm[0][1];
                    return substr($block, 0, $pos) . $widgetHtml . substr($block, $pos);
                }
                return preg_replace('#</form>#i', $widgetHtml . '</form>', $block, 1) ?? $block;
            }, $html);
            if (is_string($result)) {
                $html = $result;
            }
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
