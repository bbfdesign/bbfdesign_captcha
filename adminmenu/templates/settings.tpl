<h2 class="bbf-page-title">Einstellungen</h2>

<div {literal}x-data="bbfSettings()"{/literal}>
    {literal}
    <div class="bbf-tabs bbf-settings-tabs" role="tablist">
        <button type="button" class="bbf-tab" role="tab" :class="{active: activeTab==='allgemein'}" @click="activeTab='allgemein'">Allgemein</button>
        <button type="button" class="bbf-tab" role="tab" :class="{active: activeTab==='cockpit'}" @click="activeTab='cockpit'">Zentrale Erkennung</button>
        <button type="button" class="bbf-tab" role="tab" :class="{active: activeTab==='alerts'}" @click="activeTab='alerts'">Benachrichtigungen</button>
        <button type="button" class="bbf-tab" role="tab" :class="{active: activeTab==='sicherheit'}" @click="activeTab='sicherheit'">Sicherheit &amp; Lizenz</button>
    </div>
    {/literal}

    <div {literal}x-show="activeTab==='allgemein'"{/literal}>
    <div class="bbf-card">
        <h3 class="bbf-card-title" style="margin-bottom: var(--bbf-spacing-lg);">Allgemeine Einstellungen</h3>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Globaler Schutz
                <div class="bbf-form-help">Notfall-Kill-Switch: Deaktiviert den gesamten Spam-Schutz</div>
            </label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.global_enabled"{/literal}>
                <span class="bbf-toggle-slider"></span>
            </label>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">Standard-Aktion bei Spam</label>
            <select class="bbf-input bbf-select" style="max-width: 200px;" {literal}x-model="s.default_action"{/literal}>
                <option value="block">Blockieren</option>
                <option value="log">Nur loggen</option>
                <option value="both">Beides</option>
            </select>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Log-Aufbewahrung
                <div class="bbf-form-help">Spam-Log-Eintr&auml;ge nach X Tagen automatisch l&ouml;schen</div>
            </label>
            <div style="display: flex; align-items: center; gap: 8px;">
                <input type="number" class="bbf-input" style="max-width: 100px;" min="1" {literal}x-model="s.log_retention_days"{/literal}>
                <span style="color: var(--bbf-muted);">Tage</span>
            </div>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Auto-Cleanup
                <div class="bbf-form-help">Bereinigt Logs/Rate-Limits/abgelaufene IP-Blocks automatisch &uuml;ber den nativen JTL-Cron (h&ouml;chstens 1&times;/Tag). Ohne eingerichteten Cron greift ein gedrosselter Fallback &uuml;ber den Shop-Traffic.</div>
            </label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.auto_cleanup"{/literal}>
                <span class="bbf-toggle-slider"></span>
            </label>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Cron-Bereinigung (URL)
                <div class="bbf-form-help">Optionaler Fallback: Das Plugin registriert bereits einen nativen JTL-Cron-Job (st&uuml;ndlich). Diese URL nur n&ouml;tig, wenn der Shop-Cron nicht l&auml;uft &ndash; dann per Server-Cron aufrufen.</div>
            </label>
            <div>
                <input type="text" class="bbf-input" readonly onclick="this.select()"
                       value="{$ShopURL}/bbfdesign-captcha/api/v1/cron?token={$settings.cron_token|default:''}">
            </div>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Formulardaten protokollieren
                <div class="bbf-form-help">DSGVO: Eingereichte Felder (Name, E-Mail &hellip;) abgelehnter &Uuml;bermittlungen im Spam-Log speichern, damit sie unter „Details" einsehbar sind. Aus = nur Metadaten (IP, Formular, Methode, Score, Begr&uuml;ndung).</div>
            </label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.log_request_data"{/literal}>
                <span class="bbf-toggle-slider"></span>
            </label>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Debug-Modus
                <div class="bbf-form-help">Zus&auml;tzliches Logging f&uuml;r Fehlersuche</div>
            </label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.debug_mode"{/literal}>
                <span class="bbf-toggle-slider"></span>
            </label>
        </div>

        <hr style="border-color: var(--bbf-border-light); margin: var(--bbf-spacing-lg) 0;">
        <h3 class="bbf-card-title" style="margin-bottom: var(--bbf-spacing-lg);">Formular-Abdeckung</h3>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Native JTL-Captcha-Integration
                <div class="bbf-form-help">Registriert BBF Captcha als shopweiten JTL-Captcha-Dienst. Dann sch&uuml;tzt es zus&auml;tzlich das <strong>Widerrufsformular</strong> (neu in 5.7) und alle Core- sowie Fremd-Plugin-Formulare, die JTLs Captcha-Abfrage nutzen &ndash; &uuml;berall dort, wo im Shop die jeweilige Captcha-Abfrage (z.&nbsp;B. <code>kontakt_abfragen_captcha</code>, <code>widerruf_abfragen_captcha</code>) aktiv ist. Fail-open (sperrt nie echte Kunden aus). <strong>Standard: aus</strong> &ndash; bitte zuerst auf einem Test-/Staging-Shop pr&uuml;fen.</div>
            </label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.native_captcha_integration"{/literal}>
                <span class="bbf-toggle-slider"></span>
            </label>
        </div>

    </div>
    </div>

    <div {literal}x-show="activeTab==='cockpit'"{/literal}>
    <div class="bbf-card">
        <h3 class="bbf-card-title" style="margin-bottom: var(--bbf-spacing-lg);">Zentrale Erkennung (CaptchaCockpit)</h3>

        {* CAP-11: Schnell-/Auto-Anmeldung – Plugin registriert sich selbst, Secret kommt automatisch *}
        <div class="bbf-alert bbf-alert-info" style="margin-bottom: var(--bbf-spacing-md);" {literal}x-show="!cockpitAvvAt"{/literal} x-cloak>
            <strong>Schnell-Anmeldung (empfohlen)</strong>
            <div class="bbf-form-help" style="margin-top:4px;">Endpoint + Anmelde-Schl&uuml;ssel eingeben, AVV best&auml;tigen, anmelden &ndash; das Plugin registriert sich selbst am Cockpit und erh&auml;lt sein Secret automatisch. Kein manuelles Secret-Kopieren n&ouml;tig.</div>
        </div>
        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Anmelde-Schl&uuml;ssel (Enrollment-Key)
                <div class="bbf-form-help">Geteilter Schl&uuml;ssel von BBF f&uuml;r die Selbst-Anmeldung. Wird nur server&shy;seitig gespeichert.</div>
            </label>
            <input type="password" autocomplete="new-password" class="bbf-input" style="max-width: 420px;" placeholder="Enrollment-Key einf&uuml;gen" {literal}x-model="enrollKey"{/literal}>
        </div>
        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-lg);">
            <label class="bbf-form-label">Automatisch anmelden</label>
            <div>
                <button type="button" class="bbf-btn bbf-btn-primary" {literal}@click="enroll()" :disabled="enrolling"{/literal}>
                    <span {literal}x-text="enrolling ? 'Melde an…' : 'Automatisch anmelden &amp; aktivieren'"{/literal}>Automatisch anmelden &amp; aktivieren</span>
                </button>
                <div class="bbf-form-help" style="margin-top:6px;">Benötigt Endpoint + Enrollment-Key + AVV-Best&auml;tigung (unten).</div>
            </div>
        </div>

        <hr style="border-color: var(--bbf-border-light); margin: var(--bbf-spacing-md) 0;">

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Telemetrie an Cockpit senden
                <div class="bbf-form-help">Meldet <strong>anonymisierte</strong> Spam-Ereignisse (keine Klar-IP, keine Klartext-Inhalte, keine vollst&auml;ndigen E-Mail-Adressen) an das zentrale CaptchaCockpit, damit Bot-Wellen shop&uuml;bergreifend erkannt werden. <strong>Standard: aus</strong> &ndash; Aktivierung nur mit AVV. Bei Ausfall des Cockpits bleibt der Schutz unver&auml;ndert (fail-open).</div>
            </label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.cockpit_enabled"{/literal}>
                <span class="bbf-toggle-slider"></span>
            </label>
        </div>
        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">Cockpit-Endpoint</label>
            <input type="url" class="bbf-input" style="max-width: 420px;" placeholder="https://captchacockpit.bbfdesign.de" {literal}x-model="s.cockpit_endpoint"{/literal}>
        </div>
        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Shared-Secret
                <div class="bbf-form-help">Wird nur server&shy;seitig gespeichert (nie im Frontend angezeigt). Leer lassen, um das bestehende Secret zu behalten.</div>
            </label>
            <input type="password" autocomplete="new-password" class="bbf-input" style="max-width: 420px;" placeholder="&bull;&bull;&bull;&bull; (zum &Auml;ndern eingeben)" {literal}x-model="s.cockpit_secret"{/literal}>
        </div>
        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                Anonymes IP-Pr&auml;fix mitsenden
                <div class="bbf-form-help">Optional: zus&auml;tzlich ein gek&uuml;rztes IP-Pr&auml;fix (/24 bzw. /48) senden. Erleichtert die Netz-Erkennung. Default aus.</div>
            </label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.cockpit_share_ip_prefix"{/literal}>
                <span class="bbf-toggle-slider"></span>
            </label>
        </div>

        {* CAP-08: AVV-/Datenschutz-Bestätigung – Pflicht zum Aktivieren, sonst bleibt es AUS *}
        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);" {literal}x-show="!cockpitAvvAt"{/literal}>
            <label class="bbf-form-label">
                Auftragsverarbeitung (AVV) / Datenschutz best&auml;tigen
                <div class="bbf-form-help">Pflicht zum Aktivieren. Mit der Best&auml;tigung beauftragst du BBF Design mit der Verarbeitung <strong>pseudonymer</strong> Spam-Telemetrie (Art. 6 (1) f &ndash; IT-Sicherheit; keine Klar-IP, kein Klartext, keine vollst&auml;ndigen E-Mail-Adressen). Details: <a {literal}:href="s.cockpit_endpoint || '#'"{/literal} target="_blank" rel="noopener noreferrer">Verarbeitungs-/AVV-Informationen</a>.</div>
            </label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.cockpit_avv_confirmed"{/literal}>
                <span class="bbf-toggle-slider"></span>
            </label>
        </div>
        <div class="bbf-form-help" style="margin-bottom: var(--bbf-spacing-md);" {literal}x-show="cockpitAvvAt"{/literal}>
            &#10003; AVV best&auml;tigt am <span {literal}x-text="cockpitAvvAt"{/literal}></span>.
        </div>

        {* Status-Readout *}
        <div class="bbf-form-help" style="margin-bottom: var(--bbf-spacing-md); opacity:.85;">
            Status: Ruleset-Version <strong {literal}x-text="cockpitRulesetVer"{/literal}></strong>
            &middot; Telemetrie <span {literal}x-text="cockpitLastRun ? 'zuletzt gesendet' : 'noch keine'"{/literal}></span>
            &middot; Ruleset-Pull <span {literal}x-text="cockpitLastPull ? 'aktiv' : 'noch keiner'"{/literal}></span>.
            <span {literal}x-show="!s.cockpit_endpoint || !cockpitAvvAt"{/literal}>Aktivierung: Endpoint + Secret einf&uuml;gen, AVV best&auml;tigen, Schalter an, speichern.</span>
        </div>

    </div>
    </div>

    <div {literal}x-show="activeTab==='alerts'"{/literal}>
    <div class="bbf-card">
        <h3 class="bbf-card-title" style="margin-bottom: var(--bbf-spacing-lg);">E-Mail-Benachrichtigung bei Spam-Welle</h3>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">Aktiviert</label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.email_alert_enabled"{/literal}>
                <span class="bbf-toggle-slider"></span>
            </label>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">E-Mail-Adresse</label>
            <input type="email" class="bbf-input" style="max-width: 300px;" placeholder="admin@shop.de" {literal}x-model="s.email_alert_address"{/literal}>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">Schwellenwert</label>
            <div style="display: flex; align-items: center; gap: 8px;">
                <input type="number" class="bbf-input" style="max-width: 80px;" min="1" {literal}x-model="s.email_alert_threshold"{/literal}>
                <span style="color: var(--bbf-muted);">Blocks in</span>
                <input type="number" class="bbf-input" style="max-width: 80px;" min="1" {literal}x-model="s.email_alert_window"{/literal}>
                <span style="color: var(--bbf-muted);">Minuten</span>
            </div>
        </div>

    </div>
    </div>

    <div {literal}x-show="activeTab==='sicherheit'"{/literal}>
    <div class="bbf-card">
        <h3 class="bbf-card-title" style="margin-bottom: var(--bbf-spacing-lg);">ALTCHA HMAC-Key</h3>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                HMAC-Key
                <div class="bbf-form-help">Wird automatisch generiert und w&ouml;chentlich rotiert</div>
            </label>
            <div style="display: flex; align-items: center; gap: 8px;">
                <code style="font-size: 12px; color: var(--bbf-muted); word-break: break-all;">{literal}<span x-text="s.altcha_hmac_key ? s.altcha_hmac_key.substring(0, 16) + '...' : 'Nicht gesetzt'"></span>{/literal}</code>
                <button type="button" class="bbf-btn bbf-btn-sm bbf-btn-secondary" {literal}@click="regenerateHmac()"{/literal}>Neu generieren</button>
            </div>
        </div>

    </div>

    {* ── ForgePush-Lizenz ── *}
    {literal}
    <div class="bbf-card" x-init="loadLicense()">
        <h3 class="bbf-card-title" style="margin-bottom: var(--bbf-spacing-lg);">ForgePush-Lizenz</h3>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md); align-items:center;">
            <label class="bbf-form-label">Status</label>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span class="bbf-badge" :class="licenseBadgeClass()" x-text="licenseVerdictLabel()"></span>
                <span style="font-size:12px; color:var(--bbf-muted);" x-show="lic.checkedAt"
                      x-text="'zuletzt geprüft: ' + licenseCheckedAgo()"></span>
            </div>
        </div>

        <div class="bbf-alert bbf-alert-danger" x-show="lic.hardViolation" x-cloak style="margin-bottom:12px;">
            Die Lizenzprüfung meldet ein klares Negativ-Verdikt (<span x-text="lic.verdict"></span>).
            Bitte die Lizenz in ForgePush prüfen. <strong>Der Spam-Schutz bleibt aktiv</strong> –
            eine Lizenzsache schaltet den Schutz bewusst nicht ab (sonst würde der Shop mit Spam geflutet).
        </div>
        <div class="bbf-alert bbf-alert-warning" x-show="lic.pluginMoved" x-cloak style="margin-bottom:12px;">
            ForgePush meldet einen Host-Wechsel dieser Installation
            (<span x-text="lic.pluginMoved && lic.pluginMoved.fromHost"></span> &rarr;
            <span x-text="lic.pluginMoved && lic.pluginMoved.toHost"></span>).
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">Host / Instanz
                <div class="bbf-form-help">Werden automatisch ermittelt und persistiert.</div>
            </label>
            <div style="font-size:12px; color:var(--bbf-muted);">
                <div x-text="'Host: ' + (lic.host || '–')"></div>
                <code x-text="'Instance: ' + (lic.instanceId ? lic.instanceId.substring(0,16)+'…' : '–')"></code>
            </div>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">Produkt-Slug
                <div class="bbf-form-help">Standard für dieses Plugin: <code>bbfcaptcha</code>. Nur überschreiben, wenn nötig.</div>
            </label>
            <input type="text" class="bbf-input" style="max-width:300px;" placeholder="bbfcaptcha (Standard)" x-model="licForm.product_slug">
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">Signing-Secret
                <div class="bbf-form-help">Produkt-spezifisches Secret aus ForgePush. Wird nur serverseitig gespeichert und nie wieder angezeigt. Leer lassen = unverändert.</div>
            </label>
            <input type="password" class="bbf-input" style="max-width:300px;" autocomplete="new-password"
                   :placeholder="lic.secretSet ? '•••••••• (gesetzt)' : 'nicht gesetzt'"
                   x-model="licForm.signing_secret">
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">License-Key (optional)
                <div class="bbf-form-help">Nur falls die Domain-Whitelist nicht reicht. Leer lassen = unverändert.</div>
            </label>
            <input type="password" class="bbf-input" style="max-width:300px;" autocomplete="new-password"
                   :placeholder="lic.keySet ? '•••••••• (gesetzt)' : 'nicht gesetzt'"
                   x-model="licForm.license_key">
        </div>

        <div style="margin-top: var(--bbf-spacing-lg); display:flex; gap:10px; flex-wrap:wrap;">
            <button type="button" class="bbf-btn bbf-btn-primary" @click="saveLicense()" :disabled="licBusy">Speichern &amp; prüfen</button>
            <button type="button" class="bbf-btn bbf-btn-secondary" @click="recheckLicense()" :disabled="licBusy">Jetzt prüfen</button>
        </div>
    </div>
    </div>

    <div class="bbf-settings-savebar">
        <button type="button" class="bbf-btn bbf-btn-primary" @click="saveAll()">Alle Einstellungen speichern</button>
        <span class="bbf-settings-savebar-hint">Speichert alle Tabs. Die ForgePush-Lizenz hat einen eigenen Speichern-Button.</span>
    </div>
    {/literal}
</div>

<script>
var bbfServerSettings = {$settingsJson nofilter};
</script>
<script>
{literal}
if (typeof Alpine !== 'undefined' && Alpine.data) {
    Alpine.data('bbfSettings', function() {
        var sv = window.bbfServerSettings || {};
        return {
            activeTab: 'allgemein',
            s: {
                global_enabled: sv.global_enabled === '1',
                default_action: sv.default_action || 'both',
                log_retention_days: sv.log_retention_days || 90,
                auto_cleanup: sv.auto_cleanup === '1',
                log_request_data: sv.log_request_data !== '0',
                debug_mode: sv.debug_mode === '1',
                email_alert_enabled: sv.email_alert_enabled === '1',
                email_alert_address: sv.email_alert_address || '',
                email_alert_threshold: sv.email_alert_threshold || 50,
                email_alert_window: sv.email_alert_window || 60,
                native_captcha_integration: sv.native_captcha_integration === '1',
                cockpit_enabled: sv.cockpit_enabled === '1',
                cockpit_endpoint: sv.cockpit_endpoint || '',
                cockpit_secret: '',
                cockpit_share_ip_prefix: sv.cockpit_share_ip_prefix === '1',
                cockpit_avv_confirmed: false,
                altcha_hmac_key: sv.altcha_hmac_key || ''
            },

            // ── Cockpit-Status (read-only, aus Server-Settings) ──
            cockpitAvvAt: sv.cockpit_avv_confirmed_at || '',
            cockpitRulesetVer: sv.cockpit_ruleset_version || '0',
            cockpitLastRun: sv.cockpit_last_run || '',
            cockpitLastPull: sv.cockpit_ruleset_last_pull || '',
            enrollKey: '',
            enrolling: false,

            // ── ForgePush-Lizenz ──
            lic: { configured:false, valid:false, verdict:'unknown', checkedAt:0, host:'', instanceId:'', secretSet:false, keySet:false, pluginMoved:null, hardViolation:false, productSlug:'' },
            licForm: { product_slug:'', signing_secret:'', license_key:'' },
            licBusy: false,

            loadLicense: function() {
                var self = this;
                bbfAdmin.post('getLicenseStatus').then(function(resp) {
                    if (resp.success && resp.data) {
                        self.lic = resp.data;
                        self.licForm.product_slug = resp.data.productSlug || '';
                    }
                }).catch(function(){});
            },

            saveLicense: function() {
                var self = this;
                self.licBusy = true;
                bbfAdmin.post('saveLicenseConfig', {
                    product_slug: self.licForm.product_slug || '',
                    signing_secret: self.licForm.signing_secret || '',
                    license_key: self.licForm.license_key || ''
                }).then(function(resp) {
                    self.licBusy = false;
                    if (resp.success) {
                        if (resp.data) self.lic = resp.data;
                        self.licForm.signing_secret = '';
                        self.licForm.license_key = '';
                        bbfAdmin.showNotification('Lizenz gespeichert & geprüft', 'success');
                    } else {
                        bbfAdmin.showNotification(resp.message || 'Fehler', 'error');
                    }
                }).catch(function(){ self.licBusy = false; bbfAdmin.showNotification('Fehler', 'error'); });
            },

            recheckLicense: function() {
                var self = this;
                self.licBusy = true;
                bbfAdmin.post('recheckLicense').then(function(resp) {
                    self.licBusy = false;
                    if (resp.success && resp.data) {
                        self.lic = resp.data;
                        bbfAdmin.showNotification('Lizenz geprüft', 'success');
                    } else {
                        bbfAdmin.showNotification(resp.message || 'Prüfung fehlgeschlagen', 'error');
                    }
                }).catch(function(){ self.licBusy = false; bbfAdmin.showNotification('Fehler', 'error'); });
            },

            licenseVerdictLabel: function() {
                if (!this.lic.configured && (this.lic.verdict === 'unknown' || this.lic.verdict === 'unconfigured' || !this.lic.verdict)) {
                    return 'Nicht konfiguriert';
                }
                var L = {
                    valid:'Gültig', expired:'Abgelaufen', suspended:'Ausgesetzt', revoked:'Widerrufen',
                    domain_mismatch:'Domain stimmt nicht', instance_limit_exceeded:'Instanz-Limit überschritten',
                    unlicensed:'Keine Lizenz zugeordnet', ambiguous_domain:'Domain mehrdeutig',
                    unconfigured:'Nicht konfiguriert', network_error:'Netzwerkfehler (Cache aktiv)',
                    unsigned:'Antwort unsigniert', signature_mismatch:'Signaturfehler',
                    stale_response:'Veraltete Antwort', unknown:'Unbekannt'
                };
                return L[this.lic.verdict] || this.lic.verdict || 'Unbekannt';
            },

            licenseBadgeClass: function() {
                if (!this.lic.configured) return 'bbf-badge-info';
                if (this.lic.valid) return 'bbf-badge-success';
                if (this.lic.hardViolation) return 'bbf-badge-danger';
                if (this.lic.verdict === 'unconfigured') return 'bbf-badge-info';
                return 'bbf-badge-warning';
            },

            licenseCheckedAgo: function() {
                if (!this.lic.checkedAt) return '–';
                var sec = Math.floor(Date.now()/1000) - this.lic.checkedAt;
                if (sec < 0) sec = 0;
                if (sec < 60) return 'gerade eben';
                var min = Math.floor(sec/60); if (min < 60) return 'vor ' + min + ' Min';
                var hr = Math.floor(min/60);  if (hr < 24)  return 'vor ' + hr + ' Std';
                return 'vor ' + Math.floor(hr/24) + ' Tg';
            },

            enroll: function() {
                var self = this;
                if (!this.s.cockpit_endpoint) { bbfAdmin.showNotification('Bitte zuerst den Cockpit-Endpoint eintragen.', 'error'); return; }
                if (!this.enrollKey) { bbfAdmin.showNotification('Bitte den Enrollment-Key eintragen.', 'error'); return; }
                if (!this.cockpitAvvAt && !this.s.cockpit_avv_confirmed) { bbfAdmin.showNotification('Bitte zuerst die AVV / Datenschutz bestätigen.', 'error'); return; }
                this.enrolling = true;
                bbfAdmin.post('cockpitEnroll', {
                    endpoint: this.s.cockpit_endpoint,
                    enrollment_secret: this.enrollKey,
                    avv_confirmed: this.s.cockpit_avv_confirmed ? '1' : '0'
                }).then(function(resp) {
                    self.enrolling = false;
                    bbfAdmin.showNotification(resp.message || (resp.success ? 'Angemeldet' : 'Fehler'), resp.success ? 'success' : 'error');
                    if (resp.success) {
                        self.s.cockpit_enabled = true;
                        if (!self.cockpitAvvAt) self.cockpitAvvAt = new Date().toISOString().slice(0,19).replace('T',' ');
                        self.enrollKey = '';
                    }
                }).catch(function() { self.enrolling = false; bbfAdmin.showNotification('Anmeldung fehlgeschlagen', 'error'); });
            },

            saveAll: function() {
                // CAP-08: Cockpit nur mit AVV-Bestätigung aktivierbar (Frontend-Guard; Backend prüft erneut).
                if (this.s.cockpit_enabled && !this.cockpitAvvAt && !this.s.cockpit_avv_confirmed) {
                    bbfAdmin.showNotification('Bitte zuerst die Auftragsverarbeitung (AVV) / Datenschutz bestätigen, um die zentrale Erkennung zu aktivieren.', 'error');
                    return;
                }
                var settings = {};
                for (var key in this.s) {
                    if (key === 'altcha_hmac_key') continue;
                    if (key === 'cockpit_secret' && !this.s.cockpit_secret) continue; // write-only: leer = behalten

                    settings[key] = typeof this.s[key] === 'boolean'
                        ? (this.s[key] ? '1' : '0')
                        : String(this.s[key]);
                }
                bbfAdmin.post('saveSettings', { settings: JSON.stringify(settings) }).then(function(resp) {
                    bbfAdmin.showNotification(resp.success ? 'Einstellungen gespeichert' : 'Fehler', resp.success ? 'success' : 'error');
                });
            },

            regenerateHmac: function() {
                var self = this;
                if (!confirm('HMAC-Key neu generieren? Aktive ALTCHA-Challenges werden ungültig.')) return;
                bbfAdmin.post('regenerateHmacKey').then(function(resp) {
                    if (resp.success) {
                        bbfAdmin.showNotification('HMAC-Key neu generiert', 'success');
                    }
                });
            }
        };
    });
}
{/literal}
</script>
