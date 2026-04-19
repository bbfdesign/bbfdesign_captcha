<link rel="stylesheet" href="{$adminUrl|escape:'html'}css/admin-base.css">
<link rel="stylesheet" href="{$adminUrl|escape:'html'}css/admin.css">

<div class="bbf-plugin-page" {literal}x-data="bbfCaptchaAdmin()" x-init="init()"{/literal}>
    <a href="#bbf-main-content" class="bbf-skip-link">Skip to content</a>
    {$jtl_token}

    {* ── Sidebar ── *}
    <div class="bbf-sidebar" {literal}:class="{ 'bbf-sidebar-collapsed': sidebarCollapsed }"{/literal}>
        <div class="bbf-sidebar-header">
            <div class="bbf-sidebar-logo">
                <img src="{$adminUrl|escape:'html'}images/Logo_bbfdesign_dark_2024.png" alt="bbfdesign" class="bbf-logo-img">
            </div>
            <button type="button" class="bbf-sidebar-toggle" aria-label="Toggle navigation" {literal}@click="sidebarCollapsed = !sidebarCollapsed" :aria-expanded="!sidebarCollapsed"{/literal}>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>

        <div class="bbf-sidebar-content">
            <div class="bbf-nav-section">{$langVars->getTranslation('nav_dashboard', $adminLang)|default:'&Uuml;BERSICHT'|upper}</div>
            <ul class="bbf-sidebar-nav" role="menu">
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_dashboard', $adminLang)|default:'Dashboard'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'dashboard' }" :aria-current="page === 'dashboard' ? 'page' : false" @click.prevent="navigate('dashboard')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        <span>{$langVars->getTranslation('nav_dashboard', $adminLang)|default:'Dashboard'|escape:'html'}</span>
                    </a>
                </li>
            </ul>

            <div class="bbf-nav-section">{$langVars->getTranslation('nav_protection_methods', $adminLang)|default:'SCHUTZ'|upper}</div>
            <ul class="bbf-sidebar-nav" role="menu">
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_protection_methods', $adminLang)|default:'Schutzmethoden'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'protection_methods' }" :aria-current="page === 'protection_methods' ? 'page' : false" @click.prevent="navigate('protection_methods')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>{$langVars->getTranslation('nav_protection_methods', $adminLang)|default:'Schutzmethoden'|escape:'html'}</span>
                    </a>
                </li>
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_form_protection', $adminLang)|default:'Formulare'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'form_protection' }" :aria-current="page === 'form_protection' ? 'page' : false" @click.prevent="navigate('form_protection')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>{$langVars->getTranslation('nav_form_protection', $adminLang)|default:'Formulare'|escape:'html'}</span>
                    </a>
                </li>
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_ai_filter', $adminLang)|default:'KI-Spamfilter'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'ai_spam_filter' }" :aria-current="page === 'ai_spam_filter' ? 'page' : false" @click.prevent="navigate('ai_spam_filter')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 2a4 4 0 0 1 4 4v2H8V6a4 4 0 0 1 4-4z"/><rect x="3" y="10" width="18" height="12" rx="2"/><line x1="12" y1="14" x2="12" y2="18"/></svg>
                        <span>{$langVars->getTranslation('nav_ai_filter', $adminLang)|default:'KI-Spamfilter'|escape:'html'}</span>
                    </a>
                </li>
            </ul>

            <div class="bbf-nav-section">{$langVars->getTranslation('nav_ip_management', $adminLang)|default:'VERWALTUNG'|upper}</div>
            <ul class="bbf-sidebar-nav" role="menu">
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_ip_management', $adminLang)|default:'IP-Verwaltung'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'ip_management' }" :aria-current="page === 'ip_management' ? 'page' : false" @click.prevent="navigate('ip_management')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span>{$langVars->getTranslation('nav_ip_management', $adminLang)|default:'IP-Verwaltung'|escape:'html'}</span>
                    </a>
                </li>
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_spam_log', $adminLang)|default:'Spam-Log'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'log' }" :aria-current="page === 'log' ? 'page' : false" @click.prevent="navigate('log')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <span>{$langVars->getTranslation('nav_spam_log', $adminLang)|default:'Spam-Log'|escape:'html'}</span>
                    </a>
                </li>
            </ul>

            <div class="bbf-nav-section">{$langVars->getTranslation('nav_api', $adminLang)|default:'ERWEITERT'|upper}</div>
            <ul class="bbf-sidebar-nav" role="menu">
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_api', $adminLang)|default:'API'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'api' }" :aria-current="page === 'api' ? 'page' : false" @click.prevent="navigate('api')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                        <span>{$langVars->getTranslation('nav_api', $adminLang)|default:'API'|escape:'html'}</span>
                    </a>
                </li>
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_settings', $adminLang)|default:'Einstellungen'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'settings' }" :aria-current="page === 'settings' ? 'page' : false" @click.prevent="navigate('settings')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span>{$langVars->getTranslation('nav_settings', $adminLang)|default:'Einstellungen'|escape:'html'}</span>
                    </a>
                </li>
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_css_editor', $adminLang)|default:'Custom CSS'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'css_editor' }" :aria-current="page === 'css_editor' ? 'page' : false" @click.prevent="navigate('css_editor')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        <span>{$langVars->getTranslation('nav_css_editor', $adminLang)|default:'Custom CSS'|escape:'html'}</span>
                    </a>
                </li>
            </ul>

            <div class="bbf-nav-section">{$langVars->getTranslation('nav_documentation', $adminLang)|default:'SYSTEM'|upper}</div>
            <ul class="bbf-sidebar-nav" role="menu">
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_documentation', $adminLang)|default:'Dokumentation'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'documentation' }" :aria-current="page === 'documentation' ? 'page' : false" @click.prevent="navigate('documentation')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        <span>{$langVars->getTranslation('nav_documentation', $adminLang)|default:'Dokumentation'|escape:'html'}</span>
                    </a>
                </li>
                <li role="none">
                    <a href="#" role="menuitem" aria-label="{$langVars->getTranslation('nav_changelog', $adminLang)|default:'Changelog'|escape:'html'}" {literal}:class="{ 'bbf-nav-active': page === 'changelog' }" :aria-current="page === 'changelog' ? 'page' : false" @click.prevent="navigate('changelog')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>{$langVars->getTranslation('nav_changelog', $adminLang)|default:'Changelog'|escape:'html'}</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="bbf-sidebar-footer">
            <span class="bbf-version">v{$pluginVersion|default:'1.0.0'|escape:'html'}</span>
        </div>
    </div>

    {* ── Main Content ── *}
    <div class="bbf-main">
        {* ── Gradient Header ── *}
        <div class="bbf-header">
            <div class="bbf-header-inner">
                <div>
                    <h3 class="bbf-header-title">{$langVars->getTranslation('plugin_name', $adminLang)|default:'BBF Captcha &amp; Spam-Schutz'|escape:'html'}</h3>
                    <p class="bbf-header-subtitle">Anti-Spam &amp; Bot Protection</p>
                </div>
            </div>
        </div>

        {* ── Page Content ── *}
        <main class="bbf-content" id="bbf-main-content">
            <div id="bbf-page-content" aria-live="polite" aria-busy="false">
                <div class="text-center py-5">
                    <div class="bbf-spinner bbf-spinner-lg" role="status" aria-label="{$langVars->getTranslation('loading_page', $adminLang)|default:'Loading'|escape:'html'}"></div>
                    <p class="mt-3" style="color: var(--bbf-text-light);">{$langVars->getTranslation('loading_page', $adminLang)|default:'Loading...'|escape:'html'}</p>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    var ShopURL = "{$ShopURL|escape:'javascript'}";
    var adminUrl = "{$adminUrl|escape:'javascript'}";
    var postURL = "{$postURL|escape:'javascript'}";
    var pluginId = "bbfdesign_captcha";
    var adminLang = "{$adminLang|default:'ger'|escape:'javascript'}";
    window.bbfLang = {
        copied_to_clipboard: "{$plugin->getLocalization()->getTranslation('copied_to_clipboard', $adminLang)|default:'Copied'|escape:'javascript'}",
        loading_page:        "{$plugin->getLocalization()->getTranslation('loading_page', $adminLang)|default:'Loading...'|escape:'javascript'}",
        page_load_error:     "{$plugin->getLocalization()->getTranslation('page_load_error', $adminLang)|default:'Error loading page'|escape:'javascript'}",
        connection_error:    "{$plugin->getLocalization()->getTranslation('connection_error', $adminLang)|default:'Connection error'|escape:'javascript'}",
        captcha_success:     "{$plugin->getLocalization()->getTranslation('captcha_success', $adminLang)|default:'Security check successful'|escape:'javascript'}",
        something_went_wrong:"{$plugin->getLocalization()->getTranslation('something_went_wrong', $adminLang)|default:'Something went wrong'|escape:'javascript'}",
        no_data:             "{$plugin->getLocalization()->getTranslation('loading', $adminLang)|default:'No data'|escape:'javascript'}",
        form_contact:        "{$plugin->getLocalization()->getTranslation('form_contact', $adminLang)|default:'Contact'|escape:'javascript'}",
        form_registration:   "{$plugin->getLocalization()->getTranslation('form_registration', $adminLang)|default:'Registration'|escape:'javascript'}",
        form_newsletter:     "{$plugin->getLocalization()->getTranslation('form_newsletter', $adminLang)|default:'Newsletter'|escape:'javascript'}",
        form_review:         "{$plugin->getLocalization()->getTranslation('form_review', $adminLang)|default:'Reviews'|escape:'javascript'}",
        form_checkout:       "{$plugin->getLocalization()->getTranslation('form_checkout', $adminLang)|default:'Checkout'|escape:'javascript'}",
        form_login:          "{$plugin->getLocalization()->getTranslation('form_login', $adminLang)|default:'Login'|escape:'javascript'}",
        form_password_reset: "{$plugin->getLocalization()->getTranslation('form_password_reset', $adminLang)|default:'Password reset'|escape:'javascript'}",
        form_wishlist:       "{$plugin->getLocalization()->getTranslation('form_wishlist', $adminLang)|default:'Wishlist'|escape:'javascript'}",
        method_honeypot:     "{$plugin->getLocalization()->getTranslation('method_honeypot', $adminLang)|default:'Honeypot'|escape:'javascript'}",
        method_timing:       "{$plugin->getLocalization()->getTranslation('method_timing', $adminLang)|default:'Timing'|escape:'javascript'}",
        method_altcha:       "{$plugin->getLocalization()->getTranslation('method_altcha', $adminLang)|default:'ALTCHA'|escape:'javascript'}",
        method_ai:           "{$plugin->getLocalization()->getTranslation('method_ai_filter', $adminLang)|default:'AI filter'|escape:'javascript'}"
    };
</script>
<script src="{$adminUrl|escape:'html'}js/vendor/alpine.min.js" defer></script>
<script>
{literal}
document.addEventListener('alpine:init', function() {
    Alpine.data('bbfCaptchaAdmin', function() {
        return {
            page: '',
            loading: false,
            sidebarCollapsed: false,

            init: function() {
                // Dashboard ist IMMER die erste Seite
                this.navigate('dashboard');
            },

            navigate: function(pageName) {
                var self = this;
                this.page = pageName;
                this.loading = true;
                var container = document.getElementById('bbf-page-content');
                var loadingText = (window.bbfLang && window.bbfLang.loading_page) || 'Loading...';
                container.innerHTML = '<div class="text-center py-5"><div class="bbf-spinner bbf-spinner-lg" role="status" aria-label="' + loadingText + '"></div><p class="mt-3" style="color: var(--bbf-text-light);">' + loadingText + '</p></div>';

                var xhr = new XMLHttpRequest();
                xhr.open('POST', postURL, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        self.loading = false;
                        if (xhr.status === 200) {
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp && resp.content) {
                                    container.innerHTML = resp.content;
                                    self.evalScripts(container);
                                    Alpine.initTree(container);
                                } else {
                                    var msgErr = (window.bbfLang && window.bbfLang.page_load_error) || 'Error loading page';
                                    container.innerHTML = '<div class="bbf-alert bbf-alert-danger" role="alert">' + msgErr + '</div>';
                                }
                            } catch (e) {
                                container.innerHTML = xhr.responseText;
                                self.evalScripts(container);
                                Alpine.initTree(container);
                            }
                        } else {
                            var msgConn = (window.bbfLang && window.bbfLang.connection_error) || 'Connection error';
                            container.innerHTML = '<div class="bbf-alert bbf-alert-danger" role="alert">' + msgConn + ' (' + xhr.status + ')</div>';
                        }
                    }
                };
                var token = document.querySelector('[name="jtl_token"]');
                var params = 'action=getPage&page=' + encodeURIComponent(pageName) + '&is_ajax=1';
                if (token) params += '&jtl_token=' + encodeURIComponent(token.value);
                xhr.send(params);
            },

            evalScripts: function(container) {
                var scripts = container.querySelectorAll('script');
                scripts.forEach(function(oldScript) {
                    var newScript = document.createElement('script');
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                    } else {
                        newScript.textContent = oldScript.textContent;
                    }
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            }
        };
    });
});
{/literal}
</script>
<script src="{$adminUrl|escape:'html'}js/admin.js"></script>
