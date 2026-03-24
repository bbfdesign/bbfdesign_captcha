<h2 class="bbf-page-title">Dokumentation</h2>

<div class="bbf-card">
    <h3 class="bbf-card-title">Installation &amp; Erste Schritte</h3>
    <div style="color: var(--bbf-body-text); line-height: 1.7; margin-top: var(--bbf-spacing-md);">
        <ol style="padding-left: 20px;">
            <li>Plugin &uuml;ber den JTL-Shop Plugin-Manager installieren</li>
            <li>Nach der Installation werden automatisch die Standard-Schutzmethoden aktiviert:
                <strong>Honeypot</strong>, <strong>Timing</strong>, <strong>ALTCHA</strong> und <strong>KI-Spamfilter</strong></li>
            <li>Unter <strong>Schutzmethoden</strong> k&ouml;nnen weitere Methoden aktiviert werden</li>
            <li>Unter <strong>Formulare</strong> l&auml;sst sich pro Formular konfigurieren, welche Methoden aktiv sind</li>
            <li>Das <strong>Dashboard</strong> zeigt Ihnen Statistiken &uuml;ber geblockte Spam-Versuche</li>
        </ol>
    </div>
</div>

<div class="bbf-card">
    <h3 class="bbf-card-title">Schutzmethoden im &Uuml;berblick</h3>
    <div style="color: var(--bbf-body-text); line-height: 1.7; margin-top: var(--bbf-spacing-md);">
        <table class="bbf-table">
            <thead>
                <tr>
                    <th>Methode</th>
                    <th>DSGVO</th>
                    <th>Consent</th>
                    <th>Beschreibung</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Honeypot</strong></td>
                    <td><span class="bbf-badge bbf-badge-success">Konform</span></td>
                    <td>Nein</td>
                    <td>Unsichtbare Felder, die nur Bots ausf&uuml;llen</td>
                </tr>
                <tr>
                    <td><strong>Timing</strong></td>
                    <td><span class="bbf-badge bbf-badge-success">Konform</span></td>
                    <td>Nein</td>
                    <td>Misst die Zeit zwischen Laden und Absenden</td>
                </tr>
                <tr>
                    <td><strong>ALTCHA</strong></td>
                    <td><span class="bbf-badge bbf-badge-success">Konform</span></td>
                    <td>Nein</td>
                    <td>Self-hosted Proof-of-Work Challenge</td>
                </tr>
                <tr>
                    <td><strong>KI-Filter</strong></td>
                    <td><span class="bbf-badge bbf-badge-success">Konform</span></td>
                    <td>Nein</td>
                    <td>Lokale Textanalyse mit Punktesystem</td>
                </tr>
                <tr>
                    <td><strong>Turnstile</strong></td>
                    <td><span class="bbf-badge bbf-badge-warning">Extern</span></td>
                    <td>Ja</td>
                    <td>Cloudflare Turnstile (kostenlos, privacy-friendly)</td>
                </tr>
                <tr>
                    <td><strong>reCAPTCHA</strong></td>
                    <td><span class="bbf-badge bbf-badge-danger">Google</span></td>
                    <td>Zwingend</td>
                    <td>Google reCAPTCHA v2/v3</td>
                </tr>
                <tr>
                    <td><strong>Friendly Captcha</strong></td>
                    <td><span class="bbf-badge bbf-badge-success">EU</span></td>
                    <td>Empfohlen</td>
                    <td>Europ&auml;ischer Anbieter, PoW-basiert</td>
                </tr>
                <tr>
                    <td><strong>hCaptcha</strong></td>
                    <td><span class="bbf-badge bbf-badge-warning">Extern</span></td>
                    <td>Ja</td>
                    <td>Privacy-fokussierte Alternative</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="bbf-card">
    <h3 class="bbf-card-title">Consent Manager Integration</h3>
    <div style="color: var(--bbf-body-text); line-height: 1.7; margin-top: var(--bbf-spacing-md);">
        <p>Externe Captcha-Dienste (reCAPTCHA, Turnstile, hCaptcha) laden JavaScript von fremden Servern und ben&ouml;tigen daher Consent.</p>
        <p><strong>Fallback-Kaskade:</strong> Wenn kein Consent erteilt wurde, f&auml;llt das System automatisch auf consent-freie Methoden zur&uuml;ck:</p>
        <ol style="padding-left: 20px;">
            <li>ALTCHA (self-hosted) &rarr; Kein Consent n&ouml;tig</li>
            <li>Honeypot + Timing &rarr; Kein Consent n&ouml;tig</li>
            <li>KI-Filter &rarr; Kein Consent n&ouml;tig</li>
        </ol>
        <p><strong>Es ist immer ein Schutz aktiv, auch ohne Consent!</strong></p>
    </div>
</div>

<div class="bbf-card">
    <h3 class="bbf-card-title">API-Nutzung</h3>
    <div style="color: var(--bbf-body-text); line-height: 1.7; margin-top: var(--bbf-spacing-md);">
        <p>Erstellen Sie unter <strong>API &rarr; API-Keys</strong> einen neuen Schl&uuml;ssel. Die vollst&auml;ndige API-Dokumentation mit allen Endpunkten finden Sie ebenfalls auf der API-Seite.</p>
    </div>
</div>

<div class="bbf-card">
    <h3 class="bbf-card-title">FAQ</h3>
    <div style="color: var(--bbf-body-text); line-height: 1.7; margin-top: var(--bbf-spacing-md);">
        <p><strong>Welche Methode soll ich verwenden?</strong><br>
        Wir empfehlen die Kombination aus <strong>ALTCHA + Honeypot + Timing + KI-Filter</strong>. Diese Kombination ist vollst&auml;ndig DSGVO-konform und ben&ouml;tigt keinen Consent.</p>

        <p><strong>Was passiert bei einer Spam-Welle?</strong><br>
        Das System erh&ouml;ht automatisch den ALTCHA-Schwierigkeitsgrad und blockiert auff&auml;llige IPs. Sie k&ouml;nnen unter Einstellungen eine E-Mail-Benachrichtigung konfigurieren.</p>

        <p><strong>Wie teste ich den Spam-Filter?</strong><br>
        Unter <strong>KI-Spamfilter &rarr; Test</strong> k&ouml;nnen Sie Texte eingeben und den Score live berechnen lassen.</p>
    </div>
</div>
