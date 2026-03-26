<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * KI-basierter Spam-Filter (lokales Punktesystem)
 *
 * Reine PHP-String-Operationen, kein ML-Framework, kein externer API-Call.
 * Ähnlich SpamAssassin-Logik mit konfigurierbaren Schwellenwerten.
 *
 * Score-Regeln:
 * - Zu viele URLs → +30
 * - Spam-TLDs (.xyz, .click, .top) → +20 pro URL
 * - Exzessive Großschreibung (>60%) → +15
 * - Bekannte Spam-Phrasen → +25 pro Match
 * - Fremdsprache in DE/EN Shop → +10
 * - Kyrillisch/Chinesisch → +20
 * - Wegwerf-Email → +30
 * - Unlesbare Zeichenketten → +40
 * - Verdächtige Wiederholungen → +15
 * - Text < 3 Wörter → +10
 * - HTML/BBCode im Freitext → +20
 */
class AISpamService
{
    private DbInterface $db;
    private Setting $settings;

    /** Bekannte Spam-TLDs */
    private const SPAM_TLDS = [
        '.xyz', '.click', '.top', '.buzz', '.gq', '.ml', '.cf', '.tk', '.ga',
        '.pw', '.cc', '.club', '.info', '.biz', '.wang', '.win', '.bid',
        '.stream', '.racing', '.download', '.loan', '.trade', '.date',
        '.science', '.party', '.review', '.cricket', '.accountant',
    ];

    /** Muster für verdächtige Wiederholungen */
    private const REPETITION_THRESHOLD = 4;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    /**
     * Text analysieren und Score berechnen
     *
     * @return array{score: int, details: array, verdict: string}
     */
    public function analyze(string $text, ?string $email = null, ?string $name = null): array
    {
        $score   = 0;
        $details = [];

        if (empty(trim($text)) && empty($email) && empty($name)) {
            return ['score' => 0, 'details' => [], 'verdict' => 'OK'];
        }

        $combinedText = trim($text . ' ' . ($name ?? ''));

        // 1. URL-Check
        $urlResult = $this->checkUrls($combinedText);
        $score    += $urlResult['score'];
        $details   = array_merge($details, $urlResult['details']);

        // 2. Großbuchstaben-Check
        $capsResult = $this->checkExcessiveCaps($combinedText);
        $score     += $capsResult['score'];
        $details    = array_merge($details, $capsResult['details']);

        // 3. Spam-Wörter aus DB
        $wordResult = $this->checkSpamWords($combinedText);
        $score     += $wordResult['score'];
        $details    = array_merge($details, $wordResult['details']);

        // 4. Spracherkennung (Kyrillisch, Chinesisch in DE/EN Shop)
        if ($this->settings->getBool('ai_check_language')) {
            $langResult = $this->checkLanguage($combinedText);
            $score     += $langResult['score'];
            $details    = array_merge($details, $langResult['details']);
        }

        // 5. Wegwerf-Email
        if ($email !== null && $this->settings->getBool('ai_check_disposable_email')) {
            $emailResult = $this->checkDisposableEmail($email);
            $score      += $emailResult['score'];
            $details     = array_merge($details, $emailResult['details']);
        }

        // 6. Unlesbare Zeichenketten
        $garbageResult = $this->checkGarbage($combinedText);
        $score        += $garbageResult['score'];
        $details       = array_merge($details, $garbageResult['details']);

        // 7. Wiederholungsmuster
        $repResult = $this->checkRepetitions($combinedText);
        $score    += $repResult['score'];
        $details   = array_merge($details, $repResult['details']);

        // 8. Zu kurzer Text
        $lenResult = $this->checkLength($text);
        $score    += $lenResult['score'];
        $details   = array_merge($details, $lenResult['details']);

        // 9. HTML/BBCode im Freitext
        $htmlResult = $this->checkHtmlBbcode($text);
        $score     += $htmlResult['score'];
        $details    = array_merge($details, $htmlResult['details']);

        // Bewertung
        $thresholdOk         = $this->settings->getInt('ai_threshold_ok', 30);
        $thresholdSuspicious = $this->settings->getInt('ai_threshold_suspicious', 60);
        $thresholdSpam       = $this->settings->getInt('ai_threshold_spam', 100);

        $verdict = 'OK';
        if ($score > $thresholdSpam) {
            $verdict = 'Definitiv Spam';
        } elseif ($score > $thresholdSuspicious) {
            $verdict = 'Wahrscheinlich Spam';
        } elseif ($score > $thresholdOk) {
            $verdict = 'Verdächtig';
        }

        return [
            'score'   => $score,
            'details' => $details,
            'verdict' => $verdict,
        ];
    }

    /**
     * Validierung für CaptchaService-Integration
     *
     * @return array{valid: bool, reason: string, score: int}
     */
    public function validate(array $postData, string $formType): array
    {
        // Text-Felder aus POST-Daten extrahieren
        $text  = $this->extractTextField($postData, $formType);
        $email = $postData['email'] ?? $postData['mail'] ?? $postData['cEmail'] ?? null;
        $name  = $postData['name'] ?? $postData['vorname'] ?? $postData['cVorname'] ?? null;

        if (empty(trim($text)) && empty($email)) {
            return ['valid' => true, 'reason' => '', 'score' => 0];
        }

        $result    = $this->analyze($text, $email, $name);
        $threshold = $this->settings->getInt('ai_threshold_suspicious', 60);

        if ($result['score'] >= $threshold) {
            return [
                'valid'  => false,
                'reason' => 'KI-Filter: ' . $result['verdict'] . ' (Score: ' . $result['score'] . ')',
                'score'  => $result['score'],
            ];
        }

        return [
            'valid'  => true,
            'reason' => '',
            'score'  => $result['score'],
        ];
    }

    // ─── Einzelne Prüfungen ─────────────────────────────────

    private function checkUrls(string $text): array
    {
        $score   = 0;
        $details = [];

        // URLs zählen
        preg_match_all('/https?:\/\/[^\s<>"\']+/i', $text, $matches);
        $urlCount = count($matches[0] ?? []);

        if ($urlCount > 3) {
            $points    = min(50, $urlCount * 10);
            $score    += $points;
            $details[] = 'Zu viele URLs: ' . $urlCount . ' (+' . $points . ')';
        } elseif ($urlCount > 1) {
            $score    += 15;
            $details[] = 'Mehrere URLs: ' . $urlCount . ' (+15)';
        }

        // Spam-TLDs prüfen
        foreach ($matches[0] ?? [] as $url) {
            $parsedHost = parse_url($url, PHP_URL_HOST);
            if ($parsedHost === null || $parsedHost === false) {
                continue;
            }
            foreach (self::SPAM_TLDS as $tld) {
                if (str_ends_with(strtolower($parsedHost), $tld)) {
                    $score    += 20;
                    $details[] = 'Spam-TLD in URL: ' . $tld . ' (+20)';
                    break;
                }
            }
        }

        return ['score' => $score, 'details' => $details];
    }

    private function checkExcessiveCaps(string $text): array
    {
        $stripped = preg_replace('/\s+/', '', $text);
        $total   = mb_strlen($stripped);

        if ($total < 10) {
            return ['score' => 0, 'details' => []];
        }

        $upper = preg_match_all('/[A-ZÄÖÜ]/u', $stripped);
        $ratio = $upper / $total;

        if ($ratio > 0.6) {
            return [
                'score'   => 15,
                'details' => ['Exzessive Großschreibung: ' . round($ratio * 100) . '% (+15)'],
            ];
        }

        return ['score' => 0, 'details' => []];
    }

    private function checkSpamWords(string $text): array
    {
        $score   = 0;
        $details = [];

        $rows = $this->db->queryPrepared(
            "SELECT `word`, `weight` FROM `bbf_captcha_spam_words` WHERE `category` = 'spam'",
            [],
            2
        );

        if (!is_array($rows)) {
            return ['score' => 0, 'details' => []];
        }

        $lowerText = mb_strtolower($text);

        foreach ($rows as $row) {
            $word = mb_strtolower($row->word);
            if (mb_strpos($lowerText, $word) !== false) {
                $weight    = (int)$row->weight;
                $score    += $weight;
                $details[] = 'Spam-Wort "' . $row->word . '" (+' . $weight . ')';
            }
        }

        return ['score' => $score, 'details' => $details];
    }

    private function checkLanguage(string $text): array
    {
        $score   = 0;
        $details = [];

        // Kyrillische Zeichen
        $cyrillicCount = preg_match_all('/[\p{Cyrillic}]/u', $text);
        if ($cyrillicCount > 5) {
            $score    += 20;
            $details[] = 'Kyrillische Zeichen: ' . $cyrillicCount . ' (+20)';
        }

        // Chinesische/Japanische/Koreanische Zeichen
        $cjkCount = preg_match_all('/[\p{Han}\p{Hangul}\p{Katakana}\p{Hiragana}]/u', $text);
        if ($cjkCount > 3) {
            $score    += 20;
            $details[] = 'CJK-Zeichen: ' . $cjkCount . ' (+20)';
        }

        // Arabische Zeichen
        $arabicCount = preg_match_all('/[\p{Arabic}]/u', $text);
        if ($arabicCount > 5) {
            $score    += 15;
            $details[] = 'Arabische Zeichen: ' . $arabicCount . ' (+15)';
        }

        return ['score' => $score, 'details' => $details];
    }

    /** In-Memory Cache für Disposable Domains */
    private static ?array $disposableDomainsCache = null;

    private function checkDisposableEmail(?string $email): array
    {
        if (empty($email) || !str_contains($email, '@')) {
            return ['score' => 0, 'details' => []];
        }

        $domain = strtolower(substr($email, strrpos($email, '@') + 1));

        // 1. DB-Lookup
        $result = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_disposable_domains` WHERE `domain` = :domain",
            ['domain' => $domain],
            1
        );

        if ((int)($result->cnt ?? 0) > 0) {
            return [
                'score'   => 30,
                'details' => ['Wegwerf-Email-Domain: ' . $domain . ' (+30)'],
            ];
        }

        // 2. PHP-Datei Fallback (In-Memory Cache)
        if (self::$disposableDomainsCache === null) {
            $filePath = dirname(__DIR__, 2) . '/src/Data/disposable_domains.php';
            if (file_exists($filePath)) {
                $list = require $filePath;
                self::$disposableDomainsCache = is_array($list) ? array_flip($list) : [];
            } else {
                self::$disposableDomainsCache = [];
            }
        }

        if (isset(self::$disposableDomainsCache[$domain])) {
            return [
                'score'   => 30,
                'details' => ['Wegwerf-Email-Domain: ' . $domain . ' (+30)'],
            ];
        }

        // 3. Regex-Muster für bekannte Wegwerf-Prefixes
        $patterns = [
            '/^temp/i', '/^trash/i', '/^guerrilla/i', '/^mailinator/i',
            '/^throwaway/i', '/^fake/i', '/^disposable/i', '/^10minute/i',
            '/^yopmail/i', '/^sharklasers/i', '/^grr\./i', '/^maildrop/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                return [
                    'score'   => 25,
                    'details' => ['Verdächtige Email-Domain: ' . $domain . ' (+25)'],
                ];
            }
        }

        return ['score' => 0, 'details' => []];
    }

    private function checkGarbage(string $text): array
    {
        $stripped = preg_replace('/\s+/', '', $text);
        $total   = mb_strlen($stripped);

        if ($total < 5) {
            return ['score' => 0, 'details' => []];
        }

        // Anteil Sonderzeichen (nicht Buchstaben, nicht Zahlen)
        $special = preg_match_all('/[^\p{L}\p{N}\s]/u', $stripped);
        $ratio   = $special / $total;

        if ($ratio > 0.5 && $total > 10) {
            return [
                'score'   => 40,
                'details' => ['Unlesbare Zeichenkette: ' . round($ratio * 100) . '% Sonderzeichen (+40)'],
            ];
        }

        // Konsonanten-Cluster (kein natürlicher Text)
        if (preg_match('/[bcdfghjklmnpqrstvwxyz]{8,}/i', $text)) {
            return [
                'score'   => 30,
                'details' => ['Verdächtige Konsonanten-Cluster (+30)'],
            ];
        }

        return ['score' => 0, 'details' => []];
    }

    private function checkRepetitions(string $text): array
    {
        // Gleiche Wörter wiederholt
        $words      = preg_split('/\s+/', mb_strtolower($text));
        $wordCounts = array_count_values($words);

        foreach ($wordCounts as $word => $count) {
            if (mb_strlen((string)$word) > 3 && $count >= self::REPETITION_THRESHOLD) {
                return [
                    'score'   => 15,
                    'details' => ['Verdächtige Wiederholung: "' . $word . '" ' . $count . 'x (+15)'],
                ];
            }
        }

        // Gleiche Zeichen wiederholt (z.B. "aaaaaa")
        if (preg_match('/(.)\1{9,}/u', $text)) {
            return [
                'score'   => 20,
                'details' => ['Exzessive Zeichen-Wiederholung (+20)'],
            ];
        }

        return ['score' => 0, 'details' => []];
    }

    private function checkLength(string $text): array
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) < 3 && !empty(trim($text))) {
            return [
                'score'   => 10,
                'details' => ['Text kürzer als 3 Wörter (+10)'],
            ];
        }

        return ['score' => 0, 'details' => []];
    }

    private function checkHtmlBbcode(string $text): array
    {
        $score   = 0;
        $details = [];

        // HTML-Tags
        if (preg_match('/<\/?[a-z][\s\S]*>/i', $text)) {
            $score    += 20;
            $details[] = 'HTML-Tags im Freitext (+20)';
        }

        // BBCode
        if (preg_match('/\[(?:url|link|img|b|i|u|size|color|font)[\]=]/i', $text)) {
            $score    += 15;
            $details[] = 'BBCode im Freitext (+15)';
        }

        return ['score' => $score, 'details' => $details];
    }

    /**
     * Text-Feld aus POST-Daten extrahieren (formularabhängig)
     */
    private function extractTextField(array $postData, string $formType): string
    {
        // JTL-Standard Feldnamen
        $textFields = [
            'contact'      => ['nachricht', 'cNachricht', 'message', 'kommentar'],
            'registration' => ['cVorname', 'cNachname', 'vorname', 'nachname'],
            'newsletter'   => ['cEmail', 'email'],
            'review'       => ['cText', 'cTitel', 'text', 'title', 'bewertung'],
            'checkout'     => ['cKommentar', 'kommentar', 'comment'],
            'login'        => [],
        ];

        $fields = $textFields[$formType] ?? ['message', 'text', 'comment', 'nachricht'];
        $texts  = [];

        foreach ($fields as $field) {
            if (!empty($postData[$field]) && is_string($postData[$field])) {
                $texts[] = $postData[$field];
            }
        }

        // Generisch: Alle längeren String-Felder prüfen
        if (empty($texts)) {
            foreach ($postData as $key => $value) {
                if (is_string($value) && mb_strlen($value) > 20
                    && !str_contains($key, 'password') && !str_contains($key, 'token')
                    && !str_contains($key, 'bbf_') && !str_contains($key, 'jtl_')
                ) {
                    $texts[] = $value;
                }
            }
        }

        return implode(' ', $texts);
    }

    /**
     * Lernfunktion: Admin markiert einen Eintrag als Spam/Ham
     * und das System passt die Wortliste an.
     */
    public function learnFromFeedback(string $text, bool $isSpam): void
    {
        $words = preg_split('/\s+/', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_unique($words);

        foreach ($words as $word) {
            if (mb_strlen($word) < 4 || mb_strlen($word) > 50) {
                continue;
            }
            // Nur Wörter ohne Sonderzeichen
            if (!preg_match('/^[\p{L}\p{N}]+$/u', $word)) {
                continue;
            }

            $category = $isSpam ? 'spam' : 'ham';
            $weight   = $isSpam ? 10 : -5;

            // Existiert das Wort bereits?
            $existing = $this->db->queryPrepared(
                "SELECT `id`, `weight`, `category` FROM `bbf_captcha_spam_words` WHERE `word` = :word",
                ['word' => $word],
                1
            );

            if ($existing !== null && isset($existing->id)) {
                // Gewicht anpassen
                $newWeight = max(0, min(100, (int)$existing->weight + $weight));
                $this->db->queryPrepared(
                    "UPDATE `bbf_captcha_spam_words` SET `weight` = :weight, `category` = :cat, `auto_learned` = 1
                     WHERE `id` = :id",
                    ['weight' => $newWeight, 'cat' => $category, 'id' => $existing->id]
                );
            } elseif ($isSpam) {
                // Neues Spam-Wort einfügen (nur bei Spam, nicht bei Ham)
                $this->db->queryPrepared(
                    "INSERT IGNORE INTO `bbf_captcha_spam_words` (`word`, `category`, `weight`, `auto_learned`)
                     VALUES (:word, :cat, :weight, 1)",
                    ['word' => $word, 'cat' => 'spam', 'weight' => 10]
                );
            }
        }
    }
}
