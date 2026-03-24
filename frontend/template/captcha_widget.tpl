{* BBF Captcha Widget – Wird pro Formular gerendert *}
{if isset($bbfCaptchaWidget) && $bbfCaptchaWidget}
<div class="bbf-captcha-widget" aria-label="{$bbfCaptchaAriaLabel|default:'Sicherheitsprüfung'|escape:'html'}">
    {$bbfCaptchaWidget nofilter}
</div>
{/if}
