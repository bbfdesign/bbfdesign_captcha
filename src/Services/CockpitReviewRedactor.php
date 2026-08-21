<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

/**
 * Erstellt kurze, redigierte Cockpit-Review-Vorschauen aus lokal gespeicherten
 * Request-Daten. Der Standard-Ingest nutzt diese Klasse nicht; sie greift nur
 * hinter dem expliziten Setting `cockpit_review_enabled`.
 */
class CockpitReviewRedactor
{
    public const VERSION = 'pii-redaction-v1';
    private const DEFAULT_MAX_CHARS = 180;
    private const IGNORE_KEY_PARTS = [
        'address',
        'adresse',
        'anschrift',
        'billing',
        'city',
        'company',
        'customer',
        'email',
        'e-mail',
        'firma',
        'firstname',
        'fullname',
        'hausnummer',
        'jtl_hp',
        'jtl_token',
        'kundennummer',
        'lastname',
        'mail',
        'name',
        'nachname',
        'pass',
        'password',
        'passwort',
        'phone',
        'plz',
        'postcode',
        'street',
        'strasse',
        'straße',
        'tel',
        'telefon',
        'token',
        'vorname',
        'zip',
    ];

    public function snippetFromRequestDataJson(string $requestDataJson, int $maxChars = self::DEFAULT_MAX_CHARS): ?string
    {
        $payload = $this->reviewPayloadFromRequestDataJson($requestDataJson, $maxChars);
        return is_array($payload) ? (string)$payload['snippet'] : null;
    }

    /**
     * @return array{snippet:string,meta:array<string,mixed>}|null
     */
    public function reviewPayloadFromRequestDataJson(string $requestDataJson, int $maxChars = self::DEFAULT_MAX_CHARS): ?array
    {
        $data = json_decode($requestDataJson, true);
        if (!is_array($data)) {
            return null;
        }

        $texts      = [];
        $fields     = [];
        $piiRemoved = [];
        $this->collectTexts($data, $texts, $fields, $piiRemoved);

        $combined = $this->normalize(implode(' ', $texts));
        if ($combined === '') {
            return null;
        }

        $redacted = $this->redactText($combined);
        if ($redacted === '') {
            return null;
        }

        $maxChars = max(40, min(300, $maxChars));
        $snippet  = $this->truncate($redacted, $maxChars);

        return [
            'snippet' => $snippet,
            'meta'    => [
                'redacted'         => true,
                'source'           => 'plugin',
                'redactionVersion' => self::VERSION,
                'fields'           => array_values(array_unique($fields)),
                'piiRemoved'       => array_values(array_unique(array_merge($piiRemoved, $this->markersIn($snippet)))),
                'maxChars'         => $maxChars,
            ],
        ];
    }

    /**
     * @param array<mixed> $data
     * @param array<int,string> $texts
     * @param array<int,string> $fields
     * @param array<int,string> $piiRemoved
     */
    private function collectTexts(array $data, array &$texts, array &$fields, array &$piiRemoved): void
    {
        foreach ($data as $key => $value) {
            $key = (string)$key;
            if ($this->isIgnoredKey($key)) {
                $piiRemoved[] = $this->categoryForKey($key);
                continue;
            }

            if (is_array($value)) {
                $this->collectTexts($value, $texts, $fields, $piiRemoved);
                continue;
            }

            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }

            $text = $this->normalize((string)$value);
            if ($text === '' || $text === '***REDACTED***') {
                continue;
            }

            $texts[] = $text;
            $fields[] = $this->safeFieldName($key);
        }
    }

    private function isIgnoredKey(string $key): bool
    {
        $key = mb_strtolower($key, 'UTF-8');
        foreach (self::IGNORE_KEY_PARTS as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }
        return false;
    }

    private function categoryForKey(string $key): string
    {
        $key = mb_strtolower($key, 'UTF-8');
        if (preg_match('/mail|e-mail|email/u', $key)) {
            return 'email';
        }
        if (preg_match('/tel|telefon|phone/u', $key)) {
            return 'phone';
        }
        if (preg_match('/adresse|address|anschrift|street|strasse|straße|hausnummer|plz|zip|postcode|city/u', $key)) {
            return 'address';
        }
        if (preg_match('/firma|company/u', $key)) {
            return 'company';
        }
        if (preg_match('/name|vorname|nachname|firstname|lastname|fullname/u', $key)) {
            return 'name';
        }
        if (preg_match('/pass|password|passwort/u', $key)) {
            return 'password';
        }
        if (preg_match('/token|jtl_hp|jtl_token/u', $key)) {
            return 'token';
        }
        if (preg_match('/bestell|kundennummer|customer|order/u', $key)) {
            return 'orderReference';
        }
        return 'sensitiveField';
    }

    private function safeFieldName(string $key): string
    {
        $key = mb_strtolower($key, 'UTF-8');
        $safe = preg_replace('/[^a-z0-9_:-]+/u', '_', $key);
        $safe = trim(is_string($safe) ? $safe : 'field', '_:-');
        if ($safe === '') {
            return 'field';
        }
        return mb_substr($safe, 0, 40, 'UTF-8');
    }

    public function redactText(string $text): string
    {
        $text = $this->normalize($text);
        $patterns = [
            '/https?:\/\/[^\s]+/iu' => '[URL]',
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu' => '[E-Mail]',
            '/\b(?:bestell(?:ung)?|order|auftrag|rechnung|ticket|kundennummer|kunden-nr)\s*[:#-]?\s*[A-Z0-9][A-Z0-9\-]{3,}\b/iu' => '[Referenz]',
            '/\b[A-Z]{1,5}-?\d{5,}\b/u' => '[Referenz]',
            '/(?:\+?\d[\d\s().\/-]{6,}\d)/u' => '[Telefon]',
            '/\b(?:adresse|anschrift)\s*:\s*[^,.;]{3,80}/iu' => '[Adresse]',
            '/\b[\p{L}][\p{L}\-]*(?:str\.?|strasse|straße|weg|allee|platz|gasse|ring)\s*\d*\w?\b/iu' => '[Adresse]',
            '/\b\d{5}\b(?:\s+[\p{L}][\p{L}\s.\-]{2,})?/u' => '[PLZ/Ort]',
            '/\b[\p{L}][\p{L}\s.\-]{2,}\s+(?:str\.?|strasse|straße|weg|allee|platz|gasse|ring)\s*\d*\w?\b/iu' => '[Adresse]',
            '/\b(?:ich heisse|ich heiße|mein name ist|name ist)\s+[\p{L}][\p{L}\s.\'-]{1,60}/iu' => '[Name]',
            '/\b(?:mfg|mit freundlichen gruessen|mit freundlichen grüßen|gruss|gruß|beste gruesse|beste grüße)[\s,]+[\p{L}][\p{L}\s.\'-]{1,60}$/iu' => '[Signatur]',
            '/\b[A-F0-9]{24,}\b/iu' => '[Token]',
            '/\b[A-Za-z0-9+\/=_-]{32,}\b/u' => '[Token]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $next = preg_replace($pattern, $replacement, $text);
            if (is_string($next)) {
                $text = $next;
            }
        }
        $spaced = preg_replace('/\](?=\[)/u', '] ', $text);
        if (is_string($spaced)) {
            $text = $spaced;
        }

        return trim($this->normalize($text), " \t\n\r\0\x0B,.;:-");
    }

    /**
     * @return array<int,string>
     */
    private function markersIn(string $text): array
    {
        $map = [
            '[Adresse]'  => 'address',
            '[E-Mail]'   => 'email',
            '[Name]'     => 'name',
            '[PLZ/Ort]'  => 'address',
            '[Referenz]' => 'orderReference',
            '[Signatur]' => 'name',
            '[Telefon]'  => 'phone',
            '[Token]'    => 'token',
            '[URL]'      => 'url',
        ];
        $found = [];
        foreach ($map as $marker => $category) {
            if (str_contains($text, $marker)) {
                $found[] = $category;
            }
        }
        return $found;
    }

    private function normalize(string $text): string
    {
        $normalized = preg_replace('/\s+/u', ' ', strip_tags($text));
        return trim(is_string($normalized) ? $normalized : $text);
    }

    private function truncate(string $text, int $maxChars): string
    {
        if (mb_strlen($text, 'UTF-8') <= $maxChars) {
            return $text;
        }

        $cut = rtrim(mb_substr($text, 0, max(0, $maxChars - 3), 'UTF-8'));
        return $cut . '...';
    }
}
