<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\Events\Dispatcher;
use JTL\Events\Event;
use JTL\Shop;

/**
 * Bindet den Plugin-Cron an den nativen JTL-Cron an.
 *
 * Registriert nur die Event-Mappings (MAP_CRONJOB_TYPE / GET_AVAILABLE_CRONJOBS)
 * und repariert idempotent fehlende tcron-Zeilen – Git-Deploys führen keine
 * Plugin-Update-Routine aus, deshalb sichert ein einmaliger Boot-Install den
 * Cron-Eintrag ab. Kein Hotpath wird berührt.
 *
 * Orientiert an der bewährten Umsetzung des BBF-Ticket-Plugins.
 */
final class JtlCronBootstrapService
{
    private static bool $installedEnsured = false;

    /**
     * @param array<string, array{class: class-string, name: string, frequency: int}> $jobs
     */
    public function __construct(
        private Dispatcher $dispatcher,
        private JtlCronInstallerService $installer,
        private array $jobs
    ) {
    }

    public function registerEvents(): void
    {
        // Älteres JTL ohne Cron-Events: URL-Cron-Fallback bleibt aktiv.
        if (!defined(Event::class . '::MAP_CRONJOB_TYPE')
            || !defined(Event::class . '::GET_AVAILABLE_CRONJOBS')) {
            return;
        }

        $jobs = $this->jobs;
        $this->dispatcher->listen(Event::MAP_CRONJOB_TYPE, static function (array $args) use ($jobs): void {
            $type = (string) ($args['type'] ?? '');
            if ($type !== '' && isset($jobs[$type])) {
                $args['mapping'] = $jobs[$type]['class'];
            }
        });

        $installer = $this->installer;
        $this->dispatcher->listen(Event::GET_AVAILABLE_CRONJOBS, static function (array $args) use ($installer): void {
            if (!isset($args['jobs']) || !is_array($args['jobs'])) {
                return;
            }
            // WICHTIG: JTL erwartet hier ein string[] mit jobType-Strings – die
            // Cron-Admin-Vorlage (cron.tpl) rendert jeden Eintrag direkt als
            // {$type}. Ein Objekt würde beim String-Cast einen Fatal/500 auf
            // /admin/cron auslösen. Daher NUR den jobType-String anhängen.
            foreach (array_keys($installer->jobsWithRuntimeSettings()) as $type) {
                $args['jobs'][] = (string) $type;
            }
        });
    }

    public function ensureInstalledOnce(): void
    {
        if (self::$installedEnsured) {
            return;
        }
        self::$installedEnsured = true;
        try {
            $this->installer->install();
        } catch (\Throwable $e) {
            try {
                Shop::Container()->getLogService()->warning('BBF Captcha JtlCron:ensure: ' . $e->getMessage());
            } catch (\Throwable) {
                // bewusst still
            }
        }
    }
}
