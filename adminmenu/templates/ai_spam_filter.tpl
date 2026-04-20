<h2 class="bbf-page-title">{$langVars->getTranslation('nav_ai_filter', $adminLang)|default:'Smart-Spamfilter'|escape:'html'}</h2>
<p class="bbf-muted" style="margin-top:-var(--bbf-spacing-md);margin-bottom:var(--bbf-spacing-lg);color:var(--bbf-text-light);font-size:var(--bbf-font-size-sm);">
    Regelbasierte Textanalyse (URLs, Sprache, Wortlisten, Disposable-Emails, Wiederholungen). F&uuml;r eine echte LLM-Pr&uuml;fung siehe Men&uuml;punkt „LLM-Pr&uuml;fung".
</p>

<div {literal}x-data="bbfAiFilter()"{/literal}>
    {* ── Tabs ── *}
    <div class="bbf-tabs" role="tablist" aria-label="Smart-Spamfilter-Ansicht">
        <button type="button" class="bbf-tab" role="tab" id="bbf-ai-tab-settings" aria-controls="bbf-ai-panel-settings" {literal}:aria-selected="tab === 'settings' ? 'true' : 'false'" :tabindex="tab === 'settings' ? 0 : -1" :class="{ active: tab === 'settings' }" @click="tab = 'settings'"{/literal}>Einstellungen</button>
        <button type="button" class="bbf-tab" role="tab" id="bbf-ai-tab-words" aria-controls="bbf-ai-panel-words" {literal}:aria-selected="tab === 'words' ? 'true' : 'false'" :tabindex="tab === 'words' ? 0 : -1" :class="{ active: tab === 'words' }" @click="tab = 'words'"{/literal}>Spam-W&ouml;rter</button>
        <button type="button" class="bbf-tab" role="tab" id="bbf-ai-tab-domains" aria-controls="bbf-ai-panel-domains" {literal}:aria-selected="tab === 'domains' ? 'true' : 'false'" :tabindex="tab === 'domains' ? 0 : -1" :class="{ active: tab === 'domains' }" @click="tab = 'domains'"{/literal}>Disposable Domains</button>
        <button type="button" class="bbf-tab" role="tab" id="bbf-ai-tab-test" aria-controls="bbf-ai-panel-test" {literal}:aria-selected="tab === 'test' ? 'true' : 'false'" :tabindex="tab === 'test' ? 0 : -1" :class="{ active: tab === 'test' }" @click="tab = 'test'"{/literal}>Test</button>
    </div>

    {* ── Settings Tab ── *}
    <div class="bbf-tab-content" role="tabpanel" id="bbf-ai-panel-settings" aria-labelledby="bbf-ai-tab-settings" {literal}:hidden="tab !== 'settings'" :class="{ active: tab === 'settings' }"{/literal}>
        <div class="bbf-card">
            <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
                <label class="bbf-form-label">Schwelle: OK</label>
                <div>
                    <input type="number" class="bbf-input" style="max-width: 100px;" {literal}x-model="aiSettings.ai_threshold_ok"{/literal}>
                    <div class="bbf-form-help">Punkte (Standard: 30)</div>
                </div>
            </div>
            <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
                <label class="bbf-form-label">Schwelle: Verd&auml;chtig</label>
                <div>
                    <input type="number" class="bbf-input" style="max-width: 100px;" {literal}x-model="aiSettings.ai_threshold_suspicious"{/literal}>
                    <div class="bbf-form-help">Punkte (Standard: 60)</div>
                </div>
            </div>
            <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
                <label class="bbf-form-label">Schwelle: Spam</label>
                <div>
                    <input type="number" class="bbf-input" style="max-width: 100px;" {literal}x-model="aiSettings.ai_threshold_spam"{/literal}>
                    <div class="bbf-form-help">Punkte (Standard: 100)</div>
                </div>
            </div>
            <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
                <label class="bbf-form-label" for="bbf-ai-lang-check">Spracherkennung</label>
                <label class="bbf-toggle" aria-label="Spracherkennung aktivieren">
                    <input type="checkbox" id="bbf-ai-lang-check" {literal}x-model="aiSettings.ai_check_language"{/literal}>
                    <span class="bbf-toggle-slider" aria-hidden="true"></span>
                </label>
            </div>
            <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-lg);">
                <label class="bbf-form-label" for="bbf-ai-disposable-check">Wegwerf-E-Mails</label>
                <label class="bbf-toggle" aria-label="Wegwerf-E-Mail-Pruefung aktivieren">
                    <input type="checkbox" id="bbf-ai-disposable-check" {literal}x-model="aiSettings.ai_check_disposable_email"{/literal}>
                    <span class="bbf-toggle-slider" aria-hidden="true"></span>
                </label>
            </div>
            <button type="button" class="bbf-btn bbf-btn-primary" {literal}@click="saveAiSettings()"{/literal}>Speichern</button>
        </div>
    </div>

    {* ── Spam Words Tab ── *}
    <div class="bbf-tab-content" role="tabpanel" id="bbf-ai-panel-words" aria-labelledby="bbf-ai-tab-words" {literal}:hidden="tab !== 'words'" :class="{ active: tab === 'words' }"{/literal}>
        <div class="bbf-card">
            <div style="display: flex; gap: 8px; margin-bottom: var(--bbf-spacing-md);">
                <input type="text" class="bbf-input" placeholder="Neues Spam-Wort..." style="max-width: 300px;" {literal}x-model="newWord"{/literal}>
                <input type="number" class="bbf-input" placeholder="Gewicht" style="max-width: 100px;" {literal}x-model="newWordWeight"{/literal}>
                <button type="button" class="bbf-btn bbf-btn-primary bbf-btn-sm" {literal}@click="addWord()"{/literal}>Hinzuf&uuml;gen</button>
            </div>
            <table class="bbf-table">
                <thead>
                    <tr>
                        <th scope="col">Wort</th>
                        <th scope="col">Kategorie</th>
                        <th scope="col">Gewicht</th>
                        <th scope="col">Gelernt</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    {literal}
                    <template x-for="word in spamWords" :key="word.id">
                        <tr>
                            <td x-text="word.word"></td>
                            <td><span class="bbf-badge" :class="word.category === 'spam' ? 'bbf-badge-danger' : 'bbf-badge-success'" x-text="word.category"></span></td>
                            <td x-text="word.weight"></td>
                            <td x-text="word.auto_learned == 1 ? 'Ja' : 'Nein'"></td>
                            <td>
                                <button type="button" class="bbf-btn bbf-btn-sm bbf-btn-danger" @click="deleteWord(word.id)">
                                    &times;
                                </button>
                            </td>
                        </tr>
                    </template>
                    {/literal}
                </tbody>
            </table>
        </div>
    </div>

    {* ── Disposable Domains Tab ── *}
    <div class="bbf-tab-content" role="tabpanel" id="bbf-ai-panel-domains" aria-labelledby="bbf-ai-tab-domains" {literal}:hidden="tab !== 'domains'" :class="{ active: tab === 'domains' }"{/literal}>
        <div class="bbf-card">
            <div class="bbf-alert bbf-alert-info">
                Die Liste der Wegwerf-E-Mail-Domains wird in Phase 5 vollst&auml;ndig implementiert.
                Aktuell k&ouml;nnen Domains manuell hinzugef&uuml;gt werden.
            </div>
        </div>
    </div>

    {* ── Test Tab ── *}
    <div class="bbf-tab-content" role="tabpanel" id="bbf-ai-panel-test" aria-labelledby="bbf-ai-tab-test" {literal}:hidden="tab !== 'test'" :class="{ active: tab === 'test' }"{/literal}>
        <div class="bbf-card">
            <h3 class="bbf-card-title" style="margin-bottom: var(--bbf-spacing-md);">Spam-Score testen</h3>
            <textarea class="bbf-input bbf-textarea" placeholder="Text eingeben..." {literal}x-model="testText"{/literal}></textarea>
            <div style="margin-top: var(--bbf-spacing-md);">
                <button type="button" class="bbf-btn bbf-btn-primary" {literal}@click="testFilter()"{/literal}>Score berechnen</button>
            </div>
            {literal}
            <div x-show="testResult !== null" x-transition style="margin-top: var(--bbf-spacing-lg);">
                <div class="bbf-card" style="margin-bottom: 0;">
                    <div style="display: flex; align-items: center; gap: var(--bbf-spacing-lg); margin-bottom: var(--bbf-spacing-md);">
                        <div>
                            <div class="bbf-stat-label">Score</div>
                            <div class="bbf-stat-value" x-text="testResult ? testResult.score : ''"></div>
                        </div>
                        <div>
                            <div class="bbf-stat-label">Bewertung</div>
                            <div>
                                <span class="bbf-badge"
                                      :class="{
                                          'bbf-badge-success': testResult && testResult.score <= 30,
                                          'bbf-badge-warning': testResult && testResult.score > 30 && testResult.score <= 60,
                                          'bbf-badge-danger': testResult && testResult.score > 60
                                      }"
                                      x-text="testResult ? testResult.verdict : ''"></span>
                            </div>
                        </div>
                    </div>
                    <div x-show="testResult && testResult.details && testResult.details.length > 0">
                        <strong>Details:</strong>
                        <ul style="margin-top: 8px;">
                            <template x-for="detail in (testResult ? testResult.details : [])" :key="detail">
                                <li x-text="detail" style="font-size: 13px; color: var(--bbf-muted);"></li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
            {/literal}
        </div>
    </div>
</div>

<script>
var bbfServerSettings = {$settingsJson nofilter};
</script>
<script>
{literal}
if (typeof Alpine !== 'undefined' && Alpine.data) {
    Alpine.data('bbfAiFilter', function() {
        var sv = window.bbfServerSettings || {};
        return {
            tab: 'settings',
            aiSettings: {
                ai_threshold_ok: parseInt(sv.ai_threshold_ok) || 30,
                ai_threshold_suspicious: parseInt(sv.ai_threshold_suspicious) || 60,
                ai_threshold_spam: parseInt(sv.ai_threshold_spam) || 100,
                ai_check_language: sv.ai_check_language === '1',
                ai_check_disposable_email: sv.ai_check_disposable_email === '1'
            },
            spamWords: [],
            newWord: '',
            newWordWeight: 25,
            testText: '',
            testResult: null,

            init: function() {
                this.loadSpamWords();
            },

            saveAiSettings: function() {
                var settings = {};
                for (var key in this.aiSettings) {
                    settings[key] = typeof this.aiSettings[key] === 'boolean'
                        ? (this.aiSettings[key] ? '1' : '0')
                        : String(this.aiSettings[key]);
                }
                bbfAdmin.post('saveSettings', { settings: JSON.stringify(settings) }).then(function(resp) {
                    bbfAdmin.showNotification(resp.success ? 'Gespeichert' : 'Fehler', resp.success ? 'success' : 'error');
                });
            },

            loadSpamWords: function() {
                var self = this;
                bbfAdmin.post('getSpamWords').then(function(resp) {
                    if (resp.success) self.spamWords = resp.data;
                });
            },

            addWord: function() {
                var self = this;
                if (!this.newWord.trim()) return;
                bbfAdmin.post('addSpamWord', {
                    word: this.newWord, weight: this.newWordWeight
                }).then(function(resp) {
                    if (resp.success) {
                        self.newWord = '';
                        self.loadSpamWords();
                        bbfAdmin.showNotification('Wort hinzugefügt', 'success');
                    }
                });
            },

            deleteWord: function(id) {
                var self = this;
                bbfAdmin.post('deleteSpamWord', { id: id }).then(function(resp) {
                    if (resp.success) self.loadSpamWords();
                });
            },

            testFilter: function() {
                var self = this;
                if (!this.testText.trim()) return;
                bbfAdmin.post('testAiFilter', { text: this.testText }).then(function(resp) {
                    if (resp.success) self.testResult = resp;
                });
            }
        };
    });
}
{/literal}
</script>
