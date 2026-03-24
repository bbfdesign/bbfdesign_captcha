<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Honeypot-Schutz: Dynamische Feldnamen, Off-Screen-Positionierung
 *
 * - DYNAMISCHE Feldnamen (Session-Salt-basiert, nicht statisch!)
 * - Mehrere Honeypot-Felder pro Formular
 * - Felder sehen aus wie echte Felder (name="company", name="website", etc.)
 * - Verstecken via Off-Screen-Positionierung (KEIN display:none!)
 * - aria-hidden="true" + tabindex="-1" für Barrierefreiheit
 * - Ein Feld das LEER sein MUSS + ein Feld das einen vorgegebenen Wert haben MUSS
 */
class HoneypotService
{
    private Setting $settings;

    /** Basis-Feldnamen die wie echte Felder aussehen */
    private const FIELD_BASES = [
        'company', 'website', 'phone2', 'fax', 'title',
        'middle_name', 'suffix', 'homepage', 'organization',
        'department', 'position', 'url', 'skype', 'nickname',
    ];

    /** CSS-Klassen für Off-Screen-Verstecken (kein display:none!) */
    private const HIDE_STYLES = [
        'position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;',
        'position:absolute;left:-99999px;top:auto;width:0;height:0;opacity:0;overflow:hidden;pointer-events:none;',
        'position:fixed;left:-10000px;top:-10000px;width:1px;height:1px;opacity:0;pointer-events:none;',
    ];

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Session-Salt holen oder generieren (pro Session einmalig)
     */
    public function getSessionSalt(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 'fallback_salt_' . date('Ymd');
        }

        if (empty($_SESSION['bbf_captcha_hp_salt'])) {
            $_SESSION['bbf_captcha_hp_salt'] = bin2hex(random_bytes(8));
        }

        return $_SESSION['bbf_captcha_hp_salt'];
    }

    /**
     * Dynamischen Feldnamen generieren basierend auf Session-Salt
     */
    public function generateFieldName(string $baseName): string
    {
        $salt = $this->getSessionSalt();
        $hash = substr(hash('sha256', $salt . $baseName), 0, 6);
        return $baseName . '_' . $hash;
    }

    /**
     * Honeypot-HTML generieren für ein Formular
     */
    public function renderFields(string $formType = 'generic'): string
    {
        $fieldCount = $this->settings->getInt('honeypot_field_count', 3);
        $fieldCount = max(1, min(10, $fieldCount));
        $salt       = $this->getSessionSalt();

        // Felder auswählen (basierend auf Salt für Konsistenz pro Session)
        $selectedBases = [];
        $allBases      = self::FIELD_BASES;
        $hashInt       = abs(crc32($salt . $formType));

        for ($i = 0; $i < $fieldCount && $i < count($allBases); $i++) {
            $idx = ($hashInt + $i) % count($allBases);
            $selectedBases[] = $allBases[$idx];
        }

        $html = '';

        foreach ($selectedBases as $index => $baseName) {
            $fieldName = $this->generateFieldName($baseName);
            $styleIdx  = $index % count(self::HIDE_STYLES);
            $style     = self::HIDE_STYLES[$styleIdx];

            // Normales Honeypot-Feld: MUSS leer bleiben
            $html .= '<div style="' . $style . '" aria-hidden="true">';
            $html .= '<label for="' . htmlspecialchars($fieldName) . '">'
                    . ucfirst($baseName) . '</label>';
            $html .= '<input type="text"'
                    . ' name="' . htmlspecialchars($fieldName) . '"'
                    . ' id="' . htmlspecialchars($fieldName) . '"'
                    . ' value=""'
                    . ' tabindex="-1"'
                    . ' autocomplete="off"'
                    . ' aria-hidden="true"'
                    . '>';
            $html .= '</div>';
        }

        // Spezial-Feld: MUSS einen bestimmten Wert haben (CSS-Default)
        $checkFieldName  = $this->generateFieldName('confirm_human');
        $checkFieldValue = substr(hash('sha256', $salt . 'check_value'), 0, 8);
        $html .= '<div style="' . self::HIDE_STYLES[0] . '" aria-hidden="true">';
        $html .= '<input type="text"'
                . ' name="' . htmlspecialchars($checkFieldName) . '"'
                . ' value="' . htmlspecialchars($checkFieldValue) . '"'
                . ' tabindex="-1"'
                . ' autocomplete="off"'
                . ' aria-hidden="true"'
                . ' readonly'
                . '>';
        $html .= '</div>';

        // Timestamp-Feld für das Honeypot-System (nicht Timing-Service!)
        $tsFieldName = $this->generateFieldName('hp_ts');
        $html .= '<input type="hidden"'
                . ' name="' . htmlspecialchars($tsFieldName) . '"'
                . ' value="' . time() . '"'
                . '>';

        return $html;
    }

    /**
     * Honeypot-Felder validieren
     *
     * @return array{valid: bool, reason: string, score: int}
     */
    public function validate(array $postData, string $formType = 'generic'): array
    {
        $fieldCount    = $this->settings->getInt('honeypot_field_count', 3);
        $fieldCount    = max(1, min(10, $fieldCount));
        $salt          = $this->getSessionSalt();
        $allBases      = self::FIELD_BASES;
        $hashInt       = abs(crc32($salt . $formType));
        $score         = 0;
        $reasons       = [];

        // Prüfe ob Honeypot-Felder ausgefüllt wurden (sollten leer sein)
        for ($i = 0; $i < $fieldCount && $i < count($allBases); $i++) {
            $idx       = ($hashInt + $i) % count($allBases);
            $baseName  = $allBases[$idx];
            $fieldName = $this->generateFieldName($baseName);

            if (isset($postData[$fieldName]) && $postData[$fieldName] !== '') {
                $score += 100; // Definitiv Bot
                $reasons[] = 'Honeypot-Feld "' . $baseName . '" ausgefüllt';
            }
        }

        // Prüfe Check-Feld (muss den erwarteten Wert haben)
        $checkFieldName  = $this->generateFieldName('confirm_human');
        $expectedValue   = substr(hash('sha256', $salt . 'check_value'), 0, 8);

        if (isset($postData[$checkFieldName])) {
            if ($postData[$checkFieldName] !== $expectedValue) {
                $score += 80;
                $reasons[] = 'Honeypot-Check-Feld manipuliert';
            }
        }
        // Wenn das Feld komplett fehlt, ist das auch verdächtig
        // (aber weniger sicher, da manche Formulare Felder filtern)

        $valid = $score === 0;

        return [
            'valid'  => $valid,
            'reason' => $valid ? '' : implode('; ', $reasons),
            'score'  => $score,
        ];
    }

    /**
     * Honeypot-Felder per Output-Filter in HTML-Formulare injizieren
     */
    public function injectIntoForms(string $html): string
    {
        if (!$this->settings->getBool('honeypot_inject_all_forms')) {
            return $html;
        }

        // Finde alle <form> Tags und füge Honeypot-Felder nach dem öffnenden Tag ein
        $pattern = '/(<form\b[^>]*>)/i';

        return preg_replace_callback($pattern, function (array $matches) {
            static $formIndex = 0;
            $formIndex++;
            $honeypotHtml = $this->renderFields('auto_' . $formIndex);
            return $matches[1] . "\n" . $honeypotHtml;
        }, $html);
    }

    /**
     * Alle Honeypot-Feldnamen für ein Formular zurückgeben (für Cleanup)
     */
    public function getFieldNames(string $formType = 'generic'): array
    {
        $fieldCount = $this->settings->getInt('honeypot_field_count', 3);
        $allBases   = self::FIELD_BASES;
        $hashInt    = abs(crc32($this->getSessionSalt() . $formType));
        $names      = [];

        for ($i = 0; $i < $fieldCount && $i < count($allBases); $i++) {
            $idx     = ($hashInt + $i) % count($allBases);
            $names[] = $this->generateFieldName($allBases[$idx]);
        }

        $names[] = $this->generateFieldName('confirm_human');
        $names[] = $this->generateFieldName('hp_ts');

        return $names;
    }
}
