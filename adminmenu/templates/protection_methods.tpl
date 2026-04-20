<h2 class="bbf-page-title">Schutzmethoden</h2>

<p style="color: var(--bbf-muted); margin-bottom: var(--bbf-spacing-lg);">
    Aktivieren und konfigurieren Sie die Schutzmethoden f&uuml;r Ihren Shop.
    Methoden k&ouml;nnen kombiniert werden (Layered Defense).
</p>

<div {literal}x-data="bbfProtectionMethods()"{/literal}>

    {* ── ALTCHA (Empfohlen) ── *}
    <div class="bbf-method-card bbf-method-recommended">
        <div class="bbf-method-header">
            <div class="bbf-method-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                ALTCHA (Proof-of-Work)
                <span class="bbf-badge bbf-badge-primary">Empfohlen</span>
                <span class="bbf-badge bbf-badge-success">Kein Consent n&ouml;tig</span>
            </div>
            <label class="bbf-toggle" aria-label="Schutzmethode aktivieren">
                <input type="checkbox" {literal}x-model="methods.altcha_enabled" @change="saveSetting('altcha_enabled', methods.altcha_enabled ? '1' : '0')"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>
        <div class="bbf-method-desc">
            Self-hosted Proof-of-Work Challenge. 100% DSGVO-konform, kein Consent n&ouml;tig. WCAG 2.2 AA konform.
            Der Browser muss eine mathematische Aufgabe l&ouml;sen – f&uuml;r Bots wird Massenspam unwirtschaftlich.
        </div>
        <div class="bbf-accordion" {literal}x-show="methods.altcha_enabled"{/literal}>
            <button type="button" class="bbf-accordion-trigger" {literal}@click="openConfig = openConfig === 'altcha' ? '' : 'altcha'"{/literal}>
                Konfiguration
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="bbf-accordion-body" {literal}x-show="openConfig === 'altcha'" x-transition{/literal}>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Max Number</label>
                    <div>
                        <input type="number" class="bbf-input" style="max-width: 200px;"
                               {literal}x-model="methods.altcha_maxnumber"
                               @change="saveSetting('altcha_maxnumber', methods.altcha_maxnumber)"{/literal}>
                        <div class="bbf-form-help">Standard: 100.000 – Unter Attacke: 1.000.000</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* ── Honeypot ── *}
    <div class="bbf-method-card">
        <div class="bbf-method-header">
            <div class="bbf-method-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Honeypot
                <span class="bbf-badge bbf-badge-success">Kein Consent n&ouml;tig</span>
            </div>
            <label class="bbf-toggle" aria-label="Schutzmethode aktivieren">
                <input type="checkbox" {literal}x-model="methods.honeypot_enabled" @change="saveSetting('honeypot_enabled', methods.honeypot_enabled ? '1' : '0')"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>
        <div class="bbf-method-desc">
            Unsichtbare Felder, die nur Bots ausf&uuml;llen. Dynamische Feldnamen pro Session. DSGVO-konform.
        </div>
        <div class="bbf-accordion" {literal}x-show="methods.honeypot_enabled"{/literal}>
            <button type="button" class="bbf-accordion-trigger" {literal}@click="openConfig = openConfig === 'honeypot' ? '' : 'honeypot'"{/literal}>
                Konfiguration
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="bbf-accordion-body" {literal}x-show="openConfig === 'honeypot'" x-transition{/literal}>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Anzahl Felder</label>
                    <div>
                        <input type="number" class="bbf-input" style="max-width: 100px;" min="1" max="10"
                               {literal}x-model="methods.honeypot_field_count"
                               @change="saveSetting('honeypot_field_count', methods.honeypot_field_count)"{/literal}>
                    </div>
                </div>
                <div class="bbf-form-grid">
                    <label class="bbf-form-label">Alle Formulare</label>
                    <div>
                        <label class="bbf-toggle" aria-label="Schutzmethode aktivieren">
                            <input type="checkbox" {literal}x-model="methods.honeypot_inject_all_forms"
                                   @change="saveSetting('honeypot_inject_all_forms', methods.honeypot_inject_all_forms ? '1' : '0')"{/literal}>
                            <span class="bbf-toggle-slider" aria-hidden="true"></span>
                        </label>
                        <div class="bbf-form-help">Honeypot-Felder in alle Formulare per Output-Filter injizieren</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* ── Timing-Schutz ── *}
    <div class="bbf-method-card">
        <div class="bbf-method-header">
            <div class="bbf-method-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Timing-Schutz
                <span class="bbf-badge bbf-badge-success">Kein Consent n&ouml;tig</span>
            </div>
            <label class="bbf-toggle" aria-label="Schutzmethode aktivieren">
                <input type="checkbox" {literal}x-model="methods.timing_enabled" @change="saveSetting('timing_enabled', methods.timing_enabled ? '1' : '0')"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>
        <div class="bbf-method-desc">
            Misst die Zeit zwischen Formular-Laden und Absenden. Zu schnell = Bot-Verdacht.
        </div>
        <div class="bbf-accordion" {literal}x-show="methods.timing_enabled"{/literal}>
            <button type="button" class="bbf-accordion-trigger" {literal}@click="openConfig = openConfig === 'timing' ? '' : 'timing'"{/literal}>
                Konfiguration
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="bbf-accordion-body" {literal}x-show="openConfig === 'timing'" x-transition{/literal}>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Minimum (Sek.)</label>
                    <input type="number" class="bbf-input" style="max-width: 120px;" min="1"
                           {literal}x-model="methods.timing_min_seconds"
                           @change="saveSetting('timing_min_seconds', methods.timing_min_seconds)"{/literal}>
                </div>
                <div class="bbf-form-grid">
                    <label class="bbf-form-label">Maximum (Sek.)</label>
                    <input type="number" class="bbf-input" style="max-width: 120px;" min="60"
                           {literal}x-model="methods.timing_max_seconds"
                           @change="saveSetting('timing_max_seconds', methods.timing_max_seconds)"{/literal}>
                </div>
            </div>
        </div>
    </div>

    {* ── Smart-Spamfilter ── *}
    <div class="bbf-method-card">
        <div class="bbf-method-header">
            <div class="bbf-method-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Smart-Spamfilter
                <span class="bbf-badge bbf-badge-success">Kein Consent n&ouml;tig</span>
            </div>
            <label class="bbf-toggle" aria-label="Schutzmethode aktivieren">
                <input type="checkbox" {literal}x-model="methods.ai_filter_enabled" @change="saveSetting('ai_filter_enabled', methods.ai_filter_enabled ? '1' : '0')"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>
        <div class="bbf-method-desc">
            Regelbasierte Textanalyse mit Punktesystem: erkennt Spam anhand von URLs, Spam-W&ouml;rtern, Sprache, Wegwerf-Emails u.v.m.
            Optional mit LLM-Zweitpr&uuml;fung (Ollama, OpenAI, Claude, Gemini) kombinierbar &mdash; siehe Men&uuml;punkt „LLM-Pr&uuml;fung".
        </div>
    </div>

    {* ── Cloudflare Turnstile ── *}
    <div class="bbf-method-card">
        <div class="bbf-method-header">
            <div class="bbf-method-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Cloudflare Turnstile
                <span class="bbf-badge bbf-badge-warning">Consent erforderlich</span>
            </div>
            <label class="bbf-toggle" aria-label="Schutzmethode aktivieren">
                <input type="checkbox" {literal}x-model="methods.turnstile_enabled" @change="saveSetting('turnstile_enabled', methods.turnstile_enabled ? '1' : '0')"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>
        <div class="bbf-method-desc">
            Kostenlose, privacy-freundliche Alternative zu reCAPTCHA von Cloudflare. WCAG 2.2 AAA konform.
        </div>
        <div class="bbf-accordion" {literal}x-show="methods.turnstile_enabled"{/literal}>
            <button type="button" class="bbf-accordion-trigger" {literal}@click="openConfig = openConfig === 'turnstile' ? '' : 'turnstile'"{/literal}>
                Konfiguration
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="bbf-accordion-body" {literal}x-show="openConfig === 'turnstile'" x-transition{/literal}>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Site Key</label>
                    <input type="text" class="bbf-input" placeholder="0x..."
                           {literal}x-model="methods.turnstile_site_key"
                           @change="saveSetting('turnstile_site_key', methods.turnstile_site_key)"{/literal}>
                </div>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Secret Key</label>
                    <input type="password" class="bbf-input" placeholder="0x..."
                           {literal}x-model="methods.turnstile_secret_key"
                           @change="saveSetting('turnstile_secret_key', methods.turnstile_secret_key)"{/literal}>
                </div>
                <div class="bbf-form-grid">
                    <label class="bbf-form-label">Modus</label>
                    <select class="bbf-input bbf-select" style="max-width: 200px;"
                            {literal}x-model="methods.turnstile_mode"
                            @change="saveSetting('turnstile_mode', methods.turnstile_mode)"{/literal}>
                        <option value="managed">Managed</option>
                        <option value="invisible">Invisible</option>
                        <option value="non-interactive">Non-Interactive</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {* ── Google reCAPTCHA ── *}
    <div class="bbf-method-card">
        <div class="bbf-method-header">
            <div class="bbf-method-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Google reCAPTCHA
                <span class="bbf-badge bbf-badge-danger">Consent zwingend!</span>
            </div>
            <label class="bbf-toggle" aria-label="Schutzmethode aktivieren">
                <input type="checkbox" {literal}x-model="methods.recaptcha_enabled" @change="saveSetting('recaptcha_enabled', methods.recaptcha_enabled ? '1' : '0')"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>
        <div class="bbf-method-desc">
            Google reCAPTCHA v2 (Checkbox) oder v3 (Invisible mit Score). Nicht DSGVO-konform ohne Consent!
        </div>
        <div class="bbf-accordion" {literal}x-show="methods.recaptcha_enabled"{/literal}>
            <button type="button" class="bbf-accordion-trigger" {literal}@click="openConfig = openConfig === 'recaptcha' ? '' : 'recaptcha'"{/literal}>
                Konfiguration
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="bbf-accordion-body" {literal}x-show="openConfig === 'recaptcha'" x-transition{/literal}>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Version</label>
                    <select class="bbf-input bbf-select" style="max-width: 150px;"
                            {literal}x-model="methods.recaptcha_version"
                            @change="saveSetting('recaptcha_version', methods.recaptcha_version)"{/literal}>
                        <option value="v2">v2 (Checkbox)</option>
                        <option value="v3">v3 (Invisible)</option>
                    </select>
                </div>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Site Key</label>
                    <input type="text" class="bbf-input"
                           {literal}x-model="methods.recaptcha_site_key"
                           @change="saveSetting('recaptcha_site_key', methods.recaptcha_site_key)"{/literal}>
                </div>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Secret Key</label>
                    <input type="password" class="bbf-input"
                           {literal}x-model="methods.recaptcha_secret_key"
                           @change="saveSetting('recaptcha_secret_key', methods.recaptcha_secret_key)"{/literal}>
                </div>
                <div class="bbf-form-grid" {literal}x-show="methods.recaptcha_version === 'v3'"{/literal}>
                    <label class="bbf-form-label">Score-Schwelle</label>
                    <div>
                        <input type="number" class="bbf-input" style="max-width: 100px;" min="0" max="1" step="0.1"
                               {literal}x-model="methods.recaptcha_score_threshold"
                               @change="saveSetting('recaptcha_score_threshold', methods.recaptcha_score_threshold)"{/literal}>
                        <div class="bbf-form-help">0.0 = Bot, 1.0 = Mensch (Standard: 0.5)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* ── Friendly Captcha ── *}
    <div class="bbf-method-card">
        <div class="bbf-method-header">
            <div class="bbf-method-title">
                Friendly Captcha
                <span class="bbf-badge bbf-badge-success">EU-Anbieter</span>
            </div>
            <label class="bbf-toggle" aria-label="Schutzmethode aktivieren">
                <input type="checkbox" {literal}x-model="methods.friendly_captcha_enabled" @change="saveSetting('friendly_captcha_enabled', methods.friendly_captcha_enabled ? '1' : '0')"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>
        <div class="bbf-method-desc">
            Europ&auml;ischer Anbieter, DSGVO-freundlich. Proof-of-Work basiert, invisible, kein Puzzle f&uuml;r Nutzer.
        </div>
        <div class="bbf-accordion" {literal}x-show="methods.friendly_captcha_enabled"{/literal}>
            <button type="button" class="bbf-accordion-trigger" {literal}@click="openConfig = openConfig === 'friendly_captcha' ? '' : 'friendly_captcha'"{/literal}>
                Konfiguration
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="bbf-accordion-body" {literal}x-show="openConfig === 'friendly_captcha'" x-transition{/literal}>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Site Key</label>
                    <input type="text" class="bbf-input"
                           {literal}x-model="methods.friendly_captcha_site_key"
                           @change="saveSetting('friendly_captcha_site_key', methods.friendly_captcha_site_key)"{/literal}>
                </div>
                <div class="bbf-form-grid">
                    <label class="bbf-form-label">API Key</label>
                    <input type="password" class="bbf-input"
                           {literal}x-model="methods.friendly_captcha_api_key"
                           @change="saveSetting('friendly_captcha_api_key', methods.friendly_captcha_api_key)"{/literal}>
                </div>
            </div>
        </div>
    </div>

    {* ── hCaptcha ── *}
    <div class="bbf-method-card">
        <div class="bbf-method-header">
            <div class="bbf-method-title">
                hCaptcha
                <span class="bbf-badge bbf-badge-warning">Consent erforderlich</span>
            </div>
            <label class="bbf-toggle" aria-label="Schutzmethode aktivieren">
                <input type="checkbox" {literal}x-model="methods.hcaptcha_enabled" @change="saveSetting('hcaptcha_enabled', methods.hcaptcha_enabled ? '1' : '0')"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>
        <div class="bbf-method-desc">
            Privacy-fokussierte Alternative zu reCAPTCHA. Visuelles Challenge (Bilder ausw&auml;hlen).
        </div>
        <div class="bbf-accordion" {literal}x-show="methods.hcaptcha_enabled"{/literal}>
            <button type="button" class="bbf-accordion-trigger" {literal}@click="openConfig = openConfig === 'hcaptcha' ? '' : 'hcaptcha'"{/literal}>
                Konfiguration
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="bbf-accordion-body" {literal}x-show="openConfig === 'hcaptcha'" x-transition{/literal}>
                <div class="bbf-form-grid" style="margin-bottom: 12px;">
                    <label class="bbf-form-label">Site Key</label>
                    <input type="text" class="bbf-input"
                           {literal}x-model="methods.hcaptcha_site_key"
                           @change="saveSetting('hcaptcha_site_key', methods.hcaptcha_site_key)"{/literal}>
                </div>
                <div class="bbf-form-grid">
                    <label class="bbf-form-label">Secret Key</label>
                    <input type="password" class="bbf-input"
                           {literal}x-model="methods.hcaptcha_secret_key"
                           @change="saveSetting('hcaptcha_secret_key', methods.hcaptcha_secret_key)"{/literal}>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
var bbfServerSettings = {$settingsJson nofilter};
</script>
<script>
{literal}
if (typeof Alpine !== 'undefined' && Alpine.data) {
    Alpine.data('bbfProtectionMethods', function() {
        var s = window.bbfServerSettings || {};
        return {
            openConfig: '',
            methods: {
                altcha_enabled: s.altcha_enabled === '1',
                altcha_maxnumber: s.altcha_maxnumber || '100000',
                honeypot_enabled: s.honeypot_enabled === '1',
                honeypot_field_count: s.honeypot_field_count || '3',
                honeypot_inject_all_forms: s.honeypot_inject_all_forms === '1',
                timing_enabled: s.timing_enabled === '1',
                timing_min_seconds: s.timing_min_seconds || '3',
                timing_max_seconds: s.timing_max_seconds || '3600',
                ai_filter_enabled: s.ai_filter_enabled === '1',
                turnstile_enabled: s.turnstile_enabled === '1',
                turnstile_site_key: s.turnstile_site_key || '',
                turnstile_secret_key: s.turnstile_secret_key || '',
                turnstile_mode: s.turnstile_mode || 'managed',
                recaptcha_enabled: s.recaptcha_enabled === '1',
                recaptcha_version: s.recaptcha_version || 'v3',
                recaptcha_site_key: s.recaptcha_site_key || '',
                recaptcha_secret_key: s.recaptcha_secret_key || '',
                recaptcha_score_threshold: s.recaptcha_score_threshold || '0.5',
                friendly_captcha_enabled: s.friendly_captcha_enabled === '1',
                friendly_captcha_site_key: s.friendly_captcha_site_key || '',
                friendly_captcha_api_key: s.friendly_captcha_api_key || '',
                hcaptcha_enabled: s.hcaptcha_enabled === '1',
                hcaptcha_site_key: s.hcaptcha_site_key || '',
                hcaptcha_secret_key: s.hcaptcha_secret_key || ''
            },

            saveSetting: function(key, value) {
                bbfAdmin.post('saveSetting', { key: key, value: value }).then(function(resp) {
                    if (resp.success) {
                        bbfAdmin.showNotification('Gespeichert', 'success');
                    } else {
                        bbfAdmin.showNotification(resp.message || 'Fehler', 'error');
                    }
                }).catch(function() {
                    bbfAdmin.showNotification('Verbindungsfehler', 'error');
                });
            }
        };
    });
}
{/literal}
</script>
