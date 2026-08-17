<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

/**
 * Sanitizer for admin-managed frontend widget CSS.
 *
 * The custom CSS is emitted inside a frontend <style> tag. Keep the supported
 * surface intentionally small: only widget-scoped selectors, no external
 * resources, no at-rules and a conservative property allowlist.
 */
class CustomCssSanitizer
{
    private const MAX_LENGTH = 20000;

    private const ALLOWED_PROPERTIES = [
        'accent-color',
        'align-items',
        'appearance',
        'background',
        'background-color',
        'border',
        'border-color',
        'border-radius',
        'border-style',
        'border-width',
        'box-shadow',
        'box-sizing',
        'color',
        'display',
        'flex',
        'flex-direction',
        'flex-wrap',
        'font-family',
        'font-size',
        'font-style',
        'font-weight',
        'gap',
        'height',
        'justify-content',
        'letter-spacing',
        'line-height',
        'margin',
        'margin-bottom',
        'margin-left',
        'margin-right',
        'margin-top',
        'max-height',
        'max-width',
        'min-height',
        'min-width',
        'opacity',
        'outline',
        'outline-color',
        'outline-offset',
        'outline-style',
        'outline-width',
        'padding',
        'padding-bottom',
        'padding-left',
        'padding-right',
        'padding-top',
        'text-align',
        'text-decoration',
        'text-transform',
        'transition',
        'vertical-align',
        'visibility',
        'white-space',
        'width',
    ];

    public static function sanitize(string $css): string
    {
        $css = substr($css, 0, self::MAX_LENGTH);
        $css = str_replace("\0", '', $css);
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';
        $css = preg_replace('#</?\s*(?:style|script)\b[^>]*>#i', '', $css) ?? '';
        $css = str_replace(['<!--', '-->'], '', $css);

        $safeRules = [];
        if (!preg_match_all('/([^{}@]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER)) {
            return '';
        }

        foreach ($matches as $match) {
            $selector = self::sanitizeSelector(trim((string)$match[1]));
            if ($selector === '') {
                continue;
            }

            $declarations = self::sanitizeDeclarations((string)$match[2]);
            if ($declarations === '') {
                continue;
            }

            $safeRules[] = $selector . " {\n" . $declarations . "\n}";
        }

        return implode("\n\n", $safeRules);
    }

    private static function sanitizeSelector(string $selector): string
    {
        if ($selector === '' || strlen($selector) > 500) {
            return '';
        }
        if (!preg_match('/^[a-zA-Z0-9\s\.\#\:\,\[\]\(\)\=\"\'\|\~\^\$\*\+\>\-_\n\r]+$/', $selector)) {
            return '';
        }

        $parts = array_map('trim', explode(',', $selector));
        $safeParts = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $lower = strtolower($part);
            if (
                str_contains($lower, 'bbf-captcha')
                || str_contains($lower, 'altcha-widget')
                || str_contains($lower, '[data-bbf-captcha')
            ) {
                $safeParts[] = $part;
            }
        }

        return implode(', ', $safeParts);
    }

    private static function sanitizeDeclarations(string $block): string
    {
        $safe = [];
        foreach (explode(';', $block) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '' || !str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            if (!in_array($property, self::ALLOWED_PROPERTIES, true)) {
                continue;
            }

            if (!self::isSafeValue($value)) {
                continue;
            }

            $safe[] = '    ' . $property . ': ' . $value . ';';
        }

        return implode("\n", $safe);
    }

    private static function isSafeValue(string $value): bool
    {
        if ($value === '' || strlen($value) > 500) {
            return false;
        }

        $lower = strtolower($value);
        $blocked = ['url(', '@import', 'expression(', 'javascript:', 'data:', 'vbscript:', 'behavior:', '-moz-binding', '</', '<', '>', '\\'];
        foreach ($blocked as $needle) {
            if (str_contains($lower, $needle)) {
                return false;
            }
        }

        return (bool)preg_match('/^[a-zA-Z0-9\s\#\.\,\%\(\)\+\-\*\/\:_"\'!]+$/', $value);
    }
}
