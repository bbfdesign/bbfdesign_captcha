<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Bot-Erkennung via User-Agent
 *
 * - Mitgelieferte Bot-Liste (~200 bekannte Bots/Crawler)
 * - Known Good Bots (Google, Bing) von Spam-Bots unterscheiden
 * - User-Agent-Analyse: fehlender UA, verdächtige Muster
 * - Headless Browser Detection
 * - In-Memory (Array-Lookup), keine DB-Query
 */
class BotDetectorService
{
    private Setting $settings;

    /** Bekannte gute Bots (Search Engines, Monitoring etc.) – NICHT blockieren */
    private const GOOD_BOTS = [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
        'yandexbot', 'facebot', 'ia_archiver', 'mj12bot',
        'ahrefsbot', 'semrushbot', 'dotbot', 'rogerbot',
        'linkedinbot', 'twitterbot', 'pinterestbot', 'applebot',
        'uptimerobot', 'pingdom', 'statuscake', 'jetmon',
        'google-structured-data-testing-tool', 'google-inspectiontool',
        'apis-google', 'mediapartners-google', 'adsbot-google',
        'chrome-lighthouse', 'pagespeed', 'gtmetrix',
    ];

    /** Bekannte Spam/Scraper Bots */
    private const BAD_BOTS = [
        'sqlmap', 'nikto', 'dirbuster', 'nessus', 'nmap',
        'masscan', 'zgrab', 'censys', 'shodan',
        'python-requests', 'python-urllib', 'python/',
        'go-http-client', 'java/', 'wget/', 'curl/',
        'libwww-perl', 'lwp-trivial', 'mechanize',
        'scrapy', 'httpclient', 'nutch', 'anthill',
        'emailcollector', 'emailsiphon', 'emailwolf',
        'extractorpro', 'harvest', 'collector',
        'webbandit', 'webcopier', 'websauger', 'webstripper',
        'webzip', 'teleport', 'miixpc', 'offline explorer',
        'black.hole', 'blackwidow', 'blowfish', 'botalot',
        'buddy', 'builtbottough', 'bullseye', 'bunnyslippers',
        'cheesebot', 'cherrypicker', 'copyrightcheck',
        'cosmos', 'crescent', 'discobot', 'dittospyder',
        'dotnetdotcom', 'dumbot', 'emailharvest',
        'eirgrabber', 'express webpictures', 'flaming attackbot',
        'foobot', 'frontpage', 'grafula', 'grub',
        'hambot', 'hloader', 'httplib', 'htmlparser',
        'humanlinks', 'infonavirobot', 'jennybot',
        'kenjin spider', 'keyword density', 'larbin',
        'leechftp', 'lexibot', 'linkextractorpro',
        'linkscan', 'linkwalker', 'lnspiderguy',
        'mag-net', 'memo', 'microsoft url control',
        'midown tool', 'mister pix', 'moget',
        'mozilla/3.0 (compatible)', 'netants', 'octopus',
        'offline navigator', 'openfind', 'pagegrabber',
        'papa foto', 'pavuk', 'pcbrowser', 'propowerbot',
        'prowebwalker', 'queryn metasearch', 'reget',
        'repomonkey', 'rma', 'siphon', 'siteexplorer',
        'sitesnagger', 'smartdownload', 'superbot', 'superhttp',
        'surfbot', 'thenomad', 'titan', 'urly.warning',
        'vci', 'webmasterworldforumbot', 'website quester',
        'webster', 'webleacher', 'webmirror', 'websampling',
        'spankbot', 'spanner', 'spbot', 'stackrambler',
        'stripper', 'sucker', 'szukacz', 'telesoft',
        'true_robot', 'turingos', 'turnitinbot', 'voyager',
        'webalta', 'webcollage', 'webfetch', 'webreaper',
        'webwhacker', 'widow', 'wisenutbot', 'wwwoffle',
        'xaldon', 'zeus', 'zmeu', 'zyborg',
        'headlesschrome', 'phantomjs', 'slimerjs',
    ];

    /** Verdächtige UA-Muster */
    private const SUSPICIOUS_PATTERNS = [
        '/^Mozilla\/[45]\.0\s*$/',              // Zu kurzer Mozilla-UA
        '/^Mozilla\/5\.0\s+\(compatible;\s*\)/', // Leerer Compatible-String
        '/^\s*$/',                                // Leerer UA
    ];

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
    }

    /**
     * User-Agent analysieren
     *
     * @return array{valid: bool, reason: string, score: int, is_good_bot: bool}
     */
    public function validate(?string $userAgent): array
    {
        if (!$this->settings->getBool('bot_detection_enabled')) {
            return ['valid' => true, 'reason' => '', 'score' => 0, 'is_good_bot' => false];
        }

        // Fehlender User-Agent
        if (empty($userAgent)) {
            return [
                'valid'       => false,
                'reason'      => 'Fehlender User-Agent',
                'score'       => 30,
                'is_good_bot' => false,
            ];
        }

        $uaLower = strtolower($userAgent);

        // Good Bot → durchlassen
        foreach (self::GOOD_BOTS as $bot) {
            if (str_contains($uaLower, $bot)) {
                return [
                    'valid'       => true,
                    'reason'      => '',
                    'score'       => 0,
                    'is_good_bot' => true,
                ];
            }
        }

        // Bad Bot → blockieren
        foreach (self::BAD_BOTS as $bot) {
            if (str_contains($uaLower, $bot)) {
                return [
                    'valid'       => false,
                    'reason'      => 'Bekannter Spam-Bot: ' . $bot,
                    'score'       => 80,
                    'is_good_bot' => false,
                ];
            }
        }

        // Verdächtige Muster
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return [
                    'valid'       => false,
                    'reason'      => 'Verdächtiger User-Agent',
                    'score'       => 40,
                    'is_good_bot' => false,
                ];
            }
        }

        // UA zu kurz (< 20 Zeichen, kein normaler Browser)
        if (mb_strlen($userAgent) < 20) {
            return [
                'valid'       => false,
                'reason'      => 'User-Agent zu kurz',
                'score'       => 25,
                'is_good_bot' => false,
            ];
        }

        return [
            'valid'       => true,
            'reason'      => '',
            'score'       => 0,
            'is_good_bot' => false,
        ];
    }

    /**
     * JavaScript-Challenge Token generieren
     *
     * Ein kleines JS berechnet einen Token der mitgesendet wird.
     * Bots ohne JS-Ausführung können diesen Token nicht liefern.
     */
    public function generateJsChallenge(): array
    {
        if (!$this->settings->getBool('bot_js_challenge')) {
            return ['field' => '', 'script' => ''];
        }

        $timestamp = time();
        $hmacKey   = $this->settings->get('altcha_hmac_key', 'js_challenge_key');
        $expected  = substr(hash_hmac('sha256', 'bbf_js_' . $timestamp, $hmacKey), 0, 16);

        $field = '<input type="hidden" name="bbf_js_token" id="bbf_js_token" value="">'
               . '<input type="hidden" name="bbf_js_ts" value="' . $timestamp . '">';

        // JS das den Token berechnet (einfache String-Operation)
        $script = '<script>'
                . '(function(){'
                . 'var t="' . $timestamp . '";'
                . 'var s="bbf_js_"+t;'
                . 'var h=0;for(var i=0;i<s.length;i++){h=((h<<5)-h)+s.charCodeAt(i);h|=0;}'
                . 'document.getElementById("bbf_js_token").value=Math.abs(h).toString(16);'
                . '})();'
                . '</script>';

        return ['field' => $field, 'script' => $script, 'expected_prefix' => $expected];
    }

    /**
     * JS-Challenge validieren
     */
    public function validateJsChallenge(array $postData): array
    {
        if (!$this->settings->getBool('bot_js_challenge')) {
            return ['valid' => true, 'reason' => '', 'score' => 0];
        }

        $jsToken = $postData['bbf_js_token'] ?? '';

        if (empty($jsToken)) {
            return [
                'valid'  => false,
                'reason' => 'JS-Challenge nicht gelöst (kein JavaScript?)',
                'score'  => 50,
            ];
        }

        // Token ist ein Hex-Wert → mindestens das Format muss stimmen
        if (!preg_match('/^[0-9a-f]+$/', $jsToken)) {
            return [
                'valid'  => false,
                'reason' => 'JS-Challenge Token ungültig',
                'score'  => 60,
            ];
        }

        return ['valid' => true, 'reason' => '', 'score' => 0];
    }
}
