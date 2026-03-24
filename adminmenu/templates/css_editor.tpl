<h2 class="bbf-page-title">Custom CSS</h2>

<div {literal}x-data="bbfCssEditor()"{/literal}>
    <div class="bbf-card">
        <p style="color: var(--bbf-muted); margin-bottom: var(--bbf-spacing-md);">
            Eigenes CSS f&uuml;r die Frontend-Captcha-Widgets. &Auml;nderungen werden sofort wirksam.
        </p>

        <textarea class="bbf-input bbf-textarea" style="min-height: 400px; font-family: monospace; font-size: 13px; line-height: 1.5;"
                  placeholder="/* Custom CSS hier eingeben */
.bbf-captcha-widget {
    /* Ihre Styles */
}"
                  {literal}x-model="customCss"{/literal}></textarea>

        <div style="margin-top: var(--bbf-spacing-md); display: flex; gap: 8px;">
            <button type="button" class="bbf-btn bbf-btn-primary" {literal}@click="saveCss()"{/literal}>Speichern</button>
            <button type="button" class="bbf-btn bbf-btn-secondary" {literal}@click="customCss = ''; saveCss()"{/literal}>Zur&uuml;cksetzen</button>
        </div>
    </div>
</div>

<script>
var bbfServerSettings = {$settingsJson nofilter};
</script>
<script>
{literal}
if (typeof Alpine !== 'undefined' && Alpine.data) {
    Alpine.data('bbfCssEditor', function() {
        var sv = window.bbfServerSettings || {};
        return {
            customCss: sv.custom_css || '',

            saveCss: function() {
                bbfAdmin.post('saveSetting', { key: 'custom_css', value: this.customCss, group: 'css' }).then(function(resp) {
                    bbfAdmin.showNotification(resp.success ? 'CSS gespeichert' : 'Fehler', resp.success ? 'success' : 'error');
                });
            }
        };
    });
}
{/literal}
</script>
