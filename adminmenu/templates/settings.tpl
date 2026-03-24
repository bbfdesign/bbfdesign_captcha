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
            <label class="bbf-form-label">Auto-Cleanup</label>
            <label class="bbf-toggle">
                <input type="checkbox" {literal}x-model="s.auto_cleanup"{/literal}>
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
</div>

<script>
var bbfServerSettings = {$settingsJson nofilter};
</script>
<script>
{literal}
document.addEventListener('alpine:init', function() {
    Alpine.data('bbfSettings', function() {
        var sv = window.bbfServerSettings || {};
        return {
            s: {
                global_enabled: sv.global_enabled === '1',
                default_action: sv.default_action || 'both',
                log_retention_days: sv.log_retention_days || 90,
                auto_cleanup: sv.auto_cleanup === '1',
                debug_mode: sv.debug_mode === '1',
                email_alert_enabled: sv.email_alert_enabled === '1',
                email_alert_address: sv.email_alert_address || '',
                email_alert_threshold: sv.email_alert_threshold || 50,
                email_alert_window: sv.email_alert_window || 60,
                altcha_hmac_key: sv.altcha_hmac_key || ''
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
});
{/literal}
</script>
