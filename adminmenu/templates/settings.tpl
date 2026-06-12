<h2 class="bbf-page-title">Einstellungen</h2>

<div {literal}x-data="bbfSettings()"{/literal}>
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

        <hr style="border-color: var(--bbf-border-light); margin: var(--bbf-spacing-lg) 0;">
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

        <div style="margin-top: var(--bbf-spacing-xl);">
            <button type="button" class="bbf-btn bbf-btn-primary" {literal}@click="saveAll()"{/literal}>Alle Einstellungen speichern</button>
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
            Der Spam-Schutz ist deshalb derzeit <strong>deaktiviert</strong> (Fail-closed). Bitte die
            Lizenz in ForgePush prüfen; nach Behebung reaktiviert sich der Schutz beim nächsten Check automatisch.
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
                altcha_hmac_key: sv.altcha_hmac_key || ''
            },

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

            saveAll: function() {
                var settings = {};
                for (var key in this.s) {
                    if (key === 'altcha_hmac_key') continue;
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
