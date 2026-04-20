<h2 class="bbf-page-title">Formular-Schutz</h2>

<p style="color: var(--bbf-muted); margin-bottom: var(--bbf-spacing-lg);">
    W&auml;hlen Sie pro Formular, welche Schutzmethoden aktiv sein sollen.
</p>

<div {literal}x-data="bbfFormProtection()"{/literal}>

    <div class="bbf-card">
        <div style="overflow-x: auto;">
            <table class="bbf-table">
                <thead>
                    <tr>
                        <th>Formular</th>
                        <th>Methoden</th>
                        <th>Score-Schwelle</th>
                        <th>Aktion bei Spam</th>
                        <th>Aktiv</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {literal}
                    <template x-for="(form, index) in forms" :key="form.form_type">
                        <tr>
                            <td>
                                <strong x-text="formLabels[form.form_type] || form.form_type"></strong>
                            </td>
                            <td>
                                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                    <template x-for="method in availableMethods" :key="method.key">
                                        <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; cursor: pointer; padding: 2px 8px; border-radius: 12px; border: 1px solid var(--bbf-border);"
                                               :style="isMethodActive(form, method.key) ? 'background: var(--bbf-primary-light); border-color: var(--bbf-primary); color: var(--bbf-primary);' : ''">
                                            <input type="checkbox"
                                                   :checked="isMethodActive(form, method.key)"
                                                   @change="toggleMethod(form, method.key)"
                                                   style="width: 12px; height: 12px;">
                                            <span x-text="method.label"></span>
                                        </label>
                                    </template>
                                </div>
                            </td>
                            <td>
                                <input type="number" class="bbf-input" style="width: 80px; height: 36px; padding: 4px 8px;"
                                       x-model="form.score_threshold" min="0" max="200"
                                       @change="saveForm(form)">
                            </td>
                            <td>
                                <select class="bbf-input bbf-select" style="width: 120px; height: 36px; padding: 4px 8px;"
                                        x-model="form.action_on_spam" @change="saveForm(form)">
                                    <option value="block">Blockieren</option>
                                    <option value="log">Nur loggen</option>
                                    <option value="both">Beides</option>
                                </select>
                            </td>
                            <td>
                                <label class="bbf-toggle">
                                    <input type="checkbox" x-model="form.is_active" @change="saveForm(form)"
                                           :true-value="1" :false-value="0">
                                    <span class="bbf-toggle-slider"></span>
                                </label>
                            </td>
                            <td>
                                <button type="button" class="bbf-btn bbf-btn-sm bbf-btn-secondary"
                                        @click="saveForm(form)">Speichern</button>
                            </td>
                        </tr>
                    </template>
                    {/literal}
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
var bbfFormConfigs = {$formConfigsJson nofilter};
</script>
<script>
{literal}
if (typeof Alpine !== 'undefined' && Alpine.data) {
    Alpine.data('bbfFormProtection', function() {
        var rawConfigs = window.bbfFormConfigs || [];
        return {
            forms: rawConfigs.map(function(f) {
                f.methods = typeof f.methods === 'string' ? JSON.parse(f.methods) : (f.methods || []);
                f.is_active = parseInt(f.is_active) || 0;
                f.score_threshold = parseInt(f.score_threshold) || 60;
                return f;
            }),
            formLabels: {
                'contact': 'Kontaktformular',
                'registration': 'Registrierung',
                'newsletter': 'Newsletter',
                'review': 'Produktbewertungen',
                'checkout': 'Checkout',
                'password_reset': 'Passwort vergessen',
                'wishlist': 'Wunschzettel',
                'login': 'Login'
            },
            availableMethods: [
                { key: 'honeypot', label: 'Honeypot' },
                { key: 'timing', label: 'Timing' },
                { key: 'altcha', label: 'ALTCHA' },
                { key: 'ai_filter', label: 'Smart-Filter' },
                { key: 'turnstile', label: 'Turnstile' },
                { key: 'recaptcha', label: 'reCAPTCHA' },
                { key: 'friendly_captcha', label: 'Friendly' },
                { key: 'hcaptcha', label: 'hCaptcha' }
            ],

            init: function() {
                this.loadForms();
            },

            loadForms: function() {
                var self = this;
                bbfAdmin.post('getFormConfigs').then(function(resp) {
                    if (resp.success && resp.data) {
                        self.forms = resp.data.map(function(f) {
                            f.methods = typeof f.methods === 'string' ? JSON.parse(f.methods) : f.methods;
                            f.is_active = parseInt(f.is_active);
                            f.score_threshold = parseInt(f.score_threshold);
                            return f;
                        });
                    }
                });
            },

            isMethodActive: function(form, method) {
                return Array.isArray(form.methods) && form.methods.indexOf(method) !== -1;
            },

            toggleMethod: function(form, method) {
                if (!Array.isArray(form.methods)) form.methods = [];
                var idx = form.methods.indexOf(method);
                if (idx === -1) {
                    form.methods.push(method);
                } else {
                    form.methods.splice(idx, 1);
                }
            },

            saveForm: function(form) {
                bbfAdmin.post('saveFormConfig', {
                    form_type: form.form_type,
                    methods: JSON.stringify(form.methods),
                    score_threshold: form.score_threshold,
                    action_on_spam: form.action_on_spam,
                    is_active: form.is_active
                }).then(function(resp) {
                    if (resp.success) {
                        bbfAdmin.showNotification('Gespeichert', 'success');
                    } else {
                        bbfAdmin.showNotification(resp.message || 'Fehler', 'error');
                    }
                });
            }
        };
    });
}
{/literal}
</script>
