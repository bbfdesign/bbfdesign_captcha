<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Cron;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Services\SpamLogService;

/**
 * Nativer JTL-Cron-Job für BBF Captcha.
 *
 * Übernimmt die wiederkehrenden Wartungsaufgaben über das Shop-Cron-System
 * statt über den bisherigen Pseudo-Cron (Trigger durch normalen Traffic):
 *
 *  1. Spam-Wellen-Alarm prüfen und ggf. per Shop-Mailsystem benachrichtigen
 *     (selbst gedrosselt: höchstens 1×/Stunde).
 *  2. Log-Aufbewahrung sowie Rate-Limit- und IP-Block-Cleanup (selbst gedrosselt
 *     auf das eingestellte Intervall, Standard 24 h).
 *
 * Beide Teilaufgaben drosseln sich selbst, daher ist eine kurze Cron-Frequenz
 * (Standard 15 min) unkritisch und sorgt für zeitnahe Wellen-Alarme.
 */
final class CleanupCron extends Job
{
    public const JOB_TYPE = 'bbf_captcha_maintenance';

    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);
        try {
            self::run();
        } catch (\Throwable $e) {
            self::logWarning('CleanupCron:start', $e->getMessage());
        }
        $this->setFinished(true);

        return $this;
    }

    /**
     * Direkt aufrufbar – wird vom nativen JTL-Cron, vom URL-Cron-Fallback und
     * von Tests genutzt.
     *
     * @return array{wave_checked: bool, cleanup_ran: bool}
     */
    public static function run(): array
    {
        $db       = Shop::Container()->getDB();
        $settings = new Setting($db);
        $service  = new SpamLogService($db, $settings);

        // 1) Spam-Wellen-Alarm (Mail über das Shop-Mailsystem, intern 1h-Cooldown).
        try {
            $service->checkSpamWaveAlert();
        } catch (\Throwable $e) {
            self::logWarning('CleanupCron:wave', $e->getMessage());
        }

        // 2) Bereinigung (intern auf cleanup_interval_hours gedrosselt).
        $cleanupRan = false;
        try {
            $cleanupRan = $service->runIfDue();
        } catch (\Throwable $e) {
            self::logWarning('CleanupCron:cleanup', $e->getMessage());
        }

        return ['wave_checked' => true, 'cleanup_ran' => $cleanupRan];
    }

    private static function logWarning(string $context, string $message): void
    {
        try {
            Shop::Container()->getLogService()->warning('BBF Captcha ' . $context . ': ' . $message);
        } catch (\Throwable) {
            // Logging darf den Cron-Lauf niemals kippen.
        }
    }
}
