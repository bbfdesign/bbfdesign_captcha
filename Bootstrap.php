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

class Bootstrap extends Bootstrapper
{
    private ?Setting $settingsModel = null;
    private ?FormProtection $formProtection = null;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        try {
            $plugin = $this->getPlugin();
            $db     = Shop::Container()->getDB();
            $this->settingsModel = new Setting($db);

            // Frontend: Hooks + API-Routen registrieren
            if (Shop::isFrontend()) {
                $this->registerFrontendHooks($dispatcher);
                $this->registerApiRoutes($dispatcher);
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
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            header('Content-Type: application/json; charset=utf-8');

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
        $smarty->assign([
            'plugin'        => $plugin,
            'pluginId'      => $plugin->getPluginID(),
            'postURL'       => $plugin->getPaths()->getBackendURL(),
            'adminUrl'      => $plugin->getPaths()->getAdminURL(),
            'ShopURL'       => Shop::getURL(),
            'adminLang'     => $adminLang,
            'langVars'      => $langVars,
            'pluginVersion' => $plugin->getCurrentVersion(),
            'settings'      => $allSettings,
            'settingsJson'  => json_encode($allSettings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ]);

        $templateMap = [
            'dashboard'          => 'dashboard.tpl',
            'protection_methods' => 'protection_methods.tpl',
            'form_protection'    => 'form_protection.tpl',
            'ai_spam_filter'     => 'ai_spam_filter.tpl',
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
            // Formularkonfigurationen serverseitig vorladen
            $formConfigs = $db->queryPrepared(
                "SELECT * FROM `bbf_captcha_form_config` ORDER BY `form_type` ASC",
                [],
                2
            );
            $smarty->assign('formConfigsJson', json_encode(
                is_array($formConfigs) ? $formConfigs : [],
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
            if ($this->settingsModel === null || !$this->settingsModel->getBool('global_enabled')) {
                return;
            }

            if (!isset($args['output'])) {
                return;
            }

            $html = &$args['output'];

            // Assets einbinden (nur wenn Formular auf der Seite)
            $assetHook = new IncludeAssets($plugin, $this->settingsModel);
            $html = $assetHook->includeIfNeeded($html);

            // Honeypot + Timing in Formulare injizieren
            $outputFilter = new SmartyOutputFilter($this->settingsModel);
            $html = $outputFilter->filter($html);
        });

        // Kontaktformular
        $dispatcher->listen('shop.hook.' . \HOOK_KONTAKT_PAGE, function (array $args) use ($plugin, $db) {
            $this->handleFormHook('contact', $args, $plugin, $db);
        });

        // Registrierung (HOOK_REGISTRIEREN_PAGE = 40)
        $dispatcher->listen('shop.hook.' . \HOOK_REGISTRIEREN_PAGE, function (array $args) use ($plugin, $db) {
            $this->handleFormHook('registration', $args, $plugin, $db);
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
     * Zentrale Formular-Hook-Behandlung
     */
    private function handleFormHook(string $formType, array $args, $plugin, $db): void
    {
        if ($this->settingsModel === null || !$this->settingsModel->getBool('global_enabled')) {
            return;
        }

        if ($this->formProtection === null) {
            $this->formProtection = new FormProtection($plugin, $db, $this->settingsModel);
        }

        $this->formProtection->handleFormHook($formType, $args);
    }

    /**
     * REST-API Routen registrieren
     */
    private function registerApiRoutes(Dispatcher $dispatcher): void
    {
        $plugin = $this->getPlugin();

        $dispatcher->listen('shop.hook.' . \HOOK_ROUTER_PRE_DISPATCH, function (array $args) use ($plugin) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $basePath   = '/bbfdesign-captcha/api/';

            // Prüfe ob die URL zum API-Prefix passt
            $pos = strpos($requestUri, $basePath);
            if ($pos === false) {
                return;
            }

            $pathAfterBase = substr($requestUri, $pos + strlen($basePath));
            // Query-String entfernen
            $pathAfterBase = explode('?', $pathAfterBase, 2)[0];
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
}
