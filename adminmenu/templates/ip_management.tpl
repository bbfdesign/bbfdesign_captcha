<h2 class="bbf-page-title">IP-Verwaltung</h2>

<div {literal}x-data="bbfIpManagement()"{/literal}>
    <div class="bbf-tabs">
        <button type="button" class="bbf-tab" {literal}:class="{ active: tab === 'blacklist' }" @click="tab = 'blacklist'; loadEntries('blacklist')"{/literal}>Blacklist</button>
        <button type="button" class="bbf-tab" {literal}:class="{ active: tab === 'whitelist' }" @click="tab = 'whitelist'; loadEntries('whitelist')"{/literal}>Whitelist</button>
        <button type="button" class="bbf-tab" {literal}:class="{ active: tab === 'autoblock' }" @click="tab = 'autoblock'"{/literal}>Auto-Block Regeln</button>
    </div>

    {* ── Blacklist/Whitelist ── *}
    {literal}
    <template x-if="tab === 'blacklist' || tab === 'whitelist'">
        <div class="bbf-card">
            <div style="display: flex; gap: 8px; margin-bottom: var(--bbf-spacing-md); flex-wrap: wrap;">
                <input type="text" class="bbf-input" placeholder="IP-Adresse (z.B. 192.168.1.1)" style="max-width: 250px;" x-model="newIp">
                <input type="text" class="bbf-input" placeholder="CIDR (z.B. /24)" style="max-width: 100px;" x-model="newRange">
                <input type="text" class="bbf-input" placeholder="Grund (optional)" style="max-width: 250px;" x-model="newReason">
                <button type="button" class="bbf-btn bbf-btn-primary bbf-btn-sm" @click="addEntry()">Hinzufügen</button>
            </div>
            <table class="bbf-table">
                <thead>
                    <tr>
                        <th>IP-Adresse</th>
                        <th>CIDR</th>
                        <th>Grund</th>
                        <th>Auto</th>
                        <th>Ablauf</th>
                        <th>Erstellt</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="entry in entries" :key="entry.id">
                        <tr>
                            <td><code x-text="entry.ip_address"></code></td>
                            <td x-text="entry.ip_range || '—'"></td>
                            <td x-text="entry.reason || '—'"></td>
                            <td>
                                <span class="bbf-badge" :class="entry.auto_added == 1 ? 'bbf-badge-warning' : 'bbf-badge-info'"
                                      x-text="entry.auto_added == 1 ? 'Auto' : 'Manuell'"></span>
                            </td>
                            <td x-text="entry.expires_at || 'Permanent'"></td>
                            <td x-text="entry.created_at"></td>
                            <td>
                                <button type="button" class="bbf-btn bbf-btn-sm bbf-btn-danger" @click="deleteEntry(entry.id)">
                                    &times;
                                </button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="entries.length === 0">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 24px; color: var(--bbf-text-light);">
                                Keine Einträge vorhanden.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>
    {/literal}

    {* ── Auto-Block Regeln ── *}
    {literal}
    <template x-if="tab === 'autoblock'">
        <div class="bbf-card">
            <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
                <label class="bbf-form-label">Auto-Block aktiv</label>
                <label class="bbf-toggle">
                    <input type="checkbox" x-model="autoBlock.enabled">
                    <span class="bbf-toggle-slider"></span>
                </label>
            </div>
            <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
                <label class="bbf-form-label">Versuche</label>
                <div>
                    <input type="number" class="bbf-input" style="max-width: 100px;" x-model="autoBlock.attempts" min="1">
                    <div class="bbf-form-help">Spam-Versuche bis zur Sperre</div>
                </div>
            </div>
            <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
                <label class="bbf-form-label">Zeitfenster (Min.)</label>
                <div>
                    <input type="number" class="bbf-input" style="max-width: 100px;" x-model="autoBlock.window" min="1">
                    <div class="bbf-form-help">Innerhalb dieser Zeitspanne</div>
                </div>
            </div>
            <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-lg);">
                <label class="bbf-form-label">Sperrdauer (Min.)</label>
                <div>
                    <input type="number" class="bbf-input" style="max-width: 120px;" x-model="autoBlock.duration" min="1">
                    <div class="bbf-form-help">60 = 1h, 1440 = 24h, 10080 = 7d, 0 = permanent</div>
                </div>
            </div>
            <button type="button" class="bbf-btn bbf-btn-primary" @click="saveAutoBlock()">Speichern</button>
        </div>
    </template>
    {/literal}
</div>

<script>
var bbfServerSettings = {$settingsJson nofilter};
</script>
<script>
{literal}
document.addEventListener('alpine:init', function() {
    Alpine.data('bbfIpManagement', function() {
        var sv = window.bbfServerSettings || {};
        return {
            tab: 'blacklist',
            entries: [],
            newIp: '',
            newRange: '',
            newReason: '',
            autoBlock: {
                enabled: sv.ip_auto_block_enabled === '1',
                attempts: parseInt(sv.ip_auto_block_attempts) || 5,
                window: parseInt(sv.ip_auto_block_window) || 10,
                duration: parseInt(sv.ip_auto_block_duration) || 1440
            },

            init: function() {
                this.loadEntries('blacklist');
            },

            loadEntries: function(type) {
                var self = this;
                bbfAdmin.post('getIpEntries', { entry_type: type }).then(function(resp) {
                    if (resp.success) self.entries = resp.data;
                });
            },

            addEntry: function() {
                var self = this;
                if (!this.newIp.trim()) return;
                bbfAdmin.post('addIpEntry', {
                    ip: this.newIp,
                    ip_range: this.newRange,
                    entry_type: this.tab,
                    reason: this.newReason
                }).then(function(resp) {
                    if (resp.success) {
                        self.newIp = '';
                        self.newRange = '';
                        self.newReason = '';
                        self.loadEntries(self.tab);
                        bbfAdmin.showNotification('IP hinzugefügt', 'success');
                    }
                });
            },

            deleteEntry: function(id) {
                var self = this;
                bbfAdmin.post('deleteIpEntry', { id: id }).then(function(resp) {
                    if (resp.success) self.loadEntries(self.tab);
                });
            },

            saveAutoBlock: function() {
                var settings = {
                    ip_auto_block_enabled: this.autoBlock.enabled ? '1' : '0',
                    ip_auto_block_attempts: String(this.autoBlock.attempts),
                    ip_auto_block_window: String(this.autoBlock.window),
                    ip_auto_block_duration: String(this.autoBlock.duration)
                };
                bbfAdmin.post('saveSettings', { settings: JSON.stringify(settings) }).then(function(resp) {
                    bbfAdmin.showNotification(resp.success ? 'Gespeichert' : 'Fehler', resp.success ? 'success' : 'error');
                });
            }
        };
    });
});
{/literal}
</script>
