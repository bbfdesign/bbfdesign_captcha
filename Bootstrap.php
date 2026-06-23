<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\bbfdesign_captcha\src\Controllers\Admin\AdminController;
use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Hooks\FormProtection;
use Plugin\bbfdesign_captcha\src\Hooks\SmartyOutputFilter;
use Plugin\bbfdesign_captcha\src\Hooks\IncludeAssets;
use Plugin\bbfdesign_captcha\src\Cron\CleanupCron;
use Plugin\bbfdesign_captcha\src\Services\JtlCronBootstrapService;
use Plugin\bbfdesign_captcha\src\Services\JtlCronInstallerService;

class Bootstrap extends Bootstrapper
{
    private ?Setting $settingsModel = null;
    private ?FormProtection $formProtection = null;

    /**
     * Plugin-eigene Jobs für den nativen JTL-Cron.
     *
     * WICHTIG: `frequency` ist in JTL die Anzahl STUNDEN bis zum nächsten Lauf
     * (Queue.php: nextStart->modify('+frequency hours'); 0 = bei jedem Cron-Lauf).
     * Die Frequenz wird zur Laufzeit aus den Settings aufgelöst
     * (JtlCronInstallerService::jobsWithRuntimeSettings). 1 h ist unkritisch, da
     * sich Wellen-Alarm (1h-Cooldown) und Cleanup (24h) ohnehin selbst drosseln.
     *
     * @var array<string, array{class: class-string, name: string, frequency: int}>
     */
    private const CRON_JOBS = [
        CleanupCron::JOB_TYPE => [
            'class'     => CleanupCron::class,
            'name'      => 'BBF Captcha Wartung (Spam-Welle & Cleanup)',
            'frequency' => 1,
        ],
    ];

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        try {
            $plugin = $this->getPlugin();
            $db     = Shop::Container()->getDB();
            $this->settingsModel = new Setting($db);

            // Cron-Token für den URL-Cron-Endpoint sicherstellen (auch Bestand).
            if (empty($this->settingsModel->get('cron_token'))) {
                $this->settingsModel->set('cron_token', bin2hex(random_bytes(16)));
            }

            // Nativen JTL-Cron anbinden – unbedingt (auch im Cron-/Backend-Kontext,
            // damit der Cron-Runner den jobType auf unsere Klasse mappen kann).
            $this->registerCronEvents($dispatcher);
            $this->ensureCronJobsInstalled();

            // Fallback ohne eingerichteten Cron: gedrosselte Selbstbereinigung über
            // normalen Traffic (höchstens 1×/Intervall). Mit nativem Cron ist das
            // redundant, schadet aber nicht und hält Shops ohne Cron sauber.
            (new \Plugin\bbfdesign_captcha\src\Services\SpamLogService($db, $this->settingsModel))->runIfDue();

            // Zentrale Telemetrie ans CaptchaCockpit (Default AUS, fail-open,
            // DSGVO-minimiert). Gedrosselt; sendet nur, wenn konfiguriert.
            $cockpitTelemetry = new \Plugin\bbfdesign_captcha\src\Services\CockpitTelemetryService($db, $this->settingsModel);
            $cockpitTelemetry->runIfDue();
            $cockpitTelemetry->registerIfDue();

            // Zentrales Ruleset vom Cockpit ziehen (Default AUS, fail-safe). Wendet
            // Schwellen/Token-Heuristik/Listen ohne Plugin-Update an.
            (new \Plugin\bbfdesign_captcha\src\Services\RemoteRulesetService($db, $this->settingsModel))->pullIfDue();

            // CAP-12: Auto-Lizenzierung (keyless, by-domain). Gedrosselt (12 h) + fail-open;
            // greift nur, wenn ein Signing-Secret konfiguriert ist. Schaltet den Schutz NIE ab –
            // ein Lizenzproblem wird nur als Backend-Hinweis angezeigt.
            try {
                (new \Plugin\bbfdesign_captcha\src\Services\LicenseService($db, $this->settingsModel))->checkIfDue();
            } catch (\Throwable $e) {
                // fail-open: der Lizenz-Check darf den Shop nie beeinträchtigen
            }

            // Frontend: Hooks + API-Routen registrieren
            if (Shop::isFrontend()) {
                $this->registerFrontendHooks($dispatcher);
                $this->registerApiRoutes($dispatcher);
                $this->registerNativeCaptcha($plugin, $db);
            }
        } catch (\Throwable $e) {
            Shop::Container()->getLogService()->error(
                'BBF Captcha boot error: ' . $e->getMessage()
            );
        }
    }

    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        $plugin   = $this->getPlugin();
        $db       = Shop::Container()->getDB();
        $settings = new Setting($db);
        $adminLang = $_SESSION['AdminAccount']->language ?? 'ger';

        // AJAX-Request: direkt ausgeben und beenden
        if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
            header('Content-Type: application/json; charset=utf-8');

            // Admin-Auth: nur eingeloggte Admins mit aktivem Backend-Account
            if (!$this->isAdminAuthenticated()) {
                http_response_code(401);
                echo json_encode(
                    ['success' => false, 'message' => 'Unauthorized'],
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                );
                exit;
            }

            // CSRF-Token prüfen (JTL-Standard: jtl_token)
            if (!$this->isValidCsrfToken()) {
                http_response_code(403);
                echo json_encode(
                    ['success' => false, 'message' => 'Invalid CSRF token'],
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                );
                exit;
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            if ($_REQUEST['action'] === 'getPage') {
                echo $this->handleGetPage(
                    $_REQUEST['page'] ?? '',
                    $smarty,
                    $plugin,
                    $db,
                    $settings,
                    $adminLang
                );
            } else {
                $controller = new AdminController($plugin, $db, $settings);
                echo $controller->handleAction($_REQUEST['action'], $_REQUEST);
            }
            exit;
        }

        $langVars = $plugin->getLocalization();

        $smarty->assign([
            'plugin'        => $plugin,
            'pluginId'      => $plugin->getPluginID(),
            'postURL'       => $plugin->getPaths()->getBackendURL(),
            'adminUrl'      => $plugin->getPaths()->getAdminURL(),
            'ShopURL'       => Shop::getURL(),
            'adminLang'     => $adminLang,
            'langVars'      => $langVars,
            'pluginVersion' => $plugin->getCurrentVersion(),
        ]);

        return $smarty->fetch($plugin->getPaths()->getAdminPath() . 'templates/index.tpl');
    }

    public function installed(): void
    {
        parent::installed();

        $db       = Shop::Container()->getDB();
        $settings = new Setting($db);

        // ALTCHA HMAC-Key generieren wenn noch nicht vorhanden
        $hmacKey = $settings->get('altcha_hmac_key');
        if (empty($hmacKey)) {
            $settings->set('altcha_hmac_key', bin2hex(random_bytes(32)));
            $settings->set('altcha_hmac_rotated_at', date('Y-m-d H:i:s'));
        }

        // Cron-Token für den URL-Cron-Endpoint
        if (empty($settings->get('cron_token'))) {
            $settings->set('cron_token', bin2hex(random_bytes(16)));
        }

        // Nativen JTL-Cron-Job in tcron eintragen.
        $this->installCronJobs();
    }

    public function updated($oldVersion, $newVersion): void
    {
        parent::updated($oldVersion, $newVersion);
        // Cron-Job-Metadaten (Name/Frequenz) bei Update idempotent angleichen.
        $this->installCronJobs();
    }

    public function uninstalled(bool $deleteData = true): void
    {
        // Cron-Job sauber entfernen (tcron + laufende tjobqueue-Einträge).
        $this->removeCronJobs();
        parent::uninstalled($deleteData);
    }

    // ─── Nativer JTL-Cron ───────────────────────────────────────────────

    private static function cronInstaller(): JtlCronInstallerService
    {
        return new JtlCronInstallerService(Shop::Container()->getDB(), self::CRON_JOBS);
    }

    private static function cronBootstrap(Dispatcher $dispatcher): JtlCronBootstrapService
    {
        return new JtlCronBootstrapService($dispatcher, self::cronInstaller(), self::CRON_JOBS);
    }

    /**
     * Hängt den Plugin-Job an den nativen JTL-Cron. JTL fragt unbekannte
     * jobType-Werte über MAP_CRONJOB_TYPE ab; dort liefern wir die Job-Klasse.
     */
    private function registerCronEvents(Dispatcher $dispatcher): void
    {
        self::cronBootstrap($dispatcher)->registerEvents();
    }

    /**
     * Git-Deploys führen keine Plugin-Update-Routine aus. Deshalb reparieren wir
     * fehlende tcron-Zeilen beim Boot idempotent, aber nur einmal pro PHP-Prozess.
     */
    private function ensureCronJobsInstalled(): void
    {
        self::cronBootstrap(Dispatcher::getInstance())->ensureInstalledOnce();
    }

    private function installCronJobs(): void
    {
        self::cronInstaller()->install();
    }

    private function removeCronJobs(): void
    {
        self::cronInstaller()->remove();
    }

    /**
     * AJAX Page-Loading: Einzelne Admin-Seiten als HTML-Fragment zurückgeben
     */
    private function handleGetPage(
        string $page,
        JTLSmarty $smarty,
        $plugin,
        $db,
        Setting $settings,
        string $adminLang
    ): string {
        $langVars     = $plugin->getLocalization();
        $templatePath = $plugin->getPaths()->getAdminPath() . 'templates/';

        $allSettings = $settings->getAll();
        // Secrets niemals an den Browser geben (DSGVO/Sicherheit). Die
        // Lizenz-Sektion arbeitet write-only über eigene AJAX-Actions.
        $publicSettings = $allSettings;
        foreach (['forgepush_signing_secret', 'forgepush_license_key', 'cockpit_secret', 'cockpit_pepper', 'cockpit_enrollment_secret'] as $secretKey) {
            unset($publicSettings[$secretKey]);
        }
        $smarty->assign([
            'plugin'        => $plugin,
            'pluginId'      => $plugin->getPluginID(),
            'postURL'       => $plugin->getPaths()->getBackendURL(),
            'adminUrl'      => $plugin->getPaths()->getAdminURL(),
            'ShopURL'       => Shop::getURL(),
            'adminLang'     => $adminLang,
            'langVars'      => $langVars,
            'pluginVersion' => $plugin->getCurrentVersion(),
            'settings'      => $publicSettings,
            'settingsJson'  => json_encode($publicSettings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ]);

        $templateMap = [
            'dashboard'          => 'dashboard.tpl',
            'protection_methods' => 'protection_methods.tpl',
            'form_protection'    => 'form_protection.tpl',
            'ai_spam_filter'     => 'ai_spam_filter.tpl',
            'llm_check'          => 'llm_check.tpl',
            'ip_management'      => 'ip_management.tpl',
            'log'                => 'log.tpl',
            'api'                => 'api.tpl',
            'settings'           => 'settings.tpl',
            'css_editor'         => 'css_editor.tpl',
            'documentation'      => 'documentation.tpl',
            'changelog'          => 'changelog.tpl',
        ];

        $template = $templateMap[$page] ?? 'dashboard.tpl';

        // Seiten-spezifische Daten laden
        if ($page === 'dashboard') {
            $controller = new AdminController($plugin, $db, $settings);
            $dashboardData = $controller->getDashboardData();
            $smarty->assign('dashboardData', $dashboardData);
            $smarty->assign('dashboardDataJson', json_encode(
                $dashboardData,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ));
        } elseif ($page === 'form_protection') {
            // Formularkonfigurationen serverseitig vorladen – robuste Defaults + DB,
            // damit die Liste auch ohne/vor dem AJAX (und bei AJAX-Fehler) gefüllt ist.
            $controller  = new AdminController($plugin, $db, $settings);
            $formConfigs = $controller->getFormConfigsData();
            $smarty->assign('formConfigsJson', json_encode(
                $formConfigs,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ));
        }

        try {
            $content = $smarty->fetch($templatePath . $template);
        } catch (\Throwable $e) {
            $content = '<div class="bbf-alert bbf-alert-danger">Fehler beim Laden: '
                     . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return json_encode(['content' => $content]);
    }

    /**
     * Frontend-Hooks registrieren
     */
    private function registerFrontendHooks(Dispatcher $dispatcher): void
    {
        $plugin = $this->getPlugin();
        $db     = Shop::Container()->getDB();

        // Smarty Output Filter – Honeypot/Timing injizieren + Assets einbinden
        // WICHTIG: $args['output'] ist eine Referenz auf den Smarty-Output (nicht 'original'!)
        $dispatcher->listen('shop.hook.' . \HOOK_SMARTY_OUTPUTFILTER, function (array &$args) use ($plugin) {
            if (!$this->protectionActive()) {
                return;
            }

            try {
                $assetHook    = new IncludeAssets($plugin, $this->settingsModel);
                $outputFilter = new SmartyOutputFilter($this->settingsModel);

                // Älteres JTL: roher HTML-String unter 'output'.
                if (isset($args['output']) && is_string($args['output'])) {
                    $html = &$args['output'];
                    $html = $assetHook->includeIfNeeded($html);
                    $html = $outputFilter->filter($html);
                    return;
                }

                // JTL 5.6/5.7: phpQuery-Dokument unter 'document'
                // (executeHook(140, ['smarty'=>..., 'document'=>$doc])).
                if (isset($args['document']) && is_object($args['document'])) {
                    $assetHook->injectIntoDocument($args['document']);
                    $outputFilter->filterDocument($args['document']);
                }
            } catch (\Throwable $e) {
                // Der Output-Filter darf die Seite niemals zerstören – im Fehlerfall
                // bleibt der Output unverändert.
                if ($this->settingsModel->getBool('debug_mode')) {
                    Shop::Container()->getLogService()->warning(
                        'BBF Captcha outputfilter: ' . $e->getMessage()
                    );
                }
            }
        });

        // Kontaktformular (HOOK_KONTAKT_PAGE) – stellt nur das Widget bereit.
        // Achtung: dieser Hook feuert ERST NACH dem Mailversand (ContactController
        // sendet in assignForms, bevor HOOK_KONTAKT_PAGE läuft) → hier NICHT blocken.
        $dispatcher->listen('shop.hook.' . \HOOK_KONTAKT_PAGE, function (array $args) use ($plugin, $db) {
            $this->handleFormHook('contact', $args, $plugin, $db);
        });

        // Kontakt-Spam WIRKLICH blocken: HOOK_KONTAKT_PAGE_PLAUSI feuert in
        // ContactController::assignForms VOR dem Versand (Z. „if ($ok && honeypot===false) … editMessage()").
        // Der Hook übergibt keine Referenz-Args, aber wir können bei Spam JTLs eigenen
        // Kontakt-Honeypot setzen ($_POST['jtl_hp_input']) → Form::honeypotWasFilledOut()
        // wird true → JTL überspringt den Versand sauber (wie bei einem Bot), ohne 500.
        if (defined('HOOK_KONTAKT_PAGE_PLAUSI')) {
            $dispatcher->listen('shop.hook.' . \HOOK_KONTAKT_PAGE_PLAUSI, function (array $args) use ($plugin, $db) {
                if (!$this->protectionActive()) {
                    return;
                }
                try {
                    $captcha = new \Plugin\bbfdesign_captcha\src\Services\CaptchaService($plugin, $db, $this->settingsModel);
                    if (!$captcha->validate($_POST, 'contact')->isValid()) {
                        $_POST['jtl_hp_input'] = '1'; // JTL-Kontakt-Honeypot → Versand wird übersprungen
                    }
                } catch (\Throwable $e) {
                    // Fail-open: ein Fehler darf legitime Kontaktanfragen nie blockieren.
                    if ($this->settingsModel !== null && $this->settingsModel->getBool('debug_mode')) {
                        Shop::Container()->getLogService()->warning('BBF Captcha kontakt plausi: ' . $e->getMessage());
                    }
                }
            });
        }

        // Registrierung (HOOK_REGISTRIEREN_PAGE = 40) – stellt nur das Widget bereit.
        $dispatcher->listen('shop.hook.' . \HOOK_REGISTRIEREN_PAGE, function (array $args) use ($plugin, $db) {
            $this->handleFormHook('registration', $args, $plugin, $db);
        });

        // Registrierung WIRKLICH blocken: HOOK_REGISTRIEREN_PAGE_REGISTRIEREN_PLAUSI (41)
        // übergibt nReturnValue + fehlendeAngaben PER REFERENZ. Nur hier lässt sich die
        // Konto-Erstellung verhindern (Hook 40 feuert vor der Validierung). Bei Spam
        // setzen wir nReturnValue=false -> JTL legt das Konto nicht an.
        $dispatcher->listen('shop.hook.' . \HOOK_REGISTRIEREN_PAGE_REGISTRIEREN_PLAUSI, function (array &$args) use ($plugin, $db) {
            if (!$this->protectionActive()) {
                return;
            }
            try {
                $captcha = new \Plugin\bbfdesign_captcha\src\Services\CaptchaService($plugin, $db, $this->settingsModel);
                $result  = $captcha->validate($_POST, 'registration');
                if (!$result->isValid()) {
                    $args['nReturnValue'] = false;
                    if (isset($args['fehlendeAngaben']) && is_array($args['fehlendeAngaben'])) {
                        $args['fehlendeAngaben']['bbf_captcha_spam'] = 1;
                    }
                    $langVars = $plugin->getLocalization();
                    $shopLang = $_SESSION['cISOSprache'] ?? 'ger';
                    $_SESSION['cFehler'] = $langVars->getTranslation('captcha_failed', $shopLang)
                        ?: 'Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.';
                }
            } catch (\Throwable $e) {
                // Fail-open: ein Fehler darf legitime Registrierungen nie blockieren.
                if ($this->settingsModel->getBool('debug_mode')) {
                    Shop::Container()->getLogService()->warning('BBF Captcha registration plausi: ' . $e->getMessage());
                }
            }
        });

        // Newsletter (HOOK_NEWSLETTER_PAGE = 36)
        $dispatcher->listen('shop.hook.' . \HOOK_NEWSLETTER_PAGE, function (array $args) use ($plugin, $db) {
            $this->handleFormHook('newsletter', $args, $plugin, $db);
        });

        // Produktbewertungen (HOOK_BEWERTUNG_INC_SPEICHERBEWERTUNG = 78)
        $dispatcher->listen('shop.hook.' . \HOOK_BEWERTUNG_INC_SPEICHERBEWERTUNG, function (array $args) use ($plugin, $db) {
            $this->handleFormHook('review', $args, $plugin, $db);
        });

        // Checkout (HOOK_BESTELLVORGANG_PAGE = 19)
        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLVORGANG_PAGE, function (array $args) use ($plugin, $db) {
            $this->handleFormHook('checkout', $args, $plugin, $db);
        });

        // Login (HOOK_KUNDE_CLASS_HOLLOGINKUNDE = 145)
        $dispatcher->listen('shop.hook.' . \HOOK_KUNDE_CLASS_HOLLOGINKUNDE, function (array $args) use ($plugin, $db) {
            $this->handleFormHook('login', $args, $plugin, $db);
        });

        // Wunschliste (HOOK_WUNSCHLISTE_CLASS_FUEGEEIN = 127)
        $dispatcher->listen('shop.hook.' . \HOOK_WUNSCHLISTE_CLASS_FUEGEEIN, function (array $args) use ($plugin, $db) {
            $this->handleFormHook('wishlist', $args, $plugin, $db);
        });

        // Passwort vergessen läuft über jtl.php (HOOK_JTL_PAGE = 23).
        // Wir erkennen das Formular anhand typischer POST-Felder.
        $dispatcher->listen('shop.hook.' . \HOOK_JTL_PAGE, function (array $args) use ($plugin, $db) {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return;
            }
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $isPasswordReset = isset($_POST['pw_vergessen_triggered'])
                || isset($_POST['pass_retry'])
                || (isset($_POST['email']) && stripos($uri, 'passwortvergessen') !== false);
            if ($isPasswordReset) {
                $this->handleFormHook('password_reset', $args, $plugin, $db);
            }
        });

        // Widerrufsformular (NEU in JTL 5.7, WithdrawalController). Es hat KEINEN
        // eigenen Plausi-Hook (HOOK_SEITE_PAGE feuert erst NACH dem Mailversand),
        // versendet aber eine Mail (MAILTEMPLATE_WIDERRUFSFORMULAR) und ist damit
        // spambar. Der Controller prüft vor dem Versand Form::honeypotWasFilledOut().
        // Wir greifen daher am HOOK_ROUTER_PRE_DISPATCH (feuert VOR dem Controller)
        // an: erkennen die Widerruf-POST anhand der Felder, fahren den Smart-Filter
        // und setzen bei Spam JTLs eigenen Honeypot → der Versand wird übersprungen.
        $dispatcher->listen('shop.hook.' . \HOOK_ROUTER_PRE_DISPATCH, function (array $args) use ($plugin, $db) {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                return;
            }
            // Widerruf-Formular eindeutig identifizieren (submit-Flag + typisches Feld)
            if ((int)($_POST['withdrawal'] ?? 0) !== 1 || !isset($_POST['withdrawal_email'])) {
                return;
            }
            if (!$this->protectionActive()) {
                return;
            }
            try {
                $captcha = new \Plugin\bbfdesign_captcha\src\Services\CaptchaService($plugin, $db, $this->settingsModel);
                if (!$captcha->validate($_POST, 'withdrawal')->isValid()) {
                    $_POST['jtl_hp_input'] = '1'; // JTL-Honeypot → Widerruf-Versand wird übersprungen
                }
            } catch (\Throwable $e) {
                // Fail-open: ein Fehler darf einen echten Widerruf nie blockieren.
                if ($this->settingsModel !== null && $this->settingsModel->getBool('debug_mode')) {
                    Shop::Container()->getLogService()->warning('BBF Captcha withdrawal pre-dispatch: ' . $e->getMessage());
                }
            }
        });

        // Consent Manager Integration
        $dispatcher->listen('shop.hook.' . \CONSENT_MANAGER_GET_ACTIVE_ITEMS, function (array $args) use ($plugin) {
            if ($this->settingsModel === null) {
                return;
            }
            $consentService = new \Plugin\bbfdesign_captcha\src\Services\ConsentService($plugin, $this->settingsModel);
            $consentService->registerConsentItems($args);
        });
    }

    /**
     * Registriert BBF Captcha als NATIVEN JTL-Captcha-Dienst (Container-Override).
     *
     * Damit übernimmt BBF den nativen Captcha-Pfad shopweit – überall wo JTL
     * (oder ein Fremd-Plugin) `Shop::Container()->getCaptchaService()` bzw.
     * `Form::validateCaptcha()` aufruft und das jeweilige `*_abfragen_captcha`-
     * Shop-Setting aktiv ist (Kontakt, Widerruf, Registrierung, Passwort …).
     *
     * Standardmäßig AUS (`native_captcha_integration`), damit das Shop-Verhalten
     * unverändert bleibt, bis der Betreiber es bewusst aktiviert und testet.
     * Fail-open: Der Adapter blockt nie allein und gibt bei jedem Fehler "gültig"
     * zurück; ein Fehler beim Binden lässt JTLs Standard-Captcha unangetastet.
     */
    private function registerNativeCaptcha($plugin, $db): void
    {
        if ($this->settingsModel === null
            || !$this->settingsModel->getBool('global_enabled')
            || !$this->settingsModel->getBool('native_captcha_integration')
        ) {
            return;
        }

        try {
            $settings = $this->settingsModel;
            Shop::Container()->setSingleton(
                \JTL\Services\JTL\CaptchaServiceInterface::class,
                static function () use ($plugin, $db, $settings) {
                    return new \Plugin\bbfdesign_captcha\src\Services\JtlCaptchaAdapter($plugin, $db, $settings);
                }
            );
        } catch (\Throwable $e) {
            // Bind-Fehler darf den Shop nie brechen → JTL-Standard-Captcha bleibt aktiv.
            if ($this->settingsModel->getBool('debug_mode')) {
                Shop::Container()->getLogService()->warning('BBF Captcha native bind: ' . $e->getMessage());
            }
        }
    }

    /**
     * Zentrale Formular-Hook-Behandlung
     */
    private function handleFormHook(string $formType, array $args, $plugin, $db): void
    {
        if (!$this->protectionActive()) {
            return;
        }

        if ($this->formProtection === null) {
            $this->formProtection = new FormProtection($plugin, $db, $this->settingsModel);
        }

        $this->formProtection->handleFormHook($formType, $args);
    }

    /**
     * Ist der Schutz aktiv? = NUR der globale Schalter.
     *
     * WICHTIG: Der Lizenzstatus deaktiviert den Spam-Schutz BEWUSST NICHT mehr.
     * Ein Anti-Spam-/Captcha-Plugin muss IMMER schützen — eine Lizenzverletzung
     * darf niemals dazu führen, dass der Shop mit Spam geflutet wird (das schadet
     * dem Betreiber statt die Lizenz durchzusetzen, und genau das ist live
     * passiert: ein domain_mismatch-Verdikt hatte den Reg-/Login-/Formularschutz
     * abgeschaltet → Spam-Konten). Lizenzprobleme werden ausschließlich im Backend
     * als Hinweis angezeigt (LicenseService::hasHardViolation für die Anzeige),
     * NICHT durch Schutz-Abschaltung erzwungen.
     */
    private function protectionActive(): bool
    {
        return $this->settingsModel !== null && $this->settingsModel->getBool('global_enabled');
    }

    /**
     * REST-API Routen registrieren
     */
    private function registerApiRoutes(Dispatcher $dispatcher): void
    {
        $plugin = $this->getPlugin();

        $dispatcher->listen('shop.hook.' . \HOOK_ROUTER_PRE_DISPATCH, function (array $args) use ($plugin) {
            $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
            // Nur Pfad (ohne Query/Host) für den Präfix-Vergleich.
            // parse_url() liefert bei kaputten URIs FALSE (nicht null) → das ??
            // fing das nicht ab → $requestPath wurde bool → str_starts_with()
            // warf einen TypeError (Live-500, fail-closed). Hart auf string zwingen.
            $parsedPath  = parse_url($requestUri, PHP_URL_PATH);
            $requestPath = (is_string($parsedPath) && $parsedPath !== '') ? $parsedPath : $requestUri;
            $basePath    = '/bbfdesign-captcha/api/';

            // Strikter Präfix-Match am Anfang des Pfades (verhindert URL-Hijack via Subpfad)
            if (!str_starts_with($requestPath, $basePath)) {
                return;
            }

            $pathAfterBase = substr($requestPath, strlen($basePath));
            $pathAfterBase = trim($pathAfterBase, '/');

            // Challenge-Endpoint (kein v1-Prefix nötig)
            if ($pathAfterBase === 'challenge') {
                $endpoint = 'challenge';
            } elseif (str_starts_with($pathAfterBase, 'v1/')) {
                $endpoint = substr($pathAfterBase, 3);
            } else {
                return;
            }

            // Session freigeben
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $db       = Shop::Container()->getDB();
            $settings = new Setting($db);
            $method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';

            $controller = new \Plugin\bbfdesign_captcha\src\Controllers\API\CaptchaAPIController(
                $plugin, $db, $settings
            );
            $controller->handleRequest($endpoint, $method);
            exit;
        });

        // Smarty-Pseudo-Funktion {bbfdesign_captcha form='...'} via Output-Replacement
        // JTL erlaubt keine Smarty-Plugin-Registrierung in Hooks, daher ersetzen
        // wir den Platzhalter direkt im HTML-Output.
        // Template-Entwickler können <!-- bbfdesign_captcha form="contact" --> nutzen.
    }

    /**
     * Prüft, ob ein Admin angemeldet ist.
     * JTL-Backend setzt $_SESSION['AdminAccount']; kAdminlogin > 0 ist das Gültigkeitskriterium.
     */
    private function isAdminAuthenticated(): bool
    {
        if (!isset($_SESSION['AdminAccount'])) {
            return false;
        }
        $account = $_SESSION['AdminAccount'];
        $id = $account->kAdminlogin ?? ($account->id ?? 0);
        return (int)$id > 0;
    }

    /**
     * Timing-safe CSRF-Token-Validierung gegen JTL-Session-Token.
     */
    private function isValidCsrfToken(): bool
    {
        $provided = $_REQUEST['jtl_token'] ?? '';
        if (!is_string($provided) || $provided === '') {
            return false;
        }
        $expected = $_SESSION['jtl_token'] ?? '';
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        return hash_equals($expected, $provided);
    }
}
