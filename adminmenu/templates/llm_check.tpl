<h2 class="bbf-page-title">{$langVars->getTranslation('nav_llm_check', $adminLang)|default:'LLM-Prüfung'|escape:'html'}</h2>
<p style="color: var(--bbf-text-light); font-size: var(--bbf-font-size-sm); margin-top: calc(var(--bbf-spacing-md) * -1); margin-bottom: var(--bbf-spacing-lg);">
    Optionale Zweitprüfung durch ein echtes Large Language Model. Ollama (lokal, kostenlos) oder
    OpenAI / Anthropic Claude / Google Gemini (API-Key nötig, kostenpflichtig). Wird nur ausgeführt, wenn
    „Aktivieren" gesetzt und Provider konfiguriert ist.
</p>

<div {literal}x-data="bbfLlmCheck()" x-init="init()"{/literal}>

    <div class="bbf-card">
        <h3 class="bbf-card-title" style="margin-bottom: var(--bbf-spacing-lg);">Konfiguration</h3>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">
                {$langVars->getTranslation('llm_enabled', $adminLang)|default:'LLM-Prüfung aktivieren'|escape:'html'}
                <div class="bbf-form-help">Wenn aktiviert, wird eingehender Text zusätzlich durch das gewählte LLM geprüft.</div>
            </label>
            <label class="bbf-toggle" aria-label="LLM-Pruefung aktivieren">
                <input type="checkbox" {literal}x-model="s.llm_enabled"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);">
            <label class="bbf-form-label">{$langVars->getTranslation('llm_provider', $adminLang)|default:'KI-Anbieter'|escape:'html'}</label>
            <select class="bbf-input bbf-select" style="max-width: 240px;" {literal}x-model="s.llm_provider"{/literal}>
                <option value="none">— keiner —</option>
                <option value="ollama">Ollama (lokal, kostenlos)</option>
                <option value="openai">OpenAI</option>
                <option value="claude">Anthropic Claude</option>
                <option value="gemini">Google Gemini</option>
            </select>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);" {literal}x-show="s.llm_provider !== 'none' && s.llm_provider !== 'ollama'"{/literal}>
            <label class="bbf-form-label">
                {$langVars->getTranslation('llm_api_key', $adminLang)|default:'API-Key'|escape:'html'}
                <div class="bbf-form-help">Für OpenAI / Claude / Gemini. Wird verschlüsselt an den jeweiligen Anbieter übertragen.</div>
            </label>
            <input type="password" class="bbf-input" style="max-width: 420px;" autocomplete="off"
                   {literal}x-model="s.llm_api_key"{/literal} placeholder="sk-...">
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);" {literal}x-show="s.llm_provider === 'ollama'"{/literal}>
            <label class="bbf-form-label">
                {$langVars->getTranslation('llm_endpoint', $adminLang)|default:'Endpoint-URL (nur für Ollama)'|escape:'html'}
                <div class="bbf-form-help">Default: http://localhost:11434 — sollte nur erreichbar sein, wenn Ollama im selben Netz läuft.</div>
            </label>
            <input type="url" class="bbf-input" style="max-width: 420px;" {literal}x-model="s.llm_endpoint"{/literal} placeholder="http://localhost:11434">
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);" {literal}x-show="s.llm_provider !== 'none'"{/literal}>
            <label class="bbf-form-label">
                {$langVars->getTranslation('llm_model', $adminLang)|default:'Modell'|escape:'html'}
                <div class="bbf-form-help" {literal}x-text="modelHint()"{/literal}></div>
            </label>
            <input type="text" class="bbf-input" style="max-width: 420px;" {literal}x-model="s.llm_model" :placeholder="modelPlaceholder()"{/literal}>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-md);" {literal}x-show="s.llm_provider !== 'none'"{/literal}>
            <label class="bbf-form-label">
                {$langVars->getTranslation('llm_only_borderline', $adminLang)|default:'Nur bei Grenzfällen prüfen'|escape:'html'}
                <div class="bbf-form-help">{$langVars->getTranslation('llm_only_borderline_help', $adminLang)|default:'LLM nur aufrufen, wenn der regelbasierte Score zwischen „OK" und „Spam" liegt. Spart API-Kosten.'|escape:'html'}</div>
            </label>
            <label class="bbf-toggle" aria-label="LLM nur bei Grenzfaellen aufrufen">
                <input type="checkbox" {literal}x-model="s.llm_only_borderline"{/literal}>
                <span class="bbf-toggle-slider" aria-hidden="true"></span>
            </label>
        </div>

        <div class="bbf-form-grid" style="margin-bottom: var(--bbf-spacing-lg);" {literal}x-show="s.llm_provider !== 'none'"{/literal}>
            <label class="bbf-form-label">{$langVars->getTranslation('llm_timeout', $adminLang)|default:'Timeout (Sekunden)'|escape:'html'}</label>
            <input type="number" class="bbf-input" style="max-width: 100px;" min="2" max="60" {literal}x-model="s.llm_timeout"{/literal}>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="button" class="bbf-btn bbf-btn-primary" {literal}@click="save()"{/literal}>Speichern</button>
            <button type="button" class="bbf-btn" {literal}@click="testConnection()" :disabled="testing || s.llm_provider === 'none'"{/literal}>
                <span {literal}x-show="!testing"{/literal}>{$langVars->getTranslation('llm_test_connection', $adminLang)|default:'Verbindung testen'|escape:'html'}</span>
                <span {literal}x-show="testing"{/literal}>Teste…</span>
            </button>
        </div>

        {literal}
        <div x-show="testResult" x-transition style="margin-top: var(--bbf-spacing-md);">
            <div class="bbf-alert" :class="testResult && testResult.success ? 'bbf-alert-success' : 'bbf-alert-danger'">
                <strong x-text="testResult && testResult.success ? 'OK' : 'Fehler'"></strong>
                <span x-text="testResult ? testResult.message : ''"></span>
                <div x-show="testResult && testResult.result" style="margin-top: 8px; font-size: 13px;">
                    <div>Klassifizierung: <strong x-text="testResult && testResult.result ? (testResult.result.spam ? 'SPAM' : 'kein Spam') : ''"></strong></div>
                    <div>Konfidenz: <strong x-text="testResult && testResult.result ? (testResult.result.confidence * 100).toFixed(0) + '%' : ''"></strong></div>
                    <div x-show="testResult && testResult.result && testResult.result.reason">Begründung: <span x-text="testResult && testResult.result ? testResult.result.reason : ''"></span></div>
                </div>
            </div>
        </div>
        {/literal}
    </div>

    <div class="bbf-card" {literal}x-show="s.llm_provider !== 'none'"{/literal}>
        <h3 class="bbf-card-title" style="margin-bottom: var(--bbf-spacing-md);">{$langVars->getTranslation('llm_test_text', $adminLang)|default:'Testtext'|escape:'html'}</h3>
        <textarea class="bbf-input bbf-textarea" placeholder="Text eingeben, der vom LLM klassifiziert werden soll…" {literal}x-model="testText"{/literal} rows="5"></textarea>
        <div style="margin-top: var(--bbf-spacing-md);">
            <button type="button" class="bbf-btn bbf-btn-primary" {literal}@click="classify()" :disabled="classifying || !s.llm_enabled"{/literal}>
                <span {literal}x-show="!classifying"{/literal}>Klassifizieren lassen</span>
                <span {literal}x-show="classifying"{/literal}>Läuft…</span>
            </button>
            <span {literal}x-show="!s.llm_enabled"{/literal} style="margin-left:12px;color:var(--bbf-text-light);font-size:13px;">Zuerst „Aktivieren" setzen und speichern.</span>
        </div>

        {literal}
        <div x-show="classifyResult" x-transition style="margin-top: var(--bbf-spacing-lg);">
            <div class="bbf-card" style="margin-bottom: 0; background: var(--bbf-bg-input);">
                <div style="display: flex; gap: var(--bbf-spacing-lg); margin-bottom: var(--bbf-spacing-md); flex-wrap: wrap;">
                    <div>
                        <div class="bbf-stat-label">Klassifizierung</div>
                        <div>
                            <span class="bbf-badge"
                                  :class="classifyResult && classifyResult.result && classifyResult.result.spam ? 'bbf-badge-danger' : 'bbf-badge-success'"
                                  x-text="classifyResult && classifyResult.result ? (classifyResult.result.spam ? 'SPAM' : 'KEIN SPAM') : ''"></span>
                        </div>
                    </div>
                    <div>
                        <div class="bbf-stat-label">Konfidenz</div>
                        <div class="bbf-stat-value" style="font-size: 1.5rem;" x-text="classifyResult && classifyResult.result ? (classifyResult.result.confidence * 100).toFixed(0) + '%' : ''"></div>
                    </div>
                    <div>
                        <div class="bbf-stat-label">Provider / Modell</div>
                        <div x-text="classifyResult && classifyResult.result ? classifyResult.result.provider + ' / ' + classifyResult.result.model : ''"></div>
                    </div>
                </div>
                <div x-show="classifyResult && classifyResult.result && classifyResult.result.reason">
                    <strong>Begründung:</strong>
                    <p style="margin-top: 8px; font-size: 13px; color: var(--bbf-muted);" x-text="classifyResult && classifyResult.result ? classifyResult.result.reason : ''"></p>
                </div>
                <div x-show="classifyResult && classifyResult.result && classifyResult.result.error" class="bbf-alert bbf-alert-danger" style="margin-top: 12px;">
                    <strong>Fehler:</strong> <span x-text="classifyResult && classifyResult.result ? classifyResult.result.error : ''"></span>
                </div>
            </div>
        </div>
        {/literal}
    </div>
</div>

<script>
var bbfServerSettings = {$settingsJson nofilter};
</script>
<script>
{literal}
if (typeof Alpine !== 'undefined' && Alpine.data) {
    Alpine.data('bbfLlmCheck', function() {
        var sv = window.bbfServerSettings || {};
        return {
            s: {
                llm_enabled:         sv.llm_enabled === '1',
                llm_provider:        sv.llm_provider || 'none',
                llm_api_key:         sv.llm_api_key || '',
                llm_model:           sv.llm_model || '',
                llm_endpoint:        sv.llm_endpoint || 'http://localhost:11434',
                llm_only_borderline: sv.llm_only_borderline !== '0',
                llm_timeout:         parseInt(sv.llm_timeout) || 8
            },
            testing: false,
            classifying: false,
            testResult: null,
            testText: '',
            classifyResult: null,

            init: function() {},

            modelPlaceholder: function() {
                switch (this.s.llm_provider) {
                    case 'ollama': return 'llama3.2';
                    case 'openai': return 'gpt-4o-mini';
                    case 'claude': return 'claude-haiku-4-5-20251001';
                    case 'gemini': return 'gemini-1.5-flash-latest';
                    default: return '';
                }
            },

            modelHint: function() {
                switch (this.s.llm_provider) {
                    case 'ollama': return 'Leer lassen für Default. Alle lokal installierten Ollama-Modelle sind möglich.';
                    case 'openai': return 'Empfohlen: gpt-4o-mini (günstig, schnell, JSON-Modus).';
                    case 'claude': return 'Empfohlen: claude-haiku-4-5-20251001 (günstigstes, schnelles Claude-Modell).';
                    case 'gemini': return 'Empfohlen: gemini-1.5-flash-latest (hat kostenloses Free-Tier).';
                    default: return '';
                }
            },

            save: function() {
                var settings = {};
                for (var k in this.s) {
                    settings[k] = typeof this.s[k] === 'boolean'
                        ? (this.s[k] ? '1' : '0')
                        : String(this.s[k]);
                }
                bbfAdmin.post('saveSettings', { settings: JSON.stringify(settings) }).then(function(resp) {
                    bbfAdmin.showNotification(
                        resp.success ? (resp.message || 'Gespeichert') : 'Fehler',
                        resp.success ? 'success' : 'error'
                    );
                });
            },

            testConnection: function() {
                var self = this;
                this.testing = true;
                this.testResult = null;
                // Erst speichern, damit der Backend-Service mit aktuellem Setup testet
                var settings = {};
                for (var k in this.s) {
                    settings[k] = typeof this.s[k] === 'boolean'
                        ? (this.s[k] ? '1' : '0')
                        : String(this.s[k]);
                }
                bbfAdmin.post('saveSettings', { settings: JSON.stringify(settings) }).then(function() {
                    return bbfAdmin.post('testLlmProvider');
                }).then(function(resp) {
                    self.testing = false;
                    self.testResult = resp;
                }).catch(function(err) {
                    self.testing = false;
                    self.testResult = { success: false, message: String(err && err.message || err) };
                });
            },

            classify: function() {
                var self = this;
                if (!this.testText.trim()) return;
                this.classifying = true;
                this.classifyResult = null;
                bbfAdmin.post('classifyWithLlm', { text: this.testText }).then(function(resp) {
                    self.classifying = false;
                    self.classifyResult = resp;
                }).catch(function(err) {
                    self.classifying = false;
                    self.classifyResult = { success: false, message: String(err && err.message || err) };
                });
            }
        };
    });
}
{/literal}
</script>
