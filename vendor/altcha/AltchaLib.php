<?php

declare(strict_types=1);

/**
 * ALTCHA PHP Library – Standalone Version für bbfdesign_captcha
 *
 * Basiert auf altcha-lib-php (https://github.com/altcha-org/altcha-lib-php)
 * Standalone-Version ohne Composer-Dependency.
 *
 * ALTCHA (Anti-spam Lightweight Tool for Content Hosting & Authentication)
 * ist ein Proof-of-Work basiertes Captcha-System.
 *
 * Funktionsweise:
 * 1. Server generiert eine Challenge (Salt + Ziel-Hash + Signatur)
 * 2. Client muss eine Zahl finden, die zusammen mit dem Salt den Hash ergibt
 * 3. Client sendet die gefundene Zahl (Solution) zurück
 * 4. Server verifiziert die Solution
 *
 * Sicherheit:
 * - Challenge ist HMAC-signiert → Manipulation erkennbar
 * - Proof-of-Work → CPU-Kosten für Bots
 * - Jede Challenge ist einmalig (Salt ist zufällig)
 */

namespace Altcha;

class Altcha
{
    /**
     * Erstelle eine neue Challenge
     *
     * @param string $hmacKey    HMAC-Schlüssel zur Signierung
     * @param int    $maxNumber  Maximale Zahl (höher = schwieriger)
     * @param string $algorithm  Hash-Algorithmus (SHA-256, SHA-384, SHA-512)
     * @return array Challenge-Daten für den Client
     */
    public static function createChallenge(
        string $hmacKey,
        int $maxNumber = 100000,
        string $algorithm = 'SHA-256'
    ): array {
        $salt      = bin2hex(random_bytes(12));
        $number    = random_int(0, max(1, $maxNumber));
        $algoPhp   = self::algorithmToPhp($algorithm);
        $challenge = hash($algoPhp, $salt . (string)$number);
        $signature = hash_hmac($algoPhp, $challenge, $hmacKey);

        return [
            'algorithm'  => $algorithm,
            'challenge'  => $challenge,
            'maxnumber'  => $maxNumber,
            'salt'       => $salt,
            'signature'  => $signature,
        ];
    }

    /**
     * Verifiziere eine Solution vom Client
     *
     * @param array  $payload  Solution-Daten vom Client
     * @param string $hmacKey  HMAC-Schlüssel
     * @param bool   $checkExpiry  Ob Ablaufzeit geprüft werden soll
     * @return bool  True wenn Solution gültig
     */
    public static function verifySolution(array $payload, string $hmacKey, bool $checkExpiry = false): bool
    {
        $algorithm = $payload['algorithm'] ?? '';
        $challenge = $payload['challenge'] ?? '';
        $number    = $payload['number'] ?? -1;
        $salt      = $payload['salt'] ?? '';
        $signature = $payload['signature'] ?? '';

        // Pflichtfelder prüfen
        if (empty($algorithm) || empty($challenge) || empty($salt) || empty($signature)) {
            return false;
        }

        // Unterstützte Algorithmen
        $algoPhp = self::algorithmToPhp($algorithm);
        if ($algoPhp === null) {
            return false;
        }

        // Number muss eine nicht-negative Ganzzahl sein
        $number = (int)$number;
        if ($number < 0) {
            return false;
        }

        // Challenge verifizieren: hash(salt + number) === challenge
        $expectedChallenge = hash($algoPhp, $salt . (string)$number);
        if (!hash_equals($expectedChallenge, $challenge)) {
            return false;
        }

        // Signatur verifizieren: hmac(challenge, key) === signature
        $expectedSignature = hash_hmac($algoPhp, $challenge, $hmacKey);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // Optional: Salt-Ablaufzeit prüfen (wenn Salt ein Timestamp-Prefix hat)
        if ($checkExpiry) {
            $parts = explode('?expires=', $salt, 2);
            if (count($parts) === 2) {
                $expires = (int)$parts[1];
                if ($expires > 0 && time() > $expires) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Verifiziere eine Base64-encoded Solution
     *
     * @param string $base64Payload  Base64-encoded JSON Solution
     * @param string $hmacKey        HMAC-Schlüssel
     * @return bool
     */
    public static function verifySolutionBase64(string $base64Payload, string $hmacKey): bool
    {
        $decoded = base64_decode($base64Payload, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            return false;
        }

        return self::verifySolution($payload, $hmacKey);
    }

    /**
     * Challenge als JSON-String
     */
    public static function createChallengeJson(
        string $hmacKey,
        int $maxNumber = 100000,
        string $algorithm = 'SHA-256'
    ): string {
        return json_encode(self::createChallenge($hmacKey, $maxNumber, $algorithm));
    }

    /**
     * Algorithmus-Name zu PHP hash()-Algorithmus konvertieren
     */
    private static function algorithmToPhp(string $algorithm): ?string
    {
        $map = [
            'SHA-256' => 'sha256',
            'SHA-384' => 'sha384',
            'SHA-512' => 'sha512',
        ];

        return $map[strtoupper($algorithm)] ?? null;
    }
}
