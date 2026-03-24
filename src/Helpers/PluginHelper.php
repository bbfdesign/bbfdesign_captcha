<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Helpers;

class PluginHelper
{
    /**
     * Client-IP-Adresse ermitteln (hinter Proxies etc.)
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * HMAC-Token generieren
     */
    public static function generateHmacToken(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    /**
     * HMAC-Token verifizieren
     */
    public static function verifyHmacToken(string $data, string $key, string $token): bool
    {
        $expected = hash_hmac('sha256', $data, $key);
        return hash_equals($expected, $token);
    }

    /**
     * Sensible Daten aus Request-Daten entfernen (für Logging)
     */
    public static function sanitizeRequestData(array $data): array
    {
        $sensitiveKeys = ['password', 'passwort', 'pass', 'pwd', 'credit_card', 'kreditkarte', 'cvv', 'jtl_token'];

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $sanitized[$key] = '***REDACTED***';
                    continue 2;
                }
            }
            if (is_string($value) && mb_strlen($value) > 500) {
                $sanitized[$key] = mb_substr($value, 0, 500) . '...[truncated]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * IP gegen CIDR-Range prüfen
     */
    public static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int)$mask;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong    = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong   = -1 << (32 - $mask);
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // IPv6 support
        $ipBin    = inet_pton($ip);
        $subBin   = inet_pton($subnet);
        if ($ipBin === false || $subBin === false) {
            return false;
        }

        $maskBin = str_repeat("\xff", (int)($mask / 8));
        if ($mask % 8 !== 0) {
            $maskBin .= chr(256 - (1 << (8 - ($mask % 8))));
        }
        $maskBin = str_pad($maskBin, strlen($ipBin), "\x00");

        return ($ipBin & $maskBin) === ($subBin & $maskBin);
    }
}
