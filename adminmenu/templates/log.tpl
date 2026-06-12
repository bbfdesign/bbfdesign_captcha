<h2 class="bbf-page-title">Spam-Log</h2>

<div {literal}x-data="bbfSpamLog()"{/literal}>
    {* ── Filter ── *}
    <div class="bbf-card" style="margin-bottom: var(--bbf-spacing-md);">
        <div style="display: flex; flex-wrap: wrap; gap: var(--bbf-spacing-sm); align-items: flex-end;">
            <div>
                <label style="font-size: 12px; color: var(--bbf-muted); display: block; margin-bottom: 4px;">Von</label>
                <input type="date" class="bbf-input" style="height: 36px; width: 150px;" {literal}x-model="filters.from"{/literal}>
            </div>
            <div>
                <label style="font-size: 12px; color: var(--bbf-muted); display: block; margin-bottom: 4px;">Bis</label>
                <input type="date" class="bbf-input" style="height: 36px; width: 150px;" {literal}x-model="filters.to"{/literal}>
            </div>
            <div>
                <label style="font-size: 12px; color: var(--bbf-muted); display: block; margin-bottom: 4px;">Formular</label>
                <select class="bbf-input bbf-select" style="height: 36px; width: 160px;" {literal}x-model="filters.form"{/literal}>
                    <option value="">Alle</option>
                    <option value="contact">Kontakt</option>
                    <option value="registration">Registrierung</option>
                    <option value="newsletter">Newsletter</option>
                    <option value="review">Bewertungen</option>
                    <option value="checkout">Checkout</option>
                    <option value="login">Login</option>
                </select>
            </div>
            <div>
                <label style="font-size: 12px; color: var(--bbf-muted); display: block; margin-bottom: 4px;">Methode</label>
                <select class="bbf-input bbf-select" style="height: 36px; width: 150px;" {literal}x-model="filters.method"{/literal}>
                    <option value="">Alle</option>
                    <option value="honeypot">Honeypot</option>
                    <option value="timing">Timing</option>
                    <option value="altcha">ALTCHA</option>
                    <option value="ai">Smart-Filter</option>
                    <option value="llm">LLM</option>
                    <option value="ip">IP-Block</option>
                    <option value="rate">Rate Limit</option>
                </select>
            </div>
            <div>
                <label style="font-size: 12px; color: var(--bbf-muted); display: block; margin-bottom: 4px;">IP</label>
                <input type="text" class="bbf-input" style="height: 36px; width: 140px;" placeholder="IP..." {literal}x-model="filters.ip"{/literal}>
            </div>
            <div>
                <label style="font-size: 12px; color: var(--bbf-muted); display: block; margin-bottom: 4px;">Aktion</label>
                <select class="bbf-input bbf-select" style="height: 36px; width: 130px;" {literal}x-model="filters.action"{/literal}>
                    <option value="">Alle</option>
                    <option value="blocked">Geblockt</option>
                    <option value="logged">Geloggt</option>
                    <option value="allowed">Erlaubt</option>
                </select>
            </div>
            <button type="button" class="bbf-btn bbf-btn-primary bbf-btn-sm" {literal}@click="loadLog(1)"{/literal}>Filtern</button>
            <button type="button" class="bbf-btn bbf-btn-secondary bbf-btn-sm" {literal}@click="exportCsv()"{/literal}>CSV Export</button>
        </div>
    </div>

    {* ── Log-Tabelle ── *}
    <div class="bbf-card">
        <div style="overflow-x: auto;">
            <table class="bbf-table">
                <thead>
                    <tr>
                        <th>Zeitpunkt</th>
                        <th>IP</th>
                        <th>Formular</th>
                        <th>Methode</th>
                        <th>Score</th>
                        <th>Aktion</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    {literal}
                    <template x-for="entry in logEntries" :key="entry.id">
                        <tr>
                            <td style="white-space: nowrap;" x-text="entry.created_at"></td>
                            <td><code x-text="entry.ip_address"></code></td>
                            <td x-text="entry.form_type"></td>
                            <td><span class="bbf-badge bbf-badge-info" x-text="entry.detection_method"></span></td>
                            <td x-text="entry.spam_score"></td>
                            <td>
                                <span class="bbf-badge"
                                      :class="{
                                          'bbf-badge-danger': entry.action_taken === 'blocked',
                                          'bbf-badge-warning': entry.action_taken === 'logged',
                                          'bbf-badge-success': entry.action_taken === 'allowed'
                                      }"
                                      x-text="entry.action_taken === 'blocked' ? 'Geblockt' : entry.action_taken === 'logged' ? 'Geloggt' : 'Erlaubt'"></span>
                            </td>
                            <td style="white-space: nowrap;">
                                <button type="button" class="bbf-btn bbf-btn-sm bbf-btn-danger" title="IP sperren"
                                        @click="blockIp(entry.ip_address)" style="padding: 4px 8px;">
                                    IP sperren
                                </button>
                                <button type="button" class="bbf-btn bbf-btn-sm bbf-btn-outline" title="Kein Spam"
                                        @click="markNotSpam(entry.id)" style="padding: 4px 8px;"
                                        :class="{ 'bbf-badge-success': entry.is_false_positive == 1 }">
                                    Kein Spam
                                </button>
                                <button type="button" class="bbf-btn bbf-btn-sm bbf-btn-secondary" title="Eingereichte Daten ansehen"
                                        @click="showDetail(entry)" style="padding: 4px 8px;">
                                    Details
                                </button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="logEntries.length === 0 && !loading">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 32px; color: var(--bbf-text-light);">
                                Keine Einträge gefunden.
                            </td>
                        </tr>
                    </template>
                    {/literal}
                </tbody>
            </table>
        </div>

        {* ── Paginierung ── *}
        {literal}
        <div class="bbf-pagination" x-show="totalPages > 1">
            <button type="button" @click="loadLog(currentPage - 1)" :disabled="currentPage <= 1">&laquo;</button>
            <template x-for="p in paginationRange()" :key="p">
                <button type="button" @click="loadLog(p)" :class="{ active: p === currentPage }" x-text="p"></button>
            </template>
            <button type="button" @click="loadLog(currentPage + 1)" :disabled="currentPage >= totalPages">&raquo;</button>
        </div>
        {/literal}

        <div style="text-align: center; margin-top: 8px; font-size: 12px; color: var(--bbf-muted);">
            {literal}<span x-text="totalEntries + ' Einträge'"></span>{/literal}
        </div>
    </div>

    {* ── Off-Canvas Detail: eingereichte Daten einer abgelehnten Anmeldung ── *}
    {literal}
    <div class="bbf-drawer-overlay" :class="{ 'is-open': detailEntry }"
         x-effect="document.body.style.overflow = detailEntry ? 'hidden' : ''"
         @keydown.escape.window="detailEntry = null">
        <button type="button" class="bbf-drawer-backdrop" @click="detailEntry = null" aria-label="Schließen"></button>
        <aside class="bbf-drawer" role="dialog" aria-modal="true">
            <div class="bbf-drawer-header">
                <div>
                    <h3>Eingereichte Daten</h3>
                    <div class="bbf-drawer-sub"
                         x-text="detailEntry ? (detailEntry.form_type + ' · ' + detailEntry.created_at) : ''"></div>
                </div>
                <button type="button" class="bbf-drawer-close" @click="detailEntry = null" title="Schlie&szlig;en" aria-label="Schlie&szlig;en">&times;</button>
            </div>
            <div class="bbf-drawer-body">
                <div x-show="detailEntry && detailEntry.reason" class="bbf-alert bbf-alert-warning" style="margin-bottom:16px;">
                    <strong>Begr&uuml;ndung:</strong> <span x-text="detailEntry ? detailEntry.reason : ''"></span>
                </div>
                <template x-if="detailEntry && Object.keys(detailEntry.fields).length">
                    <table class="bbf-table" style="width:100%;">
                        <template x-for="key in Object.keys(detailEntry.fields)" :key="key">
                            <tr>
                                <td style="width:170px; color:var(--bbf-muted); vertical-align:top;" x-text="key"></td>
                                <td><code style="white-space:pre-wrap; word-break:break-word;" x-text="detailEntry.fields[key]"></code></td>
                            </tr>
                        </template>
                    </table>
                </template>
                <template x-if="detailEntry && !Object.keys(detailEntry.fields).length">
                    <div style="color:var(--bbf-text-light);">
                        Keine gespeicherten Formulardaten zu diesem Eintrag
                        (erweitertes Logging war evtl. deaktiviert).
                    </div>
                </template>
                <div style="margin-top:16px; padding-top:12px; border-top:1px solid var(--bbf-border-light); font-size:12px; color:var(--bbf-muted);">
                    <span x-text="'Methode: ' + (detailEntry ? detailEntry.detection_method : '')"></span> &middot;
                    <span x-text="'Score: ' + (detailEntry ? detailEntry.spam_score : '')"></span> &middot;
                    <span x-text="'User-Agent: ' + (detailEntry ? (detailEntry.user_agent || '–') : '')"></span>
                </div>
            </div>
        </aside>
    </div>
    {/literal}
</div>

<script>
{literal}
if (typeof Alpine !== 'undefined' && Alpine.data) {
    Alpine.data('bbfSpamLog', function() {
        return {
            logEntries: [],
            detailEntry: null,
            loading: false,
            currentPage: 1,
            totalPages: 1,
            totalEntries: 0,
            filters: {
                from: '',
                to: '',
                form: '',
                method: '',
                ip: '',
                action: ''
            },

            init: function() {
                this.loadLog(1);
            },

            loadLog: function(page) {
                var self = this;
                this.loading = true;
                this.currentPage = page;
                var params = { logPage: page };
                if (this.filters.from) params.filter_from = this.filters.from;
                if (this.filters.to) params.filter_to = this.filters.to;
                if (this.filters.form) params.filter_form = this.filters.form;
                if (this.filters.method) params.filter_method = this.filters.method;
                if (this.filters.ip) params.filter_ip = this.filters.ip;
                if (this.filters.action) params.filter_action = this.filters.action;

                bbfAdmin.post('getSpamLog', params).then(function(resp) {
                    self.loading = false;
                    if (resp.success) {
                        self.logEntries = resp.data;
                        self.totalPages = resp.pages;
                        self.totalEntries = resp.total;
                    }
                }).catch(function() { self.loading = false; });
            },

            blockIp: function(ip) {
                bbfAdmin.post('blockIp', { ip: ip }).then(function(resp) {
                    bbfAdmin.showNotification(resp.success ? 'IP gesperrt' : 'Fehler', resp.success ? 'success' : 'error');
                });
            },

            markNotSpam: function(id) {
                bbfAdmin.post('markFalsePositive', { id: id, is_spam: 0 }).then(function(resp) {
                    if (resp.success) bbfAdmin.showNotification('Als Kein-Spam markiert', 'success');
                });
            },

            showDetail: function(entry) {
                var fields = {};
                var reason = '';
                try {
                    var raw = entry.request_data;
                    var parsed = (typeof raw === 'string') ? JSON.parse(raw) : raw;
                    if (parsed && typeof parsed === 'object') {
                        Object.keys(parsed).forEach(function(k) {
                            if (k === '_bbf_reason') { reason = String(parsed[k]); return; }
                            var v = parsed[k];
                            fields[k] = (v && typeof v === 'object') ? JSON.stringify(v) : String(v);
                        });
                    }
                } catch (e) {}
                this.detailEntry = {
                    form_type: entry.form_type,
                    created_at: entry.created_at,
                    detection_method: entry.detection_method,
                    spam_score: entry.spam_score,
                    user_agent: entry.user_agent,
                    reason: reason,
                    fields: fields
                };
            },

            exportCsv: function() {
                bbfAdmin.post('exportSpamLog').then(function(resp) {
                    if (resp.success && resp.data) {
                        var csv = 'ID;IP;Formular;Methode;Score;Aktion;User-Agent;Zeitpunkt\n';
                        resp.data.forEach(function(r) {
                            csv += [r.id, r.ip_address, r.form_type, r.detection_method, r.spam_score, r.action_taken, (r.user_agent || '').replace(/;/g, ','), r.created_at].join(';') + '\n';
                        });
                        var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
                        var a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = 'spam-log-' + new Date().toISOString().slice(0, 10) + '.csv';
                        a.click();
                    }
                });
            },

            paginationRange: function() {
                var pages = [];
                var start = Math.max(1, this.currentPage - 2);
                var end = Math.min(this.totalPages, this.currentPage + 2);
                for (var i = start; i <= end; i++) pages.push(i);
                return pages;
            }
        };
    });
}
{/literal}
</script>
