<link rel="stylesheet" href="{$adminUrl|escape:'html'}css/admin-base.css">
<link rel="stylesheet" href="{$adminUrl|escape:'html'}css/admin.css">

<div class="bbf-plugin-page" {literal}x-data="bbfCaptchaAdmin()" x-init="init()"{/literal}>
    {$jtl_token}

    {* ── Sidebar ── *}
    <div class="bbf-sidebar" {literal}:class="{ 'bbf-sidebar-collapsed': sidebarCollapsed }"{/literal}>
        <div class="bbf-sidebar-header">
            <div class="bbf-sidebar-logo">
                <img src="{$adminUrl|escape:'html'}images/Logo_bbfdesign_dark_2024.png" alt="bbfdesign" class="bbf-logo-img">
            </div>
            <button type="button" class="bbf-sidebar-toggle" {literal}@click="sidebarCollapsed = !sidebarCollapsed"{/literal}>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>

        <div class="bbf-sidebar-content">
            <div class="bbf-nav-section">&Uuml;BERSICHT</div>
            <ul class="bbf-sidebar-nav">
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'dashboard' }" @click.prevent="navigate('dashboard')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>

            <div class="bbf-nav-section">SCHUTZ</div>
            <ul class="bbf-sidebar-nav">
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'protection_methods' }" @click.prevent="navigate('protection_methods')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>Schutzmethoden</span>
                    </a>
                </li>
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'form_protection' }" @click.prevent="navigate('form_protection')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>Formulare</span>
                    </a>
                </li>
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'ai_spam_filter' }" @click.prevent="navigate('ai_spam_filter')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 1 4 4v2H8V6a4 4 0 0 1 4-4z"/><rect x="3" y="10" width="18" height="12" rx="2"/><line x1="12" y1="14" x2="12" y2="18"/></svg>
                        <span>KI-Spamfilter</span>
                    </a>
                </li>
            </ul>

            <div class="bbf-nav-section">VERWALTUNG</div>
            <ul class="bbf-sidebar-nav">
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'ip_management' }" @click.prevent="navigate('ip_management')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span>IP-Verwaltung</span>
                    </a>
                </li>
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'log' }" @click.prevent="navigate('log')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <span>Spam-Log</span>
                    </a>
                </li>
            </ul>

            <div class="bbf-nav-section">ERWEITERT</div>
            <ul class="bbf-sidebar-nav">
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'api' }" @click.prevent="navigate('api')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                        <span>API</span>
                    </a>
                </li>
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'settings' }" @click.prevent="navigate('settings')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span>Einstellungen</span>
                    </a>
                </li>
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'css_editor' }" @click.prevent="navigate('css_editor')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        <span>Custom CSS</span>
                    </a>
                </li>
            </ul>

            <div class="bbf-nav-section">SYSTEM</div>
            <ul class="bbf-sidebar-nav">
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'documentation' }" @click.prevent="navigate('documentation')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        <span>Dokumentation</span>
                    </a>
                </li>
                <li>
                    <a href="#" {literal}:class="{ 'bbf-nav-active': page === 'changelog' }" @click.prevent="navigate('changelog')"{/literal}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Changelog</span>
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
                    <h3 class="bbf-header-title">BBF Captcha &amp; Spam-Schutz</h3>
                    <p class="bbf-header-subtitle">Anti-Spam &amp; Bot-Schutz System</p>
                </div>
            </div>
        </div>

        {* ── Page Content ── *}
        <div class="bbf-content">
            <div id="bbf-page-content">
                <div class="text-center py-5">
                    <div class="bbf-spinner bbf-spinner-lg"></div>
                    <p class="mt-3" style="color: var(--bbf-text-light);">Seite wird geladen...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var ShopURL = "{$ShopURL|escape:'javascript'}";
    var adminUrl = "{$adminUrl|escape:'javascript'}";
    var postURL = "{$postURL|escape:'javascript'}";
    var pluginId = "bbfdesign_captcha";
    var adminLang = "{$adminLang|default:'ger'|escape:'javascript'}";
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
                container.innerHTML = '<div class="text-center py-5"><div class="bbf-spinner bbf-spinner-lg"></div><p class="mt-3" style="color: var(--bbf-text-light);">Seite wird geladen...</p></div>';

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
                                    container.innerHTML = '<div class="bbf-alert bbf-alert-danger">Fehler beim Laden der Seite.</div>';
                                }
                            } catch (e) {
                                container.innerHTML = xhr.responseText;
                                self.evalScripts(container);
                                Alpine.initTree(container);
                            }
                        } else {
                            container.innerHTML = '<div class="bbf-alert bbf-alert-danger">Verbindungsfehler (' + xhr.status + ')</div>';
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
