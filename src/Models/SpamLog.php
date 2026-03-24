<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Models;

use JTL\DB\DbInterface;

class SpamLog
{
    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Neuen Spam-Log-Eintrag erstellen
     */
    public function log(
        string $ipAddress,
        string $formType,
        string $detectionMethod,
        int $spamScore,
        string $actionTaken,
        ?string $userAgent = null,
        ?array $requestData = null
    ): void {
        $sanitizedData = null;
        if ($requestData !== null) {
            $sanitizedData = json_encode(
                \Plugin\bbfdesign_captcha\src\Helpers\PluginHelper::sanitizeRequestData($requestData),
                JSON_UNESCAPED_UNICODE
            );
        }

        $this->db->queryPrepared(
            "INSERT INTO `bbf_captcha_spam_log`
             (`ip_address`, `form_type`, `detection_method`, `spam_score`, `action_taken`, `user_agent`, `request_data`)
             VALUES (:ip, :form, :method, :score, :action, :ua, :data)",
            [
                'ip'     => $ipAddress,
                'form'   => $formType,
                'method' => $detectionMethod,
                'score'  => $spamScore,
                'action' => $actionTaken,
                'ua'     => $userAgent ? mb_substr($userAgent, 0, 500) : null,
                'data'   => $sanitizedData,
            ]
        );
    }

    /**
     * Auto-Cleanup: Alte Einträge löschen
     */
    public function cleanup(int $retentionDays): int
    {
        $result = $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_spam_log` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL :days DAY)",
            ['days' => $retentionDays]
        );

        return (int)$this->db->getAffectedRows();
    }
}
