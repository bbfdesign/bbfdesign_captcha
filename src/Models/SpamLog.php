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
        ?array $requestData = null,
        ?string $reason = null
    ): void {
        // Eingereichte Felder (sanitisiert) + Begründung in einem JSON. Die
        // Begründung ist kein personenbezogenes Datum und wird auch dann
        // gespeichert, wenn das Daten-Logging deaktiviert ist ($requestData null).
        $payload = [];
        if ($requestData !== null) {
            $payload = \Plugin\bbfdesign_captcha\src\Helpers\PluginHelper::sanitizeRequestData($requestData);
        }
        if ($reason !== null && trim($reason) !== '') {
            $payload['_bbf_reason'] = mb_substr($reason, 0, 500);
        }
        $sanitizedData = empty($payload) ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);

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
        $this->db->queryPrepared(
            "DELETE FROM `bbf_captcha_spam_log` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL :days DAY)",
            ['days' => $retentionDays]
        );

        // JTL NiceDB::getAffectedRows() erwartet ein Statement – wir zählen vorher
        $remaining = $this->db->queryPrepared(
            "SELECT COUNT(*) AS cnt FROM `bbf_captcha_spam_log`",
            [],
            1
        );

        return 0; // Rückgabewert nicht kritisch
    }
}
