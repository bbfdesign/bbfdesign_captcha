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
    /**
     * Eingaben gegen Unicode-Evasion härten: NFKC-Normalisierung (macht
     * Kompatibilitäts-/Confusable-Formen kanonisch) + Entfernen unsichtbarer
     * „Format"-Zeichen (Zero-Width-Space/Joiner, Word-Joiner U+2060, BOM, Soft-
     * Hyphen, Bidi-Steuerzeichen). So matchen Domain-/Phrasen-Regex wieder, auch
     * wenn ein Spammer Muster mit unsichtbaren Zeichen zerstückelt.
     */
    private function normalizeForAnalysis(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        if (class_exists('\Normalizer')) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_KC);
            if (is_string($n) && $n !== '') {
                $s = $n;
            }
        }
        // Unicode-Kategorie Cf (Format) = u. a. U+200B–200D, U+2060–2064, U+FEFF,
        // U+00AD, Bidi-Steuerzeichen. Plus explizite Zero-Width-/Bidi-Ranges als
        // Fallback, falls \p{Cf} eine Variante nicht abdeckt.
        $cleaned = preg_replace('/\p{Cf}/u', '', $s);
        if (is_string($cleaned)) {
            $s = $cleaned;
        }
        $cleaned = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}\x{00AD}\x{180E}]/u', '', $s);
        if (is_string($cleaned)) {
            $s = $cleaned;
        }
        return $s;
    }

    public function analyze(string $text, ?string $email = null, ?string $name = null): array
    {
        $score   = 0;
        $details = [];

        if (empty(trim($text)) && empty($email) && empty($name)) {
            return ['score' => 0, 'details' => [], 'verdict' => 'OK'];
        }

        // ANTI-EVASION: Spammer zerstückeln Muster mit unsichtbaren Unicode-Zeichen
        // (z. B. Word-Joiner U+2060 in "iuhgjklll⁠.blogspot⁠.lu"), damit Domain-/
        // Phrasen-Regex nicht mehr matchen. Vor JEDER Prüfung normalisieren.
        $text = $this->normalizeForAnalysis($text);
        $name = $name !== null ? $this->normalizeForAnalysis($name) : null;

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

        // 6b. Bot-Token (verschachtelte Buchstaben/Ziffern, z. B. NARETGR117051NERTYTRY)
        $tokenResult = $this->checkGibberishTokens($combinedText);
        $score      += $tokenResult['score'];
        $details     = array_merge($details, $tokenResult['details']);

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

        // 10. Domains/URLs ohne Protokoll (häufig in Spam-Namen wie "x.blogspot.com.uy")
        $domainResult = $this->checkBareDomains($combinedText);
        $score       += $domainResult['score'];
        $details      = array_merge($details, $domainResult['details']);

        // 11. Krypto-/Investment-Spam-Muster ("0.4 BTC for Review", "$68,005", Wallet …)
        $cryptoResult = $this->checkCryptoSpam($combinedText);
        $score       += $cryptoResult['score'];
        $details      = array_merge($details, $cryptoResult['details']);

        // 12. Bekannte Spam-Phrasen (Pharma, SEO-/Marketing-, Geld-/Scam-Muster)
        $phraseResult = $this->checkSpamPhrases($combinedText);
        $score       += $phraseResult['score'];
        $details      = array_merge($details, $phraseResult['details']);

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

        // Optionale LLM-Zweitpruefung: ueberschreibt das Urteil des Scoring-Filters,
        // wenn aktiviert und (je nach Setting) nur im Grenzbereich.
        $llmVerdict = $this->maybeLlmCheck($text, $result['score']);
        if ($llmVerdict !== null) {
            if ($llmVerdict['spam']) {
                // Fail-open: Die LLM-Zweitprüfung darf NIE allein blockieren – eine
                // Fehlklassifikation würde sonst einen echten Kunden aussperren. Sie
                // wirkt nur, wenn der heuristische Filter bereits ein
                // Korroborations-Signal liefert (mindestens "verdächtig").
                $okThreshold = $this->settings->getInt('ai_threshold_ok', 30);
                if ($result['score'] >= $okThreshold) {
                    return [
                        'valid'  => false,
                        'reason' => 'LLM (' . $llmVerdict['provider'] . '): '
                                  . ($llmVerdict['reason'] ?: 'classified as spam')
                                  . ' (conf ' . number_format($llmVerdict['confidence'], 2) . ')',
                        'score'  => max($result['score'], 100),
                    ];
                }
                // Kein heuristisches Korroborations-Signal → nicht blockieren.
                // Das Absenden bleibt möglich; unten entscheidet allein die Heuristik.
            } elseif ($result['score'] >= $threshold && $result['score'] < 100) {
                // LLM sagt "kein Spam" → überschreibt einen Borderline-Block (fail-open).
                return [
                    'valid'  => true,
                    'reason' => '',
                    'score'  => $result['score'],
                ];
            }
        }

        if ($result['score'] >= $threshold) {
            return [
                'valid'  => false,
                'reason' => 'Smart-Filter: ' . $result['verdict'] . ' (Score: ' . $result['score'] . ')',
                'score'  => $result['score'],
            ];
        }

        return [
            'valid'  => true,
            'reason' => '',
            'score'  => $result['score'],
        ];
    }

    /**
     * Loest die LLM-Zweitpruefung aus, falls konfiguriert.
     * Im Default ("only_borderline") nur, wenn der Score im Grau-Bereich liegt.
     *
     * @return array{spam: bool, confidence: float, reason: string, provider: string}|null
     */
    private function maybeLlmCheck(string $text, int $heuristicScore): ?array
    {
        $llm = new LLMSpamService($this->settings);
        if (!$llm->isEnabled()) {
            return null;
        }

        $onlyBorderline = $this->settings->getBool('llm_only_borderline');
        if ($onlyBorderline) {
            $ok   = $this->settings->getInt('ai_threshold_ok', 30);
            $spam = $this->settings->getInt('ai_threshold_spam', 100);
            if ($heuristicScore < $ok || $heuristicScore >= $spam) {
                return null;
            }
        }

        if (trim($text) === '') {
            return null;
        }

        $result = $llm->classify($text);
        if (isset($result['error'])) {
            return null; // fail-open
        }
        return $result;
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

    /**
     * Bot-generierte „Tokens" erkennen: GROSSBUCHSTABEN mit eingebetteten Ziffern,
     * z. B. „NARETGR117051NERTYTRY" / „MERTYHR117051MARTHHDF". Solche Buchstaben-
     * Ziffern-Buchstaben-Cluster (jeweils mehrere Zeichen, durchgängig groß) kommen
     * in echten Namen/Nachrichten praktisch nie vor → starkes Spam-Signal.
     * Bewusst eng gefasst (4+ Großbuchst. · 3+ Ziffern · 4+ Großbuchst.), um echte
     * Eingaben (Bestellnummern mit Leerzeichen, „iPhone13", „COVID19") nicht zu treffen.
     */
    private function checkGibberishTokens(string $text): array
    {
        if (preg_match('/[A-ZÄÖÜ]{4,}\d{3,}[A-ZÄÖÜ]{4,}/u', $text)) {
            return [
                'score'   => 45,
                'details' => ['Bot-Token (Buchstaben/Ziffern-Mischung in Großschrift) (+45)'],
            ];
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
     * Domains/URLs OHNE Protokoll erkennen (z. B. "name.blogspot.com.uy").
     * Verlinkungen mit http(s):// deckt checkUrls ab – hier die nackten Domains,
     * die typisch in Spam-Namensfeldern stehen. Code-basiert, also robust gegen
     * eine leere Spam-Wörter-Tabelle.
     */
    private function checkBareDomains(string $text): array
    {
        $score   = 0;
        $details = [];

        if (preg_match_all('/\b(?:[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?\.){1,5}[a-z]{2,}\b/i', $text, $m)) {
            $domains = array_values(array_unique(array_map('strtolower', $m[0])));
            $count   = count($domains);
            $base    = min(40, 25 + ($count - 1) * 10);
            $score  += $base;
            $details[] = 'Domain/URL ohne Protokoll: '
                . implode(', ', array_slice($domains, 0, 3)) . ' (+' . $base . ')';

            foreach ($domains as $d) {
                foreach (self::SPAM_TLDS as $tld) {
                    if (str_ends_with($d, $tld)) {
                        $score    += 15;
                        $details[] = 'Spam-TLD in Domain: ' . $tld . ' (+15)';
                        break;
                    }
                }
                if (str_contains($d, 'blogspot') || str_contains($d, 'weebly')
                    || str_contains($d, 'tumblr') || str_contains($d, 'wixsite')
                    || str_contains($d, 'wordpress.com') || str_contains($d, 'sites.google')
                ) {
                    // Free-Hoster-Domains (blogspot & Co.) sind in Shop-Formularen
                    // praktisch immer Spam – stark gewichten, damit eine solche
                    // Domain allein die Schwelle erreicht (vorher +15 → blieb unter 60).
                    $score    += 40;
                    $details[] = 'Verdächtiger Free-Hoster in Domain (+40)';
                }
            }
        }

        return ['score' => min(75, $score), 'details' => $details];
    }

    /**
     * Krypto-/Investment-Spam-Muster erkennen (BTC/ETH, "for review", Geldbeträge,
     * Wallet-Begriffe). Ergänzt die DB-Spam-Wörter und greift auch ohne diese.
     */
    private function checkCryptoSpam(string $text): array
    {
        $patterns = [
            '/\bbtc\b/i', '/\beth\b/i', '/\busdt\b/i', '/\bbitcoin\b/i', '/\bethereum\b/i',
            '/\bcrypto\b/i', '/\bwallet\b/i', '/\bbinance\b/i', '/\bblockchain\b/i',
            '/\bairdrop\b/i', '/\bfor\s+review\b/i',
            '/\b\d+(?:[.,]\d+)?\s*btc\b/i', '/[\$€]\s?\d{1,3}(?:[.,]\d{3})+/',
        ];

        $hits = 0;
        foreach ($patterns as $p) {
            if (preg_match($p, $text)) {
                $hits++;
            }
        }

        if ($hits === 0) {
            return ['score' => 0, 'details' => []];
        }

        $score = min(50, 20 + ($hits - 1) * 12);
        return [
            'score'   => $score,
            'details' => ['Krypto-/Investment-Spam-Muster: ' . $hits . ' Treffer (+' . $score . ')'],
        ];
    }

    /**
     * Bekannte Spam-Phrasen (Pharma, SEO-/Marketing-, Geld-/Scam-Muster).
     * Bewusst englischsprachig/hochsignifikant gewählt – legitime deutschsprachige
     * Shop-Kontakte lösen das praktisch nie aus. Code-basiert (DB-unabhängig).
     */
    private function checkSpamPhrases(string $text): array
    {
        $patterns = [
            // Pharma (Wirkstoffnamen in einem Weinshop praktisch nie legitim)
            '/\b(viagra|cialis|levitra|tadalafil|sildenafil|kamagra)\b/i'                                  => 40,
            '/\b(online pharmacy|prescription drugs?|pills? online|pain ?killers?)\b/i'                     => 22,
            // SEO / Marketing
            '/\b(seo (services?|experts?|company|agency)|backlinks?|guest post|rank your (site|website)|increase (your )?(web ?)?traffic|web ?design services?|digital marketing offer)\b/i' => 24,
            // Geld / Scam
            '/\b(business proposal|make money online|work from home|investment opportunity|loan offer|inheritance fund|lottery winner|you have won|claim your (prize|reward))\b/i' => 24,
            '/\b(dear (sir|madam)|dear friend|i am contacting you regarding)\b/i'                           => 15,
        ];

        $score   = 0;
        $details = [];
        foreach ($patterns as $pattern => $weight) {
            if (preg_match($pattern, $text)) {
                $score    += $weight;
                $details[] = 'Spam-Phrase erkannt (+' . $weight . ')';
            }
        }

        return ['score' => min(60, $score), 'details' => $details];
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

        // WICHTIG: JTL liefert Registrierungs-/Formulardaten oft VERSCHACHTELT
        // (z.B. $_POST['register']['vorname']). Daher flach zusammenziehen, sonst
        // wird der Spam-Inhalt (z.B. Name) nicht gesehen.
        $flat = $this->flattenStrings($postData);

        $fields = $textFields[$formType] ?? ['message', 'text', 'comment', 'nachricht'];
        $texts  = [];

        foreach ($fields as $field) {
            if (!empty($flat[$field])) {
                $texts[] = $flat[$field];
            }
        }

        // Generisch: Alle längeren String-Felder prüfen
        if (empty($texts)) {
            foreach ($flat as $key => $value) {
                if (mb_strlen($value) > 20
                    && !str_contains($key, 'password') && !str_contains($key, 'passwort')
                    && !str_contains($key, 'token') && !str_contains($key, 'bbf_')
                    && !str_contains($key, 'jtl_') && !str_contains($key, 'hp_')
                ) {
                    $texts[] = $value;
                }
            }
        }

        return implode(' ', $texts);
    }

    /**
     * POST-Daten rekursiv zu einer flachen Map (Leaf-Key => String) zusammenziehen.
     * Bei Kollision gewinnt der längere Wert (echter Inhalt vor Leerfeld).
     *
     * @param array<mixed> $data
     * @param array<string,string> $flat
     * @return array<string,string>
     */
    private function flattenStrings(array $data, array &$flat = []): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->flattenStrings($value, $flat);
            } elseif (is_string($value) || is_numeric($value)) {
                $k = (string)$key;
                $v = (string)$value;
                if (!isset($flat[$k]) || mb_strlen($v) > mb_strlen($flat[$k])) {
                    $flat[$k] = $v;
                }
            }
        }

        return $flat;
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
