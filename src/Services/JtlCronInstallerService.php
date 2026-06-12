<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Cron\CleanupCron;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Idempotente Registrierung des nativen JTL-Cron-Jobs in der Tabelle `tcron`.
 *
 * Live-sicher: ändert ausschließlich die Cron-Metadaten der plugin-eigenen
 * jobType-Werte, löscht keine Plugin-Daten. Fehler werden geloggt, nie
 * propagiert (ein gescheitertes Cron-Setup darf das Plugin nicht kippen).
 *
 * Orientiert an der bewährten Umsetzung des BBF-Ticket-Plugins.
 */
final class JtlCronInstallerService
{
    /**
     * @param array<string, array{class: class-string, name: string, frequency: int}> $jobs
     */
    public function __construct(
        private DbInterface $db,
        private array $jobs
    ) {
    }

    public function install(): void
    {
        if (!$this->tableExists('tcron')) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($this->jobsWithRuntimeSettings() as $type => $meta) {
            try {
                $existing = $this->db->queryPrepared(
                    'SELECT cronID, name, frequency FROM tcron WHERE jobType = :type LIMIT 1',
                    ['type' => $type],
                    1
                );
                if ($existing !== null && $existing !== false) {
                    $currentName      = (string) ($existing->name ?? '');
                    $currentFrequency = (int) ($existing->frequency ?? -1);
                    if ($currentName !== $meta['name'] || $currentFrequency !== (int) $meta['frequency']) {
                        // Frequenz/Name angleichen UND nextStart auf jetzt zurücksetzen,
                        // damit eine korrigierte (z. B. zuvor zu weit in der Zukunft
                        // geplante) Ausführung sofort wieder greift.
                        $this->db->queryPrepared(
                            'UPDATE tcron SET name = :name, frequency = :frequency, nextStart = :now WHERE cronID = :id',
                            [
                                'name'      => $meta['name'],
                                'frequency' => (int) $meta['frequency'],
                                'now'       => $now,
                                'id'        => (int) ($existing->cronID ?? 0),
                            ],
                            3
                        );
                    }
                    continue;
                }

                $this->db->insert('tcron', (object) [
                    'jobType'      => $type,
                    'name'         => $meta['name'],
                    'frequency'    => $meta['frequency'],
                    'startDate'    => $now,
                    'startTime'    => date('H:i:s'),
                    'nextStart'    => $now,
                    'foreignKeyID' => '_DBNULL_',
                    'foreignKey'   => '_DBNULL_',
                    'tableName'    => '_DBNULL_',
                    'lastStart'    => '_DBNULL_',
                    'lastFinish'   => '_DBNULL_',
                ]);
            } catch (\Throwable $e) {
                $this->logWarning('install:' . $type, $e->getMessage());
            }
        }
    }

    public function remove(): void
    {
        $types = array_keys($this->jobs);
        if ($types === []) {
            return;
        }

        $placeholders = [];
        $params       = [];
        foreach ($types as $i => $type) {
            $key            = 'type' . $i;
            $placeholders[] = ':' . $key;
            $params[$key]   = $type;
        }
        $list = implode(',', $placeholders);

        try {
            $this->db->queryPrepared('DELETE FROM tjobqueue WHERE jobType IN (' . $list . ')', $params, 3);
            $this->db->queryPrepared('DELETE FROM tcron WHERE jobType IN (' . $list . ')', $params, 3);
        } catch (\Throwable $e) {
            $this->logWarning('remove', $e->getMessage());
        }
    }

    /**
     * Frequenzen werden zur Laufzeit aus den Settings aufgelöst (überschreibbar
     * ohne Migration), mit sicheren Grenzen.
     *
     * @return array<string, array{class: class-string, name: string, frequency: int}>
     */
    public function jobsWithRuntimeSettings(): array
    {
        $jobs = $this->jobs;
        if (isset($jobs[CleanupCron::JOB_TYPE])) {
            $jobs[CleanupCron::JOB_TYPE]['frequency'] = $this->frequencyFromSettings('cron_frequency_hours', 1);
        }

        return $jobs;
    }

    /**
     * JTL-Cron-Frequenz in STUNDEN (0 = bei jedem Cron-Lauf). Begrenzt auf
     * [0, 168] (max. 1 Woche), damit kein Tipp-/Einheitsfehler den Job faktisch
     * stilllegt.
     */
    private function frequencyFromSettings(string $key, int $fallback): int
    {
        try {
            $frequency = (new Setting($this->db))->getInt($key, $fallback);

            return max(0, min(168, $frequency));
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $row = $this->db->queryPrepared(
                'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1',
                ['table' => $table],
                1
            );

            return $row !== null && $row !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function logWarning(string $context, string $message): void
    {
        try {
            Shop::Container()->getLogService()->warning('BBF Captcha JtlCron:' . $context . ': ' . $message);
        } catch (\Throwable) {
            // bewusst still
        }
    }
}
