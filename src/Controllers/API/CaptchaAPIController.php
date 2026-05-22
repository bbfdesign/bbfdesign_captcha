<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Controllers\API;

use JTL\DB\DbInterface;
use JTL\Plugin\PluginInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Models\ApiKey;
use Plugin\bbfdesign_captcha\src\Services\CaptchaService;
use Plugin\bbfdesign_captcha\src\Services\AltchaService;
use Plugin\bbfdesign_captcha\src\Services\SpamLogService;
use Plugin\bbfdesign_captcha\src\Services\IPService;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;

/**
 * REST API Controller für externe Systeme
 *
 * Endpoint-Prefix: /bbfdesign-captcha/api/v1/
 * Auth: API-Key im Header X-BBF-Captcha-Key
 * Rate Limiting: 60 Requests/Minute pro Key
 * Response-Format: JSON
 */
class CaptchaAPIController
{
    private const MAX_JSON_INPUT_BYTES = 1048576;

    private PluginInterface $plugin;
    private DbInterface $db;
    private Setting $settings;
    private ApiKey $apiKeyModel;
    private ?string $jsonInputError = null;

    public function __construct(PluginInterface $plugin, DbInterface $db, Setting $settings)
    {
        $this->plugin      = $plugin;
        $this->db          = $db;
        $this->settings    = $settings;
        $this->apiKeyModel = new ApiKey($db);
    }

    /**
     * API-Request verarbeiten
     */
    public function handleRequest(string $endpoint, string $method): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-BBF-Captcha-Version: 1.0.0');

        // Health-Check braucht keine Auth
        if ($endpoint === 'health') {
            $this->sendJson(['status' => 'ok', 'version' => '1.0.0', 'timestamp' => time()]);
            return;
        }

        // Challenge-Endpoint braucht keine Auth (wird vom Frontend aufgerufen)
        if ($endpoint === 'challenge' && $method === 'GET') {
            $this->handleChallenge();
            return;
        }

        // Alle anderen Endpoints: API-Key Auth
        $apiKey = $this->authenticate();
        if ($apiKey === null) {
            $this->sendError('Unauthorized: Invalid or missing API key', 401);
            return;
        }

        // Rate Limiting prüfen
        if (!$this->checkRateLimit($apiKey)) {
            $this->sendError('Rate limit exceeded', 429);
            return;
        }

        // Routing
        switch ($endpoint) {
            case 'validate':
                if ($method !== 'POST') {
                    $this->sendError('Method not allowed', 405);
                    return;
                }
                $this->handleValidate($apiKey);
                break;

            case 'stats':
                $this->handleStats($apiKey);
                break;

            case 'stats/today':
                $this->handleStatsToday($apiKey);
                break;

            case 'ip/check':
                if ($method !== 'POST') {
                    $this->sendError('Method not allowed', 405);
                    return;
                }
                $this->handleIpCheck($apiKey);
                break;

            case 'ip/block':
                if ($method === 'POST') {
                    $this->handleIpBlock($apiKey);
                } elseif ($method === 'DELETE') {
                    $this->handleIpUnblock($apiKey);
                } else {
                    $this->sendError('Method not allowed', 405);
                }
                break;

            case 'log':
                $this->handleLog($apiKey);
                break;

            case 'methods':
                $this->handleMethods($apiKey);
                break;

            default:
                $this->sendError('Endpoint not found: ' . $endpoint, 404);
        }
    }

    // ─── Endpoints ──────────────────────────────────────────

    private function handleChallenge(): void
    {
        $altcha = new AltchaService($this->settings);
        $challenge = $altcha->createChallenge();
        $this->sendJson($challenge);
    }

    private function handleValidate(object $apiKey): void
    {
        if (!$this->requirePermission($apiKey, 'validate')) {
            return;
        }

        $input = $this->getJsonInputOrFail();
        if ($input === null) {
            return;
        }

        $postData = $input['data'] ?? $input;
        if (!is_array($postData)) {
            $this->sendError('Invalid data payload', 400);
            return;
        }

        $formType = $this->normalizeFormType((string)($input['form_type'] ?? 'api'));
        if ($formType === null) {
            $this->sendError('Invalid form_type', 400);
            return;
        }

        $captcha = new CaptchaService($this->plugin, $this->db, $this->settings);
        $result  = $captcha->validate($postData, $formType);

        $this->sendJson([
            'valid'  => $result->isValid(),
            'score'  => $result->getScore(),
            'reason' => $result->getReason(),
        ]);
    }

    private function handleStats(object $apiKey): void
    {
        if (!$this->requirePermission($apiKey, 'stats')) {
            return;
        }

        $logService = new SpamLogService($this->db, $this->settings);
        $kpis       = $logService->getKPIs();
        $trend      = $logService->getTrend();

        $this->sendJson([
            'blocked_today'  => $kpis['blocked_today'],
            'blocked_total'  => $kpis['blocked_total'],
            'detection_rate' => $kpis['detection_rate'],
            'trend'          => $trend,
        ]);
    }

    private function handleStatsToday(object $apiKey): void
    {
        if (!$this->requirePermission($apiKey, 'stats')) {
            return;
        }

        $logService = new SpamLogService($this->db, $this->settings);
        $kpis       = $logService->getKPIs();

        $this->sendJson([
            'blocked_today' => $kpis['blocked_today'],
            'date'          => date('Y-m-d'),
        ]);
    }

    private function handleIpCheck(object $apiKey): void
    {
        if (!$this->requireAnyPermission($apiKey, ['ip_manage', 'validate'])) {
            return;
        }

        $input = $this->getJsonInputOrFail();
        if ($input === null) {
            return;
        }

        $ip    = $input['ip'] ?? '';

        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->sendError('Invalid IP address', 400);
            return;
        }

        $ipService = new IPService($this->db, $this->settings);

        $this->sendJson([
            'ip'          => $ip,
            'blacklisted' => $ipService->isBlacklisted($ip),
            'whitelisted' => $ipService->isWhitelisted($ip),
        ]);
    }

    private function handleIpBlock(object $apiKey): void
    {
        if (!$this->requirePermission($apiKey, 'ip_manage')) {
            return;
        }

        $input = $this->getJsonInputOrFail();
        if ($input === null) {
            return;
        }

        $ip       = $input['ip'] ?? '';
        $reason   = trim((string)($input['reason'] ?? 'Blocked via API'));
        $reason   = mb_substr($reason !== '' ? $reason : 'Blocked via API', 0, 255);
        $duration = min(525600, max(1, (int)($input['duration_minutes'] ?? 1440)));

        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->sendError('Invalid IP address', 400);
            return;
        }

        $ipService = new IPService($this->db, $this->settings);
        $ipService->blockIp($ip, $reason, $duration);

        $this->sendJson(['success' => true, 'message' => 'IP blocked', 'ip' => $ip]);
    }

    private function handleIpUnblock(object $apiKey): void
    {
        if (!$this->requirePermission($apiKey, 'ip_manage')) {
            return;
        }

        $input = $this->getJsonInputOrFail();
        if ($input === null) {
            return;
        }

        $ip    = $input['ip'] ?? $_GET['ip'] ?? '';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->sendError('Invalid IP address', 400);
            return;
        }

        $ipService = new IPService($this->db, $this->settings);
        $ipService->unblockIp($ip);

        $this->sendJson(['success' => true, 'message' => 'IP unblocked', 'ip' => $ip]);
    }

    private function handleLog(object $apiKey): void
    {
        if (!$this->requirePermission($apiKey, 'log_read')) {
            return;
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;

        $total = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log`",
            [],
            1
        );

        $rows = $this->db->queryPrepared(
            "SELECT `id`, `ip_address`, `form_type`, `detection_method`, `spam_score`,
                    `action_taken`, `created_at`
             FROM `bbf_captcha_spam_log`
             ORDER BY `created_at` DESC
             LIMIT :lim OFFSET :off",
            ['lim' => max(1, (int)$perPage), 'off' => max(0, (int)$offset)],
            2
        );

        $this->sendJson([
            'data'  => is_array($rows) ? $rows : [],
            'total' => (int)($total->cnt ?? 0),
            'page'  => $page,
            'pages' => (int)ceil(((int)($total->cnt ?? 0)) / $perPage),
        ]);
    }

    private function handleMethods(object $apiKey): void
    {
        if (!$this->requirePermission($apiKey, 'stats')) {
            return;
        }

        $methodKeys = [
            'honeypot'          => 'honeypot_enabled',
            'timing'            => 'timing_enabled',
            'altcha'            => 'altcha_enabled',
            'turnstile'         => 'turnstile_enabled',
            'recaptcha'         => 'recaptcha_enabled',
            'friendly_captcha'  => 'friendly_captcha_enabled',
            'hcaptcha'          => 'hcaptcha_enabled',
            'ai_filter'         => 'ai_filter_enabled',
            'ip_protection'     => 'ip_protection_enabled',
            'rate_limit'        => 'rate_limit_enabled',
            'bot_detection'     => 'bot_detection_enabled',
        ];

        $methods = [];
        foreach ($methodKeys as $name => $key) {
            $methods[$name] = $this->settings->getBool($key);
        }

        $this->sendJson([
            'global_enabled' => $this->settings->getBool('global_enabled'),
            'methods'        => $methods,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function authenticate(): ?object
    {
        $rawKey = $_SERVER['HTTP_X_BBF_CAPTCHA_KEY']
               ?? $_SERVER['HTTP_AUTHORIZATION']
               ?? $_GET['api_key']
               ?? '';

        // Bearer Token Format
        if (str_starts_with($rawKey, 'Bearer ')) {
            $rawKey = substr($rawKey, 7);
        }

        if (empty($rawKey)) {
            return null;
        }

        return $this->apiKeyModel->validateKey($rawKey);
    }

    private function checkRateLimit(object $apiKey): bool
    {
        $limit = max(1, (int)($apiKey->rate_limit ?? 60));
        $hash  = (string)($apiKey->key_hash ?? '');

        // Bucket-Key: Kurzhash(API-Key) + ClientIP -> pro Key UND IP limitieren
        $ip     = preg_replace('/[^a-f0-9:.]/i', '', PluginHelper::getClientIp()) ?: 'unknown';
        $bucket = substr('apk_' . substr($hash, 0, 16) . '_' . $ip, 0, 45);
        $windowStart = date('Y-m-d H:i:00');

        $count = $this->db->queryPrepared(
            "SELECT SUM(`request_count`) AS total FROM `bbf_captcha_rate_limits`
             WHERE `ip_address` = :bucket AND `form_type` = 'api'
             AND `window_start` >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            ['bucket' => $bucket],
            1
        );

        $current = (int)($count->total ?? 0);

        $this->incrementApiRateLimitCounter($bucket, $windowStart);

        return $current < $limit;
    }

    private function incrementApiRateLimitCounter(string $bucket, string $windowStart): void
    {
        $existing = $this->db->queryPrepared(
            "SELECT `id` FROM `bbf_captcha_rate_limits`
             WHERE `ip_address` = :bucket AND `form_type` = 'api' AND `window_start` = :start
             ORDER BY `id` ASC LIMIT 1",
            ['bucket' => $bucket, 'start' => $windowStart],
            1
        );

        if ($existing !== null && isset($existing->id)) {
            $this->db->queryPrepared(
                "UPDATE `bbf_captcha_rate_limits`
                 SET `request_count` = `request_count` + 1
                 WHERE `id` = :id",
                ['id' => (int)$existing->id]
            );
            return;
        }

        $this->db->queryPrepared(
            "INSERT INTO `bbf_captcha_rate_limits` (`ip_address`, `form_type`, `window_start`, `request_count`)
             VALUES (:bucket, 'api', :start, 1)",
            ['bucket' => $bucket, 'start' => $windowStart]
        );
    }

    private function getJsonInput(): array
    {
        $this->jsonInputError = null;

        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > self::MAX_JSON_INPUT_BYTES) {
            $this->jsonInputError = 'Payload too large';
            return [];
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return $_POST;
        }

        if (strlen($raw) > self::MAX_JSON_INPUT_BYTES) {
            $this->jsonInputError = 'Payload too large';
            return [];
        }

        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'application/json') === false && !empty($_POST)) {
            return $_POST;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->jsonInputError = 'Invalid JSON payload';
            return [];
        }

        return $decoded;
    }

    private function getJsonInputOrFail(): ?array
    {
        $input = $this->getJsonInput();
        if ($this->jsonInputError !== null) {
            $statusCode = $this->jsonInputError === 'Payload too large' ? 413 : 400;
            $this->sendError($this->jsonInputError, $statusCode);
            return null;
        }

        return $input;
    }

    private function normalizeFormType(string $formType): ?string
    {
        $formType = strtolower(trim($formType));
        if ($formType === '' || !preg_match('/^[a-z0-9_-]{1,50}$/', $formType)) {
            return null;
        }

        return $formType;
    }

    private function requirePermission(object $apiKey, string $permission): bool
    {
        if ($this->apiKeyModel->hasPermission($apiKey, $permission)) {
            return true;
        }

        $this->sendError('Permission denied: ' . $permission, 403);
        return false;
    }

    /**
     * @param string[] $permissions
     */
    private function requireAnyPermission(object $apiKey, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->apiKeyModel->hasPermission($apiKey, $permission)) {
                return true;
            }
        }

        $this->sendError('Permission denied', 403);
        return false;
    }

    private function sendJson(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    private function sendError(string $message, int $statusCode = 400): void
    {
        $this->sendJson(['error' => $message], $statusCode);
    }
}
