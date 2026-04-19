<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use JTL\Plugin\PluginInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Models\SpamLog;
use Plugin\bbfdesign_captcha\src\Models\IPEntry;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;
use Plugin\bbfdesign_captcha\src\Services\ConsentService;

/**
 * Zentrale Captcha-Orchestrierung
 *
 * Koordiniert alle aktiven Schutzmethoden für ein Formular,
 * berechnet einen Gesamtscore und entscheidet über die Aktion.
 */
class CaptchaService
{
    private PluginInterface $plugin;
    private DbInterface $db;
    private Setting $settings;

    private ?HoneypotService $honeypot = null;
    private ?TimingService $timing = null;
    private ?AltchaService $altcha = null;
    private ?TurnstileService $turnstile = null;
    private ?RecaptchaService $recaptcha = null;
    private ?FriendlyCaptchaService $friendlyCaptcha = null;
    private ?HCaptchaService $hcaptcha = null;
    private ?AISpamService $aiSpam = null;
    private ?RateLimitService $rateLimit = null;
    private ?BotDetectorService $botDetector = null;

    public function __construct(PluginInterface $plugin, ?DbInterface $db = null, ?Setting $settings = null)
    {
        $this->plugin   = $plugin;
        $this->db       = $db ?? \JTL\Shop::Container()->getDB();
        $this->settings = $settings ?? new Setting($this->db);
    }

    /**
     * Aktive Methoden für ein Formular ermitteln
     */
    public function getActiveMethodsForForm(string $formType): array
    {
        $row = $this->db->queryPrepared(
            "SELECT `methods`, `is_active` FROM `bbf_captcha_form_config`
             WHERE `form_type` = :type AND `form_identifier` IS NULL",
            ['type' => $formType],
            1
        );

        if ($row === null || !isset($row->methods) || (int)($row->is_active ?? 0) === 0) {
            // Default: Honeypot + Timing
            return ['honeypot', 'timing'];
        }

        $methods = json_decode($row->methods, true);
        return is_array($methods) ? $methods : ['honeypot', 'timing'];
    }

    /**
     * Form-Config für ein Formular holen
     */
    public function getFormConfig(string $formType): array
    {
        $row = $this->db->queryPrepared(
            "SELECT * FROM `bbf_captcha_form_config`
             WHERE `form_type` = :type AND `form_identifier` IS NULL",
            ['type' => $formType],
            1
        );

        if ($row === null) {
            return [
                'methods'         => ['honeypot', 'timing'],
                'score_threshold' => 60,
                'action_on_spam'  => 'both',
                'is_active'       => 1,
            ];
        }

        return [
            'methods'         => json_decode($row->methods ?? '[]', true) ?: ['honeypot', 'timing'],
            'score_threshold' => (int)($row->score_threshold ?? 60),
            'action_on_spam'  => $row->action_on_spam ?? 'both',
            'is_active'       => (int)($row->is_active ?? 1),
        ];
    }

    /**
     * HTML für alle aktiven Schutz-Widgets rendern
     */
    public function renderWidget(string $formType): string
    {
        if (!$this->settings->getBool('global_enabled')) {
            return '';
        }

        $config  = $this->getFormConfig($formType);
        $methods = $config['methods'];
        $html    = '';

        // Honeypot-Felder
        if (in_array('honeypot', $methods, true) && $this->settings->getBool('honeypot_enabled')) {
            $html .= $this->getHoneypot()->renderFields($formType);
        }

        // Timing Hidden-Field
        if (in_array('timing', $methods, true) && $this->settings->getBool('timing_enabled')) {
            $html .= $this->getTiming()->renderField();
        }

        // ALTCHA Widget
        if (in_array('altcha', $methods, true) && $this->settings->getBool('altcha_enabled')) {
            $challengeUrl = \JTL\Shop::getURL() . '/bbfdesign-captcha/api/challenge';
            $html .= $this->getAltcha()->renderWidget($challengeUrl);
        }

        // Externe Captchas – NUR rendern wenn Consent vorhanden!
        // Bei fehlendem Consent greift die Fallback-Kaskade (Honeypot/Timing/ALTCHA)
        $externalRendered = false;

        // Turnstile
        if (in_array('turnstile', $methods, true) && $this->getTurnstile()->isConfigured()) {
            if (ConsentService::hasConsent('turnstile')) {
                $html .= $this->getTurnstile()->renderWidget();
                $externalRendered = true;
            }
        }

        // reCAPTCHA
        if (!$externalRendered && in_array('recaptcha', $methods, true) && $this->getRecaptcha()->isConfigured()) {
            if (ConsentService::hasConsent('recaptcha')) {
                $html .= $this->getRecaptcha()->renderWidget();
                $externalRendered = true;
            }
        }

        // Friendly Captcha
        if (!$externalRendered && in_array('friendly_captcha', $methods, true) && $this->getFriendlyCaptcha()->isConfigured()) {
            if (ConsentService::hasConsent('friendly_captcha')) {
                $html .= $this->getFriendlyCaptcha()->renderWidget();
                $externalRendered = true;
            }
        }

        // hCaptcha
        if (!$externalRendered && in_array('hcaptcha', $methods, true) && $this->getHcaptcha()->isConfigured()) {
            if (ConsentService::hasConsent('hcaptcha')) {
                $html .= $this->getHcaptcha()->renderWidget();
                $externalRendered = true;
            }
        }

        // Fallback-Kaskade: Wenn externes Captcha konfiguriert aber kein Consent,
        // stelle sicher dass mindestens ALTCHA/Honeypot/Timing aktiv sind
        if (!$externalRendered && $this->hasExternalMethodConfigured($methods)) {
            // ALTCHA als Fallback (wenn noch nicht gerendert)
            if (!in_array('altcha', $methods, true) && $this->settings->getBool('altcha_enabled')) {
                $challengeUrl = \JTL\Shop::getURL() . '/bbfdesign-captcha/api/challenge';
                $html .= $this->getAltcha()->renderWidget($challengeUrl);
            }
        }

        return $html;
    }

    /**
     * Formular-Submission validieren
     *
     * Prüft alle aktiven Methoden und berechnet einen Gesamtscore.
     */
    public function validate(array $postData, string $formType): ValidationResult
    {
        if (!$this->settings->getBool('global_enabled')) {
            return new ValidationResult(true, 0, '');
        }

        $config    = $this->getFormConfig($formType);
        $methods   = $config['methods'];
        $threshold = $config['score_threshold'];
        $action    = $config['action_on_spam'];

        if ($config['is_active'] === 0) {
            return new ValidationResult(true, 0, '');
        }

        $totalScore = 0;
        $reasons    = [];
        $detectionMethod = '';

        // IP-Check (vorab, unabhängig von Formular-Config)
        $clientIp = PluginHelper::getClientIp();
        if ($this->settings->getBool('ip_protection_enabled')) {
            $ipEntry = new IPEntry($this->db);
            if ($ipEntry->isWhitelisted($clientIp)) {
                return new ValidationResult(true, 0, '');
            }
            if ($ipEntry->isBlacklisted($clientIp)) {
                $this->logSpam($clientIp, $formType, 'ip', 100, 'blocked');
                return new ValidationResult(false, 100, 'IP ist auf der Blacklist');
            }
        }

        // Honeypot validieren
        if (in_array('honeypot', $methods, true) && $this->settings->getBool('honeypot_enabled')) {
            $result = $this->getHoneypot()->validate($postData, $formType);
            if (!$result['valid']) {
                $totalScore += $result['score'];
                $reasons[]   = $result['reason'];
                if (empty($detectionMethod)) {
                    $detectionMethod = 'honeypot';
                }
            }
        }

        // Timing validieren
        if (in_array('timing', $methods, true) && $this->settings->getBool('timing_enabled')) {
            $result = $this->getTiming()->validate($postData);
            if (!$result['valid']) {
                $totalScore += $result['score'];
                $reasons[]   = $result['reason'];
                if (empty($detectionMethod)) {
                    $detectionMethod = 'timing';
                }
            }
        }

        // ALTCHA validieren
        if (in_array('altcha', $methods, true) && $this->settings->getBool('altcha_enabled')) {
            $result = $this->getAltcha()->validate($postData);
            if (!$result['valid']) {
                $totalScore += $result['score'];
                $reasons[]   = $result['reason'];
                if (empty($detectionMethod)) {
                    $detectionMethod = 'altcha';
                }
            }
        }

        // Externe Captchas validieren (NUR wenn Consent vorhanden)
        // Turnstile
        if (in_array('turnstile', $methods, true) && $this->getTurnstile()->isConfigured()) {
            if (ConsentService::hasConsent('turnstile')) {
                $result = $this->getTurnstile()->validate($postData);
                if (!$result['valid']) {
                    $totalScore += $result['score'];
                    $reasons[]   = $result['reason'];
                    if (empty($detectionMethod)) {
                        $detectionMethod = 'turnstile';
                    }
                }
            }
            // Kein Consent → Score 0 für diese Methode (Fallback greift über andere Methoden)
        }

        // reCAPTCHA
        if (in_array('recaptcha', $methods, true) && $this->getRecaptcha()->isConfigured()) {
            if (ConsentService::hasConsent('recaptcha')) {
                $result = $this->getRecaptcha()->validate($postData);
                if (!$result['valid']) {
                    $totalScore += $result['score'];
                    $reasons[]   = $result['reason'];
                    if (empty($detectionMethod)) {
                        $detectionMethod = 'recaptcha';
                    }
                }
            }
        }

        // Friendly Captcha
        if (in_array('friendly_captcha', $methods, true) && $this->getFriendlyCaptcha()->isConfigured()) {
            if (ConsentService::hasConsent('friendly_captcha')) {
                $result = $this->getFriendlyCaptcha()->validate($postData);
                if (!$result['valid']) {
                    $totalScore += $result['score'];
                    $reasons[]   = $result['reason'];
                    if (empty($detectionMethod)) {
                        $detectionMethod = 'friendly_captcha';
                    }
                }
            }
        }

        // hCaptcha
        if (in_array('hcaptcha', $methods, true) && $this->getHcaptcha()->isConfigured()) {
            if (ConsentService::hasConsent('hcaptcha')) {
                $result = $this->getHcaptcha()->validate($postData);
                if (!$result['valid']) {
                    $totalScore += $result['score'];
                    $reasons[]   = $result['reason'];
                    if (empty($detectionMethod)) {
                        $detectionMethod = 'hcaptcha';
                    }
                }
            }
        }

        // KI-Spamfilter
        if (in_array('ai_filter', $methods, true) && $this->settings->getBool('ai_filter_enabled')) {
            $result = $this->getAiSpam()->validate($postData, $formType);
            if (!$result['valid']) {
                $totalScore += $result['score'];
                $reasons[]   = $result['reason'];
                if (empty($detectionMethod)) {
                    $detectionMethod = 'ai';
                }
            }
        }

        // Rate Limiting
        if ($this->settings->getBool('rate_limit_enabled')) {
            $result = $this->getRateLimit()->validate($clientIp, $formType);
            if (!$result['valid']) {
                $totalScore += $result['score'];
                $reasons[]   = $result['reason'];
                if (empty($detectionMethod)) {
                    $detectionMethod = 'rate';
                }
            }
        }

        // Bot Detection (User-Agent)
        if ($this->settings->getBool('bot_detection_enabled')) {
            $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $result = $this->getBotDetector()->validate($ua);
            if (!$result['valid']) {
                $totalScore += $result['score'];
                $reasons[]   = $result['reason'];
                if (empty($detectionMethod)) {
                    $detectionMethod = 'bot';
                }
            }
            // JS-Challenge validieren
            $jsResult = $this->getBotDetector()->validateJsChallenge($postData);
            if (!$jsResult['valid']) {
                $totalScore += $jsResult['score'];
                $reasons[]   = $jsResult['reason'];
            }
        }

        // Score auswerten
        $reason = implode('; ', $reasons);

        if ($totalScore >= $threshold) {
            if (empty($detectionMethod)) {
                $detectionMethod = 'combined';
            }

            $actionTaken = 'blocked';
            if ($action === 'log') {
                $actionTaken = 'logged';
            }

            // Loggen
            $this->logSpam($clientIp, $formType, $detectionMethod, $totalScore, $actionTaken, $postData);

            // Auto-Block prüfen
            if ($this->settings->getBool('ip_auto_block_enabled')) {
                $this->checkAutoBlock($clientIp);
            }

            // Spam-Welle-Alert prüfen
            $logService = new SpamLogService($this->db, $this->settings);
            $logService->checkSpamWaveAlert();

            $isBlocked = ($action !== 'log');

            return new ValidationResult(!$isBlocked, $totalScore, $reason);
        }

        // Score > 0 aber unter Schwelle: nur loggen wenn suspekt
        if ($totalScore > 0 && $this->settings->getBool('debug_mode')) {
            $this->logSpam($clientIp, $formType, $detectionMethod ?: 'combined', $totalScore, 'allowed', $postData);
        }

        return new ValidationResult(true, $totalScore, $reason);
    }

    /**
     * Spam-Versuch loggen
     */
    private function logSpam(
        string $ip,
        string $formType,
        string $method,
        int $score,
        string $action,
        ?array $postData = null
    ): void {
        $spamLog = new SpamLog($this->db);
        $spamLog->log(
            $ip,
            $formType,
            $method,
            $score,
            $action,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $postData
        );
    }

    /**
     * Auto-Block: Prüfe ob IP nach wiederholtem Spam gesperrt werden soll
     */
    private function checkAutoBlock(string $ip): void
    {
        $attempts = $this->settings->getInt('ip_auto_block_attempts', 5);
        $window   = $this->settings->getInt('ip_auto_block_window', 10);
        $duration = $this->settings->getInt('ip_auto_block_duration', 1440);

        $count = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log`
             WHERE `ip_address` = :ip
             AND `action_taken` = 'blocked'
             AND `created_at` >= DATE_SUB(NOW(), INTERVAL :window MINUTE)",
            ['ip' => $ip, 'window' => $window],
            1
        );

        if ((int)($count->cnt ?? 0) >= $attempts) {
            $ipEntry = new IPEntry($this->db);
            $ipEntry->autoBlock(
                $ip,
                $duration,
                'Auto-Block: ' . $attempts . '+ Spam-Versuche in ' . $window . ' Minuten'
            );
        }
    }

    private function getHoneypot(): HoneypotService
    {
        if ($this->honeypot === null) {
            $this->honeypot = new HoneypotService($this->settings);
        }
        return $this->honeypot;
    }

    private function getTiming(): TimingService
    {
        if ($this->timing === null) {
            $this->timing = new TimingService($this->settings);
        }
        return $this->timing;
    }

    private function getAltcha(): AltchaService
    {
        if ($this->altcha === null) {
            $this->altcha = new AltchaService($this->settings);
        }
        return $this->altcha;
    }

    private function getTurnstile(): TurnstileService
    {
        if ($this->turnstile === null) {
            $this->turnstile = new TurnstileService($this->settings);
        }
        return $this->turnstile;
    }

    private function getRecaptcha(): RecaptchaService
    {
        if ($this->recaptcha === null) {
            $this->recaptcha = new RecaptchaService($this->settings);
        }
        return $this->recaptcha;
    }

    private function getFriendlyCaptcha(): FriendlyCaptchaService
    {
        if ($this->friendlyCaptcha === null) {
            $this->friendlyCaptcha = new FriendlyCaptchaService($this->settings);
        }
        return $this->friendlyCaptcha;
    }

    private function getHcaptcha(): HCaptchaService
    {
        if ($this->hcaptcha === null) {
            $this->hcaptcha = new HCaptchaService($this->settings);
        }
        return $this->hcaptcha;
    }

    private function getAiSpam(): AISpamService
    {
        if ($this->aiSpam === null) {
            $this->aiSpam = new AISpamService($this->db, $this->settings);
        }
        return $this->aiSpam;
    }

    private function getRateLimit(): RateLimitService
    {
        if ($this->rateLimit === null) {
            $this->rateLimit = new RateLimitService($this->db, $this->settings);
        }
        return $this->rateLimit;
    }

    private function getBotDetector(): BotDetectorService
    {
        if ($this->botDetector === null) {
            $this->botDetector = new BotDetectorService($this->settings);
        }
        return $this->botDetector;
    }

    /**
     * Prüft ob mindestens ein externer Captcha-Dienst in den Methoden konfiguriert ist
     */
    private function hasExternalMethodConfigured(array $methods): bool
    {
        $external = ['turnstile', 'recaptcha', 'friendly_captcha', 'hcaptcha'];
        foreach ($external as $m) {
            if (in_array($m, $methods, true)) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Ergebnis einer Captcha-Validierung
 */
class ValidationResult
{
    private bool $valid;
    private int $score;
    private string $reason;

    public function __construct(bool $valid, int $score, string $reason)
    {
        $this->valid  = $valid;
        $this->score  = $score;
        $this->reason = $reason;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
