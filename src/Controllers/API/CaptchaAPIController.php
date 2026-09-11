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
use Plugin\bbfdesign_captcha\src\Services\CockpitReviewRedactor;
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
    private PluginInterface $plugin;
    private DbInterface $db;
    private Setting $settings;
    private ApiKey $apiKeyModel;

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

        // Echte Plugin-Version melden (statt hartkodiert) – nützlich, um den live
        // ausgelieferten Build zu erkennen. Defensiv gekapselt: der Health-Check
        // darf nie an der Versionsermittlung scheitern.
        try {
            $version = (string)$this->plugin->getCurrentVersion();
        } catch (\Throwable $e) {
            $version = 'unknown';
        }
        if ($version === '') {
            $version = 'unknown';
        }
        header('X-BBF-Captcha-Version: ' . $version);

        // Health-Check braucht keine Auth
        if ($endpoint === 'health') {
            $this->sendJson(['status' => 'ok', 'version' => $version, 'timestamp' => time()]);
            return;
        }

        // Challenge-Endpoint braucht keine Auth (wird vom Frontend aufgerufen)
        if ($endpoint === 'challenge' && $method === 'GET') {
            $this->handleChallenge();
            return;
        }

        // Cron-Bereinigung: token-geschützt (für JTL-/Server-Cron), keine API-Key-Auth.
        // Aufruf: /bbfdesign-captcha/api/v1/cron?token=<cron_token>
        if ($endpoint === 'cron') {
            $token    = (string)($_GET['token'] ?? $_REQUEST['token'] ?? '');
            $expected = (string)$this->settings->get('cron_token');
            if ($expected === '' || !hash_equals($expected, $token)) {
                $this->sendError('Forbidden', 403);
                return;
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            // URL-Cron-Fallback: identische Arbeit wie der native JTL-Cron
            // (Spam-Wellen-Alarm + gedrosselte Bereinigung) über denselben Einstieg.
            $result = \Plugin\bbfdesign_captcha\src\Cron\CleanupCron::run();
            $this->sendJson(['status' => 'ok', 'result' => $result, 'timestamp' => time()]);
            return;
        }

        // Cockpit Remote-Review: serverseitig signiert, ohne API-Key, nur wenn
        // Cockpit + Review-Opt-in aktiv sind. Antwort bleibt redigiert.
        if (str_starts_with($endpoint, 'review-preview/')) {
            if ($method !== 'POST') {
                $this->sendError('Method not allowed', 405);
                return;
            }
            $this->handleReviewPreview(substr($endpoint, strlen('review-preview/')));
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
                $this->handleStats();
                break;

            case 'stats/today':
                $this->handleStatsToday();
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
                $this->handleMethods();
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
        if (!$this->apiKeyModel->hasPermission($apiKey, 'validate')) {
            $this->sendError('Permission denied: validate', 403);
            return;
        }

        $input    = $this->getJsonInput();
        $postData = $input['data'] ?? $input;
        $formType = $input['form_type'] ?? 'api';

        $captcha = new CaptchaService($this->plugin, $this->db, $this->settings);
        $result  = $captcha->validate($postData, $formType);

        $this->sendJson([
            'valid'  => $result->isValid(),
            'score'  => $result->getScore(),
            'reason' => $result->getReason(),
        ]);
    }

    private function handleStats(): void
    {
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

    private function handleStatsToday(): void
    {
        $logService = new SpamLogService($this->db, $this->settings);
        $kpis       = $logService->getKPIs();

        $this->sendJson([
            'blocked_today' => $kpis['blocked_today'],
            'date'          => date('Y-m-d'),
        ]);
    }

    private function handleIpCheck(object $apiKey): void
    {
        $input = $this->getJsonInput();
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
        if (!$this->apiKeyModel->hasPermission($apiKey, 'ip_manage')) {
            $this->sendError('Permission denied: ip_manage', 403);
            return;
        }

        $input    = $this->getJsonInput();
        $ip       = $input['ip'] ?? '';
        $reason   = $input['reason'] ?? 'Blocked via API';
        $duration = (int)($input['duration_minutes'] ?? 1440);

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
        if (!$this->apiKeyModel->hasPermission($apiKey, 'ip_manage')) {
            $this->sendError('Permission denied: ip_manage', 403);
            return;
        }

        $input = $this->getJsonInput();
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
             LIMIT {$perPage} OFFSET {$offset}",
            [],
            2
        );

        $this->sendJson([
            'data'  => is_array($rows) ? $rows : [],
            'total' => (int)($total->cnt ?? 0),
            'page'  => $page,
            'pages' => (int)ceil(((int)($total->cnt ?? 0)) / $perPage),
        ]);
    }

    private function handleMethods(): void
    {
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

    private function handleReviewPreview(string $localRef): void
    {
        $localRef = trim($localRef);
        if (!preg_match('/^\d{1,20}$/', $localRef)) {
            $this->sendError('Invalid review reference', 400);
            return;
        }
        if (!$this->settings->getBool('cockpit_enabled') || !$this->settings->getBool('cockpit_review_enabled')) {
            $this->sendError('Review preview disabled', 403);
            return;
        }

        $secret = $this->settings->get('cockpit_secret');
        if ($secret === '') {
            $this->sendError('Review preview not configured', 403);
            return;
        }

        $rawBody = file_get_contents('php://input');
        $rawBody = is_string($rawBody) ? $rawBody : '';
        if (!$this->verifyCockpitSignature($rawBody, $secret)) {
            $this->sendError('Forbidden', 403);
            return;
        }

        $input = json_decode($rawBody, true);
        if (!is_array($input) || !$this->verifyReviewToken($input, $secret, $localRef)) {
            $this->sendError('Invalid review token', 403);
            return;
        }

        $row = $this->db->queryPrepared(
            "SELECT `id`, `form_type`, `detection_method`, `spam_score`, `action_taken`, `request_data`, `created_at`
             FROM `bbf_captcha_spam_log`
             WHERE `id` = :id AND `action_taken` IN ('blocked', 'logged')
             LIMIT 1",
            ['id' => (int)$localRef],
            1
        );
        if ($row === null) {
            $this->sendError('Review entry not found', 404);
            return;
        }

        $requestData = (string)($row->request_data ?? '');
        $review = $requestData !== ''
            ? (new CockpitReviewRedactor())->reviewPayloadFromRequestDataJson($requestData, 300)
            : null;
        $reasons = $this->reasonsFromRequestData($requestData);
        if ($reasons === [] && !empty($row->detection_method)) {
            $reasons[] = (string)$row->detection_method;
        }

        $this->sendJson([
            'id'            => (string)$row->id,
            'occurredAt'    => $this->toIso((string)($row->created_at ?? '')),
            'formType'      => (string)($row->form_type ?? ''),
            'score'         => (int)($row->spam_score ?? 0),
            'action'        => strtoupper((string)($row->action_taken ?? 'logged')),
            'reasons'       => array_slice($reasons, 0, 20),
            'reviewSnippet' => is_array($review) ? $review['snippet'] : null,
            'reviewMeta'    => is_array($review) ? $review['meta'] : ['redacted' => true, 'source' => 'plugin'],
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function verifyCockpitSignature(string $rawBody, string $secret): bool
    {
        $signedAt = (string)($_SERVER['HTTP_X_SIGNED_AT'] ?? '');
        $sig      = (string)($_SERVER['HTTP_X_SIGNATURE'] ?? '');
        if ($signedAt === '' || $sig === '' || !ctype_digit($signedAt)) {
            return false;
        }
        $ts = (int)$signedAt;
        if (abs(time() - $ts) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody . '|' . $signedAt, $secret);
        return hash_equals($expected, $sig);
    }

    /**
     * @param array<string,mixed> $input
     */
    private function verifyReviewToken(array $input, string $secret, string $localRef): bool
    {
        $token = $input['token'] ?? null;
        if (!is_string($token) || !str_contains($token, '.')) {
            return false;
        }
        [$payload64, $sig] = explode('.', $token, 2);
        if ($payload64 === '' || $sig === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $payload64, $secret);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $json = $this->base64UrlDecode($payload64);
        if ($json === null) {
            return false;
        }
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return false;
        }
        $now = time();
        return ($payload['v'] ?? null) === 1
            && ($payload['aud'] ?? null) === 'captcha-cockpit-review-detail'
            && ($payload['scope'] ?? null) === 'redacted-preview'
            && (string)($payload['localRef'] ?? '') === $localRef
            && (string)($input['localRef'] ?? '') === $localRef
            && isset($payload['iat'], $payload['exp'])
            && (int)$payload['iat'] <= $now + 60
            && (int)$payload['exp'] >= $now;
    }

    private function base64UrlDecode(string $input): ?string
    {
        $padded = strtr($input, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($padded, true);
        return is_string($decoded) ? $decoded : null;
    }

    /**
     * @return array<int,string>
     */
    private function reasonsFromRequestData(string $requestDataJson): array
    {
        $data = json_decode($requestDataJson, true);
        if (!is_array($data) || !isset($data['_bbf_reason']) || !is_string($data['_bbf_reason'])) {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn (string $reason): string => mb_substr(trim($reason), 0, 300),
            explode(';', $data['_bbf_reason'])
        )));
    }

    private function toIso(string $value): string
    {
        $ts = strtotime($value);
        return date(DATE_ATOM, $ts !== false ? $ts : time());
    }

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
        $limit = (int)($apiKey->rate_limit ?? 60);
        $hash  = (string)($apiKey->key_hash ?? '');

        // Bucket-Key: Kurzhash(API-Key) + ClientIP -> pro Key UND IP limitieren
        $ip     = PluginHelper::getClientIp();
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

        $this->db->queryPrepared(
            "INSERT INTO `bbf_captcha_rate_limits` (`ip_address`, `form_type`, `window_start`, `request_count`)
             VALUES (:bucket, 'api', :start, 1)
             ON DUPLICATE KEY UPDATE `request_count` = `request_count` + 1",
            ['bucket' => $bucket, 'start' => $windowStart]
        );

        return $current < $limit;
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return $_POST;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $_POST;
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
