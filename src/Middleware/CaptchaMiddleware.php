<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Middleware;

use JTL\DB\DbInterface;
use JTL\Plugin\PluginInterface;
use Plugin\bbfdesign_captcha\src\Models\Setting;
use Plugin\bbfdesign_captcha\src\Services\CaptchaService;
use Plugin\bbfdesign_captcha\src\Services\ValidationResult;
use Plugin\bbfdesign_captcha\src\Helpers\PluginHelper;

/**
 * Zentrale Validierungs-Middleware
 *
 * Kann von externen Plugins oder der REST-API genutzt werden,
 * um Formulare über einen einheitlichen Einstiegspunkt zu validieren.
 */
class CaptchaMiddleware
{
    private CaptchaService $captcha;

    public function __construct(PluginInterface $plugin, DbInterface $db, Setting $settings)
    {
        $this->captcha = new CaptchaService($plugin, $db, $settings);
    }

    /**
     * Request validieren
     *
     * @param array  $postData  POST-Daten
     * @param string $formType  Formular-Typ (contact, registration, etc.)
     * @return ValidationResult
     */
    public function validate(array $postData, string $formType): ValidationResult
    {
        return $this->captcha->validate($postData, $formType);
    }

    /**
     * Captcha-Widget HTML für ein Formular holen
     */
    public function renderWidget(string $formType): string
    {
        return $this->captcha->renderWidget($formType);
    }

    /**
     * Aktive Methoden für ein Formular
     */
    public function getActiveMethods(string $formType): array
    {
        return $this->captcha->getActiveMethodsForForm($formType);
    }
}
