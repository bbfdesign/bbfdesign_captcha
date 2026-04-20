<h2 class="bbf-page-title">Dokumentation</h2>

<div class="bbf-card">
    <h3 class="bbf-card-title">Installation &amp; Erste Schritte</h3>
    <div style="color: var(--bbf-body-text); line-height: 1.7; margin-top: var(--bbf-spacing-md);">
        <ol style="padding-left: 20px;">
            <li>Plugin &uuml;ber den JTL-Shop Plugin-Manager installieren</li>
            <li>Nach der Installation werden automatisch die Standard-Schutzmethoden aktiviert:
                <strong>Honeypot</strong>, <strong>Timing</strong>, <strong>ALTCHA</strong> und <strong>Smart-Spamfilter</strong></li>
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
                    <th scope="col">Methode</th>
                    <th scope="col">DSGVO</th>
                    <th scope="col">Consent</th>
                    <th scope="col">Beschreibung</th>
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
                    <td><strong>Smart-Filter</strong></td>
                    <td><span class="bbf-badge bbf-badge-success">Konform</span></td>
                    <td>Nein</td>
                    <td>Regelbasierte Textanalyse mit Punktesystem</td>
                </tr>
                <tr>
                    <td><strong>LLM-Prüfung</strong> <span class="bbf-badge">optional</span></td>
                    <td><span class="bbf-badge bbf-badge-warning">Ollama: konform / Cloud: extern</span></td>
                    <td>Ollama: Nein / Cloud-LLMs: ja</td>
                    <td>Zweitprüfung durch ein echtes LLM (Ollama lokal, oder OpenAI/Claude/Gemini)</td>
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
            <li>Smart-Filter &rarr; Kein Consent n&ouml;tig</li>
        </ol>
        <p><strong>Es ist immer ein Schutz aktiv, auch ohne Consent!</strong></p>
        <p style="margin-top:12px;font-size:13px;color:var(--bbf-text-light);">
            Hinweis: Die optionale <strong>LLM-Pr&uuml;fung</strong> (Ollama/OpenAI/Claude/Gemini) ist kein Captcha und
            ersetzt keine der obigen Methoden. Sie kann als Zweitpr&uuml;fung f&uuml;r Textinhalte aktiviert werden.
            Bei Cloud-Anbietern (OpenAI/Claude/Gemini) werden Formulardaten an den jeweiligen Dienst &uuml;bertragen &mdash;
            Consent / DSGVO-Hinweis ist dann Aufgabe des Shopbetreibers.
        </p>
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
        Wir empfehlen die Kombination aus <strong>ALTCHA + Honeypot + Timing + Smart-Filter</strong>. Diese Kombination ist vollst&auml;ndig DSGVO-konform und ben&ouml;tigt keinen Consent.</p>

        <p><strong>Was passiert bei einer Spam-Welle?</strong><br>
        Das System erh&ouml;ht automatisch den ALTCHA-Schwierigkeitsgrad und blockiert auff&auml;llige IPs. Sie k&ouml;nnen unter Einstellungen eine E-Mail-Benachrichtigung konfigurieren.</p>

        <p><strong>Wie teste ich den Spam-Filter?</strong><br>
        Unter <strong>Smart-Spamfilter &rarr; Test</strong> k&ouml;nnen Sie Texte eingeben und den Score live berechnen lassen.
        Unter <strong>LLM-Pr&uuml;fung</strong> k&ouml;nnen Sie zus&auml;tzlich ein echtes LLM einbinden und Texte dadurch klassifizieren lassen.</p>
    </div>
</div>
