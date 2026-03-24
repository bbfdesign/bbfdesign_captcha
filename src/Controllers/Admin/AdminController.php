<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Controllers\Admin;

use JTL\DB\DbInterface;
use JTL\Plugin\PluginInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;

class AdminController
{
    private PluginInterface $plugin;
    private DbInterface $db;
    private Setting $settings;

    public function __construct(PluginInterface $plugin, DbInterface $db, Setting $settings)
    {
        $this->plugin   = $plugin;
        $this->db       = $db;
        $this->settings = $settings;
    }

    /**
     * Admin-AJAX-Action verarbeiten
     */
    public function handleAction(string $action, array $request): string
    {
        switch ($action) {
            case 'saveSetting':
                return $this->saveSetting($request);

            case 'saveSettings':
                return $this->saveSettings($request);

            case 'getDashboardData':
                return json_encode(['success' => true, 'data' => $this->getDashboardData()]);

            case 'getSpamLog':
                return $this->getSpamLog($request);

            case 'markFalsePositive':
                return $this->markFalsePositive($request);

            case 'blockIp':
                return $this->blockIp($request);

            case 'unblockIp':
                return $this->unblockIp($request);

            case 'addIpEntry':
                return $this->addIpEntry($request);

            case 'deleteIpEntry':
                return $this->deleteIpEntry($request);

            case 'getIpEntries':
                return $this->getIpEntries($request);

            case 'saveFormConfig':
                return $this->saveFormConfig($request);

            case 'getFormConfigs':
                return $this->getFormConfigs();

            case 'createApiKey':
                return $this->createApiKey($request);

            case 'deleteApiKey':
                return $this->deleteApiKey($request);

            case 'getApiKeys':
                return $this->getApiKeys();

            case 'addSpamWord':
                return $this->addSpamWord($request);

            case 'deleteSpamWord':
                return $this->deleteSpamWord($request);

            case 'getSpamWords':
                return $this->getSpamWords($request);

            case 'testAiFilter':
                return $this->testAiFilter($request);

            case 'exportSpamLog':
                return $this->exportSpamLog($request);

            case 'clearSpamLog':
                return $this->clearSpamLog($request);

            case 'regenerateHmacKey':
                return $this->regenerateHmacKey();

            default:
                return json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        }
    }

    /**
     * Dashboard-Daten zusammenstellen (via SpamLogService)
     */
    public function getDashboardData(int $days = 30): array
    {
        $logService = new \Plugin\bbfdesign_captcha\src\Services\SpamLogService($this->db, $this->settings);

        $kpis  = $logService->getKPIs();
        $trend = $logService->getTrend();

        // Aktive Methoden zählen
        $methodKeys = [
            'honeypot_enabled', 'timing_enabled', 'altcha_enabled',
            'turnstile_enabled', 'recaptcha_enabled', 'friendly_captcha_enabled',
            'hcaptcha_enabled', 'ai_filter_enabled',
        ];
        $activeMethods = 0;
        foreach ($methodKeys as $key) {
            if ($this->settings->getBool($key)) {
                $activeMethods++;
            }
        }

        // Auto-Cleanup ausführen (Pseudo-Cron)
        $logService->cleanup();

        // Spam-Welle prüfen
        $logService->checkSpamWaveAlert();

        return [
            'blocked_today'       => $kpis['blocked_today'],
            'blocked_total'       => $kpis['blocked_total'],
            'detection_rate'      => $kpis['detection_rate'],
            'active_methods'      => $activeMethods,
            'trend'               => $trend,
            'spam_history'        => $logService->getSpamHistory($days),
            'method_distribution' => $logService->getMethodDistribution($days),
            'top_forms'           => $logService->getTopForms($days),
            'top_ips'             => $logService->getTopBlockedIPs($days, 5),
            'recent_spam'         => $logService->getRecentSpam(20),
        ];
    }

    private function saveSetting(array $request): string
    {
        $key   = $request['key'] ?? '';
        $value = $request['value'] ?? '';
        $group = $request['group'] ?? 'general';

        if (empty($key)) {
            return json_encode(['success' => false, 'message' => 'Key fehlt']);
        }

        $this->settings->set($key, $value, $group);
        $this->settings->invalidateCache();

        return json_encode(['success' => true, 'message' => 'Einstellung gespeichert']);
    }

    private function saveSettings(array $request): string
    {
        $data = $request['settings'] ?? [];
        if (is_string($data)) {
            $data = json_decode($data, true) ?? [];
        }

        foreach ($data as $key => $value) {
            $this->settings->set($key, (string)$value);
        }
        $this->settings->invalidateCache();

        return json_encode(['success' => true, 'message' => 'Einstellungen gespeichert']);
    }

    private function getSpamLog(array $request): string
    {
        $page     = max(1, (int)($request['logPage'] ?? 1));
        $perPage  = 50;
        $offset   = ($page - 1) * $perPage;
        $where    = [];
        $params   = [];

        if (!empty($request['filter_form'])) {
            $where[]              = '`form_type` = :form';
            $params['form']       = $request['filter_form'];
        }
        if (!empty($request['filter_method'])) {
            $where[]              = '`detection_method` = :method';
            $params['method']     = $request['filter_method'];
        }
        if (!empty($request['filter_ip'])) {
            $where[]              = '`ip_address` LIKE :ip';
            $params['ip']         = '%' . $request['filter_ip'] . '%';
        }
        if (!empty($request['filter_action'])) {
            $where[]              = '`action_taken` = :action';
            $params['action']     = $request['filter_action'];
        }
        if (!empty($request['filter_from'])) {
            $where[]              = '`created_at` >= :from_date';
            $params['from_date']  = $request['filter_from'] . ' 00:00:00';
        }
        if (!empty($request['filter_to'])) {
            $where[]              = '`created_at` <= :to_date';
            $params['to_date']    = $request['filter_to'] . ' 23:59:59';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log` {$whereClause}",
            $params,
            1
        );

        $rows = $this->db->queryPrepared(
            "SELECT * FROM `bbf_captcha_spam_log` {$whereClause}
             ORDER BY `created_at` DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params,
            2
        );

        return json_encode([
            'success' => true,
            'data'    => is_array($rows) ? $rows : [],
            'total'   => (int)($total->cnt ?? 0),
            'page'    => $page,
            'pages'   => (int)ceil(((int)($total->cnt ?? 0)) / $perPage),
        ]);
    }

    private function markFalsePositive(array $request): string
    {
        $id      = (int)($request['id'] ?? 0);
        $isSpam  = (int)($request['is_spam'] ?? 0);

        if ($id <= 0) {
            return json_encode(['success' => false, 'message' => 'ID fehlt']);
        }

        $this->db->queryPrepared(
            "UPDATE `bbf_captcha_spam_log` SET `is_false_positive` = :fp WHERE `id` = :id",
            ['fp' => $isSpam === 0 ? 1 : 0, 'id' => $id]
        );

        // KI-Lernfunktion: Wenn gespeicherte Request-Daten vorhanden, daraus lernen
        $entry = $this->db->queryPrepared(
            "SELECT `request_data` FROM `bbf_captcha_spam_log` WHERE `id` = :id",
            ['id' => $id],
            1
        );

        if ($entry !== null && !empty($entry->request_data)) {
            $data = json_decode($entry->request_data, true);
            if (is_array($data)) {
                $text = implode(' ', array_filter($data, 'is_string'));
                if (!empty(trim($text))) {
                    $aiService = new \Plugin\bbfdesign_captcha\src\Services\AISpamService($this->db, $this->settings);
                    $aiService->learnFromFeedback($text, $isSpam === 1);
                }
            }
        }

        return json_encode(['success' => true]);
    }

    private function blockIp(array $request): string
    {
        $ip     = trim($request['ip'] ?? '');
        $reason = trim($request['reason'] ?? 'Manual block from spam log');

        if (empty($ip)) {
            return json_encode(['success' => false, 'message' => 'IP fehlt']);
        }

        $this->db->queryPrepared(
            "INSERT IGNORE INTO `bbf_captcha_ip_entries` (`ip_address`, `entry_type`, `reason`, `auto_added`)
             VALUES (:ip, 'blacklist', :reason, 0)",
            ['ip' => $ip, 'reason' => $reason]
        );

        return json_encode(['success' => true, 'message' => 'IP gesperrt']);
    }

    private function unblockIp(array $request): string
    {
        $ip = trim($request['ip'] ?? '');

        if (empty($ip)) {
            return json_encode(['success' => false, 'message' => 'IP fehlt']);
        }

        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_ip_entries` WHERE `ip_address` = :ip AND `entry_type` = 'blacklist'",
            ['ip' => $ip]
        );

        return json_encode(['success' => true, 'message' => 'IP entsperrt']);
    }

    private function addIpEntry(array $request): string
    {
        $ip    = trim($request['ip'] ?? '');
        $type  = $request['entry_type'] ?? 'blacklist';
        $range = trim($request['ip_range'] ?? '');
        $reason = trim($request['reason'] ?? '');

        if (empty($ip)) {
            return json_encode(['success' => false, 'message' => 'IP fehlt']);
        }
        if (!in_array($type, ['blacklist', 'whitelist'], true)) {
            return json_encode(['success' => false, 'message' => 'Ungültiger Typ']);
        }

        $this->db->queryPrepared(
            "INSERT INTO `bbf_captcha_ip_entries` (`ip_address`, `ip_range`, `entry_type`, `reason`, `auto_added`)
             VALUES (:ip, :range, :type, :reason, 0)",
            ['ip' => $ip, 'range' => $range ?: null, 'type' => $type, 'reason' => $reason]
        );

        return json_encode(['success' => true, 'message' => 'IP-Eintrag hinzugefügt']);
    }

    private function deleteIpEntry(array $request): string
    {
        $id = (int)($request['id'] ?? 0);

        if ($id <= 0) {
            return json_encode(['success' => false, 'message' => 'ID fehlt']);
        }

        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_ip_entries` WHERE `id` = :id",
            ['id' => $id]
        );

        return json_encode(['success' => true]);
    }

    private function getIpEntries(array $request): string
    {
        $type = $request['entry_type'] ?? '';
        $where = '';
        $params = [];

        if (in_array($type, ['blacklist', 'whitelist'], true)) {
            $where = 'WHERE `entry_type` = :type';
            $params['type'] = $type;
        }

        $rows = $this->db->queryPrepared(
            "SELECT * FROM `bbf_captcha_ip_entries` {$where} ORDER BY `created_at` DESC",
            $params,
            2
        );

        return json_encode(['success' => true, 'data' => is_array($rows) ? $rows : []]);
    }

    private function saveFormConfig(array $request): string
    {
        $formType   = $request['form_type'] ?? '';
        $methods    = $request['methods'] ?? '[]';
        $threshold  = (int)($request['score_threshold'] ?? 60);
        $actionSpam = $request['action_on_spam'] ?? 'both';
        $isActive   = (int)($request['is_active'] ?? 1);

        if (empty($formType)) {
            return json_encode(['success' => false, 'message' => 'Formular-Typ fehlt']);
        }

        $this->db->queryPrepared(
            "INSERT INTO `bbf_captcha_form_config` (`form_type`, `methods`, `score_threshold`, `action_on_spam`, `is_active`)
             VALUES (:type, :methods, :threshold, :action, :active)
             ON DUPLICATE KEY UPDATE `methods` = :methods2, `score_threshold` = :threshold2,
             `action_on_spam` = :action2, `is_active` = :active2",
            [
                'type'       => $formType,
                'methods'    => $methods,
                'threshold'  => $threshold,
                'action'     => $actionSpam,
                'active'     => $isActive,
                'methods2'   => $methods,
                'threshold2' => $threshold,
                'action2'    => $actionSpam,
                'active2'    => $isActive,
            ]
        );

        return json_encode(['success' => true, 'message' => 'Formular-Konfiguration gespeichert']);
    }

    private function getFormConfigs(): string
    {
        $rows = $this->db->queryPrepared(
            "SELECT * FROM `bbf_captcha_form_config` ORDER BY `form_type` ASC",
            [],
            2
        );

        return json_encode(['success' => true, 'data' => is_array($rows) ? $rows : []]);
    }

    private function createApiKey(array $request): string
    {
        $name        = trim($request['key_name'] ?? '');
        $permissions = $request['permissions'] ?? '["validate","challenge"]';

        if (empty($name)) {
            return json_encode(['success' => false, 'message' => 'Name fehlt']);
        }

        // Schlüssel generieren
        $rawKey  = 'bbf_' . bin2hex(random_bytes(24));
        $keyHash = hash('sha256', $rawKey);

        $this->db->queryPrepared(
            "INSERT INTO `bbf_captcha_api_keys` (`key_name`, `key_hash`, `permissions`)
             VALUES (:name, :hash, :perms)",
            ['name' => $name, 'hash' => $keyHash, 'perms' => $permissions]
        );

        return json_encode([
            'success' => true,
            'key'     => $rawKey,
            'message' => 'API-Key erstellt. Bitte jetzt kopieren!',
        ]);
    }

    private function deleteApiKey(array $request): string
    {
        $id = (int)($request['id'] ?? 0);

        if ($id <= 0) {
            return json_encode(['success' => false, 'message' => 'ID fehlt']);
        }

        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_api_keys` WHERE `id` = :id",
            ['id' => $id]
        );

        return json_encode(['success' => true]);
    }

    private function getApiKeys(): string
    {
        $rows = $this->db->queryPrepared(
            "SELECT `id`, `key_name`, `permissions`, `rate_limit`, `is_active`, `last_used_at`, `created_at`
             FROM `bbf_captcha_api_keys`
             ORDER BY `created_at` DESC",
            [],
            2
        );

        return json_encode(['success' => true, 'data' => is_array($rows) ? $rows : []]);
    }

    private function addSpamWord(array $request): string
    {
        $word     = trim($request['word'] ?? '');
        $weight   = (int)($request['weight'] ?? 25);
        $category = $request['category'] ?? 'spam';

        if (empty($word)) {
            return json_encode(['success' => false, 'message' => 'Wort fehlt']);
        }

        $this->db->queryPrepared(
            "INSERT IGNORE INTO `bbf_captcha_spam_words` (`word`, `category`, `weight`)
             VALUES (:word, :cat, :weight)",
            ['word' => mb_strtolower($word), 'cat' => $category, 'weight' => $weight]
        );

        return json_encode(['success' => true]);
    }

    private function deleteSpamWord(array $request): string
    {
        $id = (int)($request['id'] ?? 0);

        if ($id <= 0) {
            return json_encode(['success' => false, 'message' => 'ID fehlt']);
        }

        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_spam_words` WHERE `id` = :id",
            ['id' => $id]
        );

        return json_encode(['success' => true]);
    }

    private function getSpamWords(array $request): string
    {
        $category = $request['category'] ?? '';
        $where    = '';
        $params   = [];

        if (in_array($category, ['spam', 'ham'], true)) {
            $where            = 'WHERE `category` = :cat';
            $params['cat']    = $category;
        }

        $rows = $this->db->queryPrepared(
            "SELECT * FROM `bbf_captcha_spam_words` {$where} ORDER BY `weight` DESC, `word` ASC",
            $params,
            2
        );

        return json_encode(['success' => true, 'data' => is_array($rows) ? $rows : []]);
    }

    private function testAiFilter(array $request): string
    {
        $text  = $request['text'] ?? '';
        $email = $request['email'] ?? null;

        if (empty($text)) {
            return json_encode(['success' => false, 'message' => 'Text fehlt']);
        }

        $aiService = new \Plugin\bbfdesign_captcha\src\Services\AISpamService($this->db, $this->settings);
        $result    = $aiService->analyze($text, $email);

        return json_encode([
            'success' => true,
            'score'   => $result['score'],
            'verdict' => $result['verdict'],
            'details' => $result['details'],
        ]);
    }

    private function exportSpamLog(array $request): string
    {
        $logService = new \Plugin\bbfdesign_captcha\src\Services\SpamLogService($this->db, $this->settings);
        $data       = $logService->exportCsv();

        return json_encode(['success' => true, 'data' => $data]);
    }

    private function clearSpamLog(array $request): string
    {
        $logService = new \Plugin\bbfdesign_captcha\src\Services\SpamLogService($this->db, $this->settings);
        $days       = (int)($request['days'] ?? 0);

        if ($days > 0) {
            $deleted = $logService->clearOlderThan($days);
            return json_encode(['success' => true, 'message' => 'Spam-Log bereinigt']);
        }

        $logService->clearAll();
        return json_encode(['success' => true, 'message' => 'Spam-Log komplett geleert']);
    }

    private function regenerateHmacKey(): string
    {
        $newKey = bin2hex(random_bytes(32));
        $this->settings->set('altcha_hmac_key', $newKey, 'altcha');
        $this->settings->set('altcha_hmac_rotated_at', date('Y-m-d H:i:s'), 'altcha');

        return json_encode(['success' => true, 'message' => 'HMAC-Key neu generiert']);
    }
}
