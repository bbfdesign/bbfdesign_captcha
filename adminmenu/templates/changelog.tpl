<h2 class="bbf-page-title">Changelog</h2>

<div class="bbf-card">
    <div style="color: var(--bbf-body-text); line-height: 1.7;">
        <h3 style="color: var(--bbf-primary); font-size: 18px; margin-bottom: 8px;">Version 1.0.63</h3>
        <p style="color: var(--bbf-muted); font-size: 13px; margin-bottom: 12px;">17.08.2026 &mdash; Backend-v2-Qualit&auml;tsrunde</p>
        <ul style="padding-left: 20px; margin-bottom: 24px;">
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">UI</span> Einstellungszeilen sind lesbar, Help-Texte normal gesetzt und Kartenbreiten sinnvoll begrenzt.</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">UI</span> Formularschutz-Tabelle mit festen Spalten, kompakteren Methoden-Chips und mobilen Labels.</li>
            <li><span class="bbf-badge bbf-badge-info" style="margin-right: 6px;">UX</span> Topbar zeigt App-Kontext statt doppelter Seiten&uuml;berschrift.</li>
        </ul>

        <h3 style="color: var(--bbf-primary); font-size: 18px; margin-bottom: 8px;">Version 1.0.62</h3>
        <p style="color: var(--bbf-muted); font-size: 13px; margin-bottom: 12px;">17.08.2026 &mdash; Produktionsh&auml;rtung &amp; BBF-v2-Backend</p>
        <ul style="padding-left: 20px; margin-bottom: 24px;">
            <li><span class="bbf-badge bbf-badge-warning" style="margin-right: 6px;">Security</span> Admin-Seiten liefern Secrets nicht mehr in Browser-JSON aus; Secret-Felder sind write-only mit Status-Flags.</li>
            <li><span class="bbf-badge bbf-badge-warning" style="margin-right: 6px;">Security</span> Custom CSS wird serverseitig widget-gescopet und per Allowlist bereinigt.</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">UI</span> Backend-Shell an BBF-v2-Prototyp angepasst: dunkle Sidebar, CI-Toplinie, Topbar, kompakte Controls und mobile Tabellenkarten.</li>
            <li><span class="bbf-badge bbf-badge-info" style="margin-right: 6px;">QA</span> Entwicklungssteuerung erweitert: Admin-Secret-Redaction-Gate und dokumentierte Codex-Rollenrunde.</li>
        </ul>

        <h3 style="color: var(--bbf-primary); font-size: 18px; margin-bottom: 8px;">Version 1.0.54</h3>
        <p style="color: var(--bbf-muted); font-size: 13px; margin-bottom: 12px;">21.06.2026 &mdash; Hotfix Suche</p>
        <ul style="padding-left: 20px; margin-bottom: 24px;">
            <li><span class="bbf-badge bbf-badge-warning" style="margin-right: 6px;">Fix</span> Honeypot/Timing werden nicht mehr in Such-, Navigations- und GET-Formulare injiziert &ndash; die (Live-/Flexmen&uuml;-)Suche funktioniert wieder bei aktivem Plugin. Gesch&uuml;tzte Formulare (Kontakt, Registrierung, Bewertung) bleiben unver&auml;ndert gesch&uuml;tzt.</li>
        </ul>

        <h3 style="color: var(--bbf-primary); font-size: 18px; margin-bottom: 8px;">Version 1.0.0</h3>
        <p style="color: var(--bbf-muted); font-size: 13px; margin-bottom: 12px;">24.03.2026 &mdash; Initiales Release</p>
        <ul style="padding-left: 20px; margin-bottom: 24px;">
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Honeypot-Schutz mit dynamischen Feldnamen</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Timing-basierter Schutz (HMAC-signiert)</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> ALTCHA Proof-of-Work Integration (self-hosted)</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Cloudflare Turnstile Integration</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Google reCAPTCHA v2/v3 Integration</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Friendly Captcha Integration</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> hCaptcha Integration</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> KI-basierter Spamfilter mit Punktesystem</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> IP-Blacklist/Whitelist mit CIDR-Support</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Rate Limiting (Sliding Window)</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Bot-Erkennung via User-Agent</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> JTL Consent Manager Integration</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> REST-API f&uuml;r externe Systeme</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> PHP-API f&uuml;r JTL-Plugins</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Dashboard mit Echtzeit-Statistiken</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Spam-Log mit Filtern und CSV-Export</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Disposable-Email-Erkennung</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> WCAG 2.2 AA Barrierefreiheit</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Dark Mode Support</li>
            <li><span class="bbf-badge bbf-badge-success" style="margin-right: 6px;">Neu</span> Responsive Design (320px+)</li>
        </ul>
    </div>
</div>
