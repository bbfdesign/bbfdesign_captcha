<h2 class="bbf-page-title">API</h2>

<div {literal}x-data="bbfApiManagement()"{/literal}>
    <div class="bbf-tabs">
        <button type="button" class="bbf-tab" {literal}:class="{ active: tab === 'keys' }" @click="tab = 'keys'"{/literal}>API-Keys</button>
        <button type="button" class="bbf-tab" {literal}:class="{ active: tab === 'docs' }" @click="tab = 'docs'"{/literal}>Dokumentation</button>
    </div>

    {* ── API Keys ── *}
    <div class="bbf-tab-content" {literal}:class="{ active: tab === 'keys' }"{/literal}>
        <div class="bbf-card">
            <div style="margin-bottom: var(--bbf-spacing-md);">
                <button type="button" class="bbf-btn bbf-btn-primary" {literal}@click="showCreateModal = true"{/literal}>Neuen API-Key erstellen</button>
            </div>

            <table class="bbf-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Berechtigungen</th>
                        <th>Rate Limit</th>
                        <th>Aktiv</th>
                        <th>Letzte Nutzung</th>
                        <th>Erstellt</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {literal}
                    <template x-for="key in apiKeys" :key="key.id">
                        <tr>
                            <td x-text="key.key_name"></td>
                            <td>
                                <template x-for="perm in parsePermissions(key.permissions)" :key="perm">
                                    <span class="bbf-badge bbf-badge-info" style="margin-right: 4px;" x-text="perm"></span>
                                </template>
                            </td>
                            <td x-text="key.rate_limit + '/min'"></td>
                            <td>
                                <span class="bbf-badge" :class="key.is_active == 1 ? 'bbf-badge-success' : 'bbf-badge-danger'"
                                      x-text="key.is_active == 1 ? 'Aktiv' : 'Inaktiv'"></span>
                            </td>
                            <td x-text="key.last_used_at || 'Nie'"></td>
                            <td x-text="key.created_at"></td>
                            <td>
                                <button type="button" class="bbf-btn bbf-btn-sm bbf-btn-danger" @click="deleteKey(key.id)">&times;</button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="apiKeys.length === 0">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 24px; color: var(--bbf-text-light);">
                                Noch keine API-Keys erstellt.
                            </td>
                        </tr>
                    </template>
                    {/literal}
                </tbody>
            </table>
        </div>

        {* ── Create Modal ── *}
        {literal}
        <div x-show="showCreateModal" x-transition class="bbf-modal-backdrop" @click.self="showCreateModal = false">
            <div class="bbf-modal">
                <div class="bbf-modal-header">
                    <h3 class="bbf-modal-title">Neuen API-Key erstellen</h3>
                    <button type="button" class="bbf-modal-close" @click="showCreateModal = false">&times;</button>
                </div>
                <div class="bbf-modal-body">
                    <div style="margin-bottom: var(--bbf-spacing-md);">
                        <label class="bbf-form-label">Name</label>
                        <input type="text" class="bbf-input" placeholder="z.B. Externes Plugin" x-model="newKeyName">
                    </div>
                    <div x-show="createdKey" class="bbf-alert bbf-alert-warning">
                        <strong>API-Key (nur einmal sichtbar!):</strong><br>
                        <code x-text="createdKey" style="word-break: break-all;"></code>
                        <button type="button" class="bbf-btn bbf-btn-sm bbf-btn-primary" style="margin-top: 8px;"
                                @click="window.bbfAdmin.copyToClipboard(createdKey)">Kopieren</button>
                    </div>
                </div>
                <div class="bbf-modal-footer" x-show="!createdKey">
                    <button type="button" class="bbf-btn bbf-btn-secondary" @click="showCreateModal = false">Abbrechen</button>
                    <button type="button" class="bbf-btn bbf-btn-primary" @click="createKey()">Erstellen</button>
                </div>
            </div>
        </div>
        {/literal}
    </div>

    {* ── API Docs ── *}
    <div class="bbf-tab-content" {literal}:class="{ active: tab === 'docs' }"{/literal}>
        <div class="bbf-card">
            <h3 class="bbf-card-title">REST-API Endpunkte</h3>
            <p style="color: var(--bbf-muted); margin-bottom: var(--bbf-spacing-md);">
                Authentifizierung via Header: <code>X-BBF-Captcha-Key: {ldelim}key{rdelim}</code>
            </p>

            <table class="bbf-table">
                <thead>
                    <tr><th>Methode</th><th>Endpunkt</th><th>Beschreibung</th></tr>
                </thead>
                <tbody>
                    <tr><td><span class="bbf-badge bbf-badge-success">POST</span></td><td><code>/api/v1/validate</code></td><td>Formular-Submission validieren</td></tr>
                    <tr><td><span class="bbf-badge bbf-badge-info">GET</span></td><td><code>/api/v1/challenge</code></td><td>ALTCHA Challenge generieren</td></tr>
                    <tr><td><span class="bbf-badge bbf-badge-info">GET</span></td><td><code>/api/v1/stats</code></td><td>Dashboard-Statistiken</td></tr>
                    <tr><td><span class="bbf-badge bbf-badge-info">GET</span></td><td><code>/api/v1/stats/today</code></td><td>Heute geblockte Versuche</td></tr>
                    <tr><td><span class="bbf-badge bbf-badge-success">POST</span></td><td><code>/api/v1/ip/check</code></td><td>IP gegen Blacklist pr&uuml;fen</td></tr>
                    <tr><td><span class="bbf-badge bbf-badge-success">POST</span></td><td><code>/api/v1/ip/block</code></td><td>IP sperren</td></tr>
                    <tr><td><span class="bbf-badge bbf-badge-danger">DELETE</span></td><td><code>/api/v1/ip/block/{ldelim}ip{rdelim}</code></td><td>IP entsperren</td></tr>
                    <tr><td><span class="bbf-badge bbf-badge-info">GET</span></td><td><code>/api/v1/log</code></td><td>Spam-Log (paginiert)</td></tr>
                    <tr><td><span class="bbf-badge bbf-badge-info">GET</span></td><td><code>/api/v1/methods</code></td><td>Aktive Methoden</td></tr>
                    <tr><td><span class="bbf-badge bbf-badge-info">GET</span></td><td><code>/api/v1/health</code></td><td>Health Check</td></tr>
                </tbody>
            </table>

            <h3 class="bbf-card-title" style="margin-top: var(--bbf-spacing-xl);">PHP-API (f&uuml;r JTL-Plugins)</h3>
            <pre style="background: var(--bbf-bg-input); padding: 16px; border-radius: 8px; font-size: 13px; overflow-x: auto; margin-top: var(--bbf-spacing-md);">{literal}// Captcha-Widget HTML holen
$captchaService = new \Plugin\bbfdesign_captcha\Services\CaptchaService($plugin);
$widgetHtml = $captchaService->renderWidget('mein_formular');

// Submission validieren
$result = $captchaService->validate($_POST, 'mein_formular');
if (!$result->isValid()) {
    $reason = $result->getReason();
    $score = $result->getScore();
}{/literal}</pre>
        </div>
    </div>
</div>

<script>
{literal}
if (typeof Alpine !== 'undefined' && Alpine.data) {
    Alpine.data('bbfApiManagement', function() {
        return {
            tab: 'keys',
            apiKeys: [],
            showCreateModal: false,
            newKeyName: '',
            createdKey: '',

            init: function() {
                this.loadKeys();
            },

            loadKeys: function() {
                var self = this;
                bbfAdmin.post('getApiKeys').then(function(resp) {
                    if (resp.success) self.apiKeys = resp.data;
                });
            },

            createKey: function() {
                var self = this;
                if (!this.newKeyName.trim()) return;
                bbfAdmin.post('createApiKey', { key_name: this.newKeyName }).then(function(resp) {
                    if (resp.success) {
                        self.createdKey = resp.key;
                        self.loadKeys();
                    }
                });
            },

            deleteKey: function(id) {
                var self = this;
                if (!confirm('API-Key wirklich löschen?')) return;
                bbfAdmin.post('deleteApiKey', { id: id }).then(function(resp) {
                    if (resp.success) self.loadKeys();
                });
            },

            parsePermissions: function(perms) {
                if (typeof perms === 'string') {
                    try { return JSON.parse(perms); } catch(e) { return []; }
                }
                return Array.isArray(perms) ? perms : [];
            }
        };
    });
}
{/literal}
</script>
