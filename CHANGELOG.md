# Changelog

Alle nennenswerten Änderungen an BBF Captcha. Format an [Keep a Changelog]
angelehnt; Versionierung nach SemVer (Pflicht-Gate der Entwicklungssteuerung).

## 1.0.48 – 2026-06-21

### Neu: Anbindung an CaptchaCockpit (zentrale Erkennung) – Telemetrie, Default AUS

- **`CockpitTelemetryService`**: meldet Spam-Log-Ereignisse DSGVO-minimiert an das
  zentrale CaptchaCockpit (`captchacockpit.bbfdesign.de`), damit Bot-Wellen
  shopübergreifend erkannt und zentral bekämpft werden können – ohne dass jedes
  Plugin einzeln aktualisiert werden muss. Übermittelt werden **ausschließlich
  pseudonyme/aggregierte Merkmale**: `ipHash` (HMAC, keine Klar-IP),
  `contentFp`/`contentShape` (Hash + abstrakte Merkmale, kein Klartext), nur die
  E-Mail-**Domain**, Score, Aktion, Erkennungsmethode. Batches HMAC-signiert,
  gedrosselt über den Boot-/Cron-Pfad, **fail-open** (Cursor rückt nur bei Erfolg;
  Cockpit-Ausfall schwächt den Schutz nicht).
- **Standard AUS**: greift nur mit gesetztem `cockpit_enabled` + Endpoint + Secret
  (Aktivierung nur mit AVV). Neue Backend-Karte „Zentrale Erkennung (CaptchaCockpit)"
  unter Einstellungen (Toggle, Endpoint, write-only Secret, optionales anonymes
  IP-Präfix). `cockpit_secret`/`cockpit_pepper` werden aus den Frontend-Settings
  gefiltert (kein Leak).
- Vorbereitung für Inkrement 2 (`RemoteRulesetService`): zentrale Regeln/Blocklisten
  werden künftig gezogen und ohne Plugin-Update angewandt. Doku: `docs/COCKPIT-INTEGRATION.md`.

## 1.0.47 – 2026-06-19

### Behoben (Spam-Welle mit Zufalls-Tokens kam durch – Kontakt, Widerruf, Registrierung)

- **Rein zufällige Buchstaben-Tokens in Misch-Schreibweise werden jetzt erkannt.**
  Eine Bot-Welle nutzte Namen/Texte wie „qYQbYkHwdpEmyqjXugxIBCuP",
  „bFEDhpHiIcwDgVJHwzifB", „VfIGYAckGGlRfDcbqFerG" – ohne Ziffern, ohne Domain,
  ohne bekannte Spam-Wörter, daher von allen bisherigen Checks unentdeckt
  (Registrierungen, Kontakt- und Widerruf-Mails kamen durch). Neuer Check
  `checkRandomGibberish` (+60): erkennt lange Einzel-Tokens (≥ 12 Buchstaben) mit
  vielen internen Groß-/Kleinwechseln (≥ 4) UND hohem Großbuchstaben-Anteil
  (≥ 40 %). Verifiziert: alle Tokens der Welle → geblockt; echte Namen und
  CamelCase-Komposita (Müller, Anne-Marie, McDonald, PayPalZahlung,
  PayPalSofortzahlung, GeForceRTXGrafik, DHLPaketVersand, WIDERRUFSBELEHRUNG)
  lösen NICHT aus (Bot-Tokens haben 42–57 % Großbuchstaben, echte Komposita nur
  ~15–25 %). Greift formularübergreifend (Registrierung, Kontakt, Widerruf …).

## 1.0.46 – 2026-06-17

### Behoben
- **Live-500 / fail-closed im Router-Hook:** `parse_url($uri, PHP_URL_PATH)`
  liefert bei kaputten Request-URIs `false`; das `?? $requestUri` fing nur `null`
  ab → `$requestPath` wurde `bool` → `str_starts_with()` warf einen `TypeError`
  (Uncaught, 500) im `HOOK_ROUTER_PRE_DISPATCH` — also auf JEDEM Request mit
  ungültiger URI (Bot-/Scanner-Traffic). Jetzt wird `$requestPath` hart auf einen
  String gezwungen → Hook bleibt fail-open, keine 500 mehr.

## 1.0.45 – 2026-06-16

### Neu: Widerrufsformular-Schutz (JTL 5.7) + native JTL-Captcha-Integration

- **Widerrufsformular (neu in 5.7) wird jetzt geschützt.** Der `WithdrawalController`
  versendet eine Mail (`MAILTEMPLATE_WIDERRUFSFORMULAR`) und war damit spambar,
  hat aber keinen eigenen Plausi-Hook (`HOOK_SEITE_PAGE` feuert erst nach dem
  Versand). Neuer Listener an `HOOK_ROUTER_PRE_DISPATCH` (feuert vor dem
  Controller): erkennt die Widerruf-POST, fährt den Schutz (Honeypot/Timing/
  Smart-Filter; ALTCHA fail-open) und setzt bei Spam JTLs eigenen Honeypot
  (`jtl_hp_input`) → der Versand wird sauber übersprungen. Neuer Formtyp
  `withdrawal` (Default-Methoden + Textfeld-Mapping `withdrawal_name/_comment/_order`).
  Fail-open: ein echter Widerruf wird nie blockiert.
- **Native JTL-Captcha-Integration (optional, Standard AUS).** Neuer
  `JtlCaptchaAdapter` (implementiert `CaptchaServiceInterface`); ist das Setting
  `native_captcha_integration` aktiv, bindet das Bootstrap BBF Captcha als
  shopweiten Captcha-Dienst in den Container. Damit greift BBF überall, wo JTL
  (oder ein Fremd-Plugin) `Form::validateCaptcha()` nutzt und die jeweilige
  Captcha-Abfrage im Shop aktiv ist (Kontakt, Widerruf, Registrierung,
  Passwort …) – ein zentraler Punkt statt vieler Einzel-Hooks. Fail-open: der
  Adapter blockt nie allein und liefert bei jedem Fehler „gültig"; ein
  Bind-Fehler lässt JTLs Standard-Captcha unangetastet. Neuer Backend-Toggle
  unter Einstellungen → „Formular-Abdeckung".

## 1.0.44 – 2026-06-16

### Behoben (Frischinstallation: „Verbindungsfehler (500)" im Backend-Dashboard)

- Bei einer **Frischinstallation** warf das Backend-Dashboard `Verbindungsfehler
  (500)`: der erste Admin-AJAX-Call lief, **bevor die Migration die Tabelle
  `bbf_captcha_spam_log` angelegt** hatte → die KPI-/Chart-Queries warfen eine
  SQL-Exception → HTTP 500. Nach Abschluss der Migration verschwand der Fehler
  von selbst, beim Ersteinstieg sah man ihn aber. `getDashboardData()` ist jetzt
  **fail-safe**: schlägt ein DB-Zugriff fehl (Tabelle fehlt o. ä.), wird ein
  leeres, vollständig strukturiertes Dashboard (alle KPIs 0) ausgeliefert statt
  eines 500; die Ursache landet im Shop-Log. Die Zählung der aktiven Methoden
  ist settings-basiert und bleibt auch im Fallback korrekt.

## 1.0.43 – 2026-06-16

### Verbessert (LLM-Zweitprüfung erkennt jetzt auch Spam-Namen)

- Die optionale LLM-Zweitprüfung bekommt jetzt denselben kombinierten Text wie
  der regelbasierte Filter (**Nachricht + Name**), nicht nur den Nachrichtentext.
  Damit erkennt sie auch Gibberish-/Spam-Namen (vgl. Kontakt-Spam #1), bei denen
  das eigentliche Signal im Absendernamen steckt. Unverändert fail-open: das LLM
  blockt nie allein (nur mit heuristischer Korroboration ≥ „verdächtig"), und ein
  LLM-„kein Spam" hebt einen Borderline-Block auf — echte Kunden bleiben sicher.

## 1.0.42 – 2026-06-16

### Behoben (Kontakt-Spam: kohärenter B2B-Akquise-Pitch kam durch)

- **B2B-Kaltakquise/Outsourcing-Spam erkannt.** Kohärente, gut formulierte
  (meist englische) Verkaufs-Pitches („We provide dedicated remote support
  teams … engagement models starting from $9/hr … schedule a short meeting to
  explore possible collaboration … Reply us on …") umgingen alle bisherigen
  Heuristiken: kein Gibberish, keine Großschrift, normale Länge, keine
  Free-Hoster-Domain. Neuer Check `checkSolicitation` zählt sehr spezifische
  Akquise-Marker (remote support/team, our services include, we provide/offer,
  engagement model, $X/hr, schedule a meeting, marketplace management, data
  entry, bookkeeping, shopify/woocommerce/magento, reply us on, grow your
  sales, seo service, outsourc …). Ab **3 unterschiedlichen Markern → +60**
  (sperrt), 2 → +25. Verifiziert: der echte Pitch trifft 17 Marker → +60
  (zzgl. +25 für die im Text genannte Domain); legitime Anfragen lösen NICHT
  aus (DE-Weinkauf, DE-Gastro-B2B-Anfrage, EN-Tourist-Versandfrage = je 0).

## 1.0.41 – 2026-06-15

### Behoben (KRITISCH: Kontaktformular-Spam kam immer durch)

- **Kontakt-Sperre griff zu spät.** Der JTL-`ContactController` versendet die
  Kontakt-Mail in `assignForms()` (`Form::editMessage()`), und erst DANACH feuert
  `HOOK_KONTAKT_PAGE` — der Hook, an dem das Plugin hing. Die Mail war also raus,
  bevor das Plugin überhaupt prüfte → Kontakt-Spam konnte NIE geblockt werden.
  Neuer Listener auf **`HOOK_KONTAKT_PAGE_PLAUSI`** (feuert VOR dem Versand):
  bei Spam wird JTLs eigener Kontakt-Honeypot `$_POST['jtl_hp_input']` gesetzt →
  `Form::honeypotWasFilledOut()` = true → JTL überspringt den Versand sauber
  (wie bei einem Bot), ohne 500/Exception. Fail-open: nur bei erkanntem Spam.
  `FormProtection` validiert Kontakt am alten Seiten-Hook nicht mehr (wirkungslos).
- **Bot-Token-Gibberish erkannt.** Namen/Nachrichten wie
  „NARETGR117051NERTYTRY" / „MERTYHR117051MARTHHDF" (GROSSBUCHSTABEN mit
  eingebetteten Ziffern) wurden vom Smart-Filter nicht erfasst (Score ~30).
  Neuer Check `checkGibberishTokens` (+45) für das Muster
  `[A-Z]{4,}\d{3,}[A-Z]{4,}`. Verifiziert: der echte Spam erreicht jetzt 60
  (Token +45, Caps +15) → geblockt; echte Eingaben (iPhone13Pro, COVID19,
  Bestellung 2026, Müller, Anne-Marie) lösen den Check NICHT aus.

## 1.0.40 – 2026-06-12

### Behoben (KRITISCH: Smart-Filter per Unicode-Evasion ausgehebelt → Spam-Konten)

- **Anti-Evasion / Unicode-Normalisierung.** Spammer zerstückeln Muster mit
  unsichtbaren Unicode-Zeichen (z. B. Word-Joiner U+2060 in
  „iuhgjklll⁠.blogspot⁠.lu"), sodass die Domain-/Phrasen-Regex des Smart-Filters
  **gar nicht mehr greifen** → Score 0 → durchgewunken (Live-Vorfall weinewald24,
  Spam-Konto angelegt). `AISpamService::analyze()` normalisiert Eingaben jetzt vor
  jeder Prüfung: NFKC + Entfernen aller unsichtbaren „Format"-Zeichen
  (`\p{Cf}`, Zero-Width/Joiner/Bidi/BOM/Soft-Hyphen). Isoliert verifiziert:
  derselbe Spam-Name ohne Norm = 0, mit Norm = 65 (≥ Schwelle 60) → geblockt;
  echte Namen (Hans-Peter, Maria José, J.Robert, Dr.Schmidt) bleiben < 60.
- **Free-Hoster stärker gewichtet:** Domains wie blogspot/weebly/tumblr/wixsite/
  wordpress.com/sites.google sind in Shop-Formularen praktisch immer Spam →
  Penalty von +15 auf +40 (Cap 75), damit eine solche Domain allein die Schwelle
  erreicht.

## 1.0.39 – 2026-06-12

### Behoben (KRITISCH: Lizenz schaltete den Spam-Schutz ab → Spam-Konten)

- **Der Lizenzstatus deaktiviert den Spam-Schutz NICHT mehr.** Das in 1.0.31
  eingeführte Fail-closed-Enforcement schaltete bei einem harten Verdikt
  (z. B. `domain_mismatch`, wenn die Shop-Domain nicht in den `allowedDomains`
  der Lizenz steht) den GESAMTEN Schutz ab — inkl. Registrierungs-, Login- und
  Formular-Sperre. Folge: der Shop wurde mit Spam-Konten geflutet
  (Live-Vorfall weinewald24). Das ist für ein Anti-Spam-Plugin grundfalsch:
  eine Lizenzsache darf den Betreiber nie mit Spam bestrafen.
- `protectionActive()` prüft jetzt NUR noch den globalen Schalter. Lizenzprobleme
  werden ausschließlich im Backend als Hinweis angezeigt (informativ), niemals
  durch Schutz-Abschaltung erzwungen. Damit ist der Schutz wieder unabhängig vom
  Lizenzstatus aktiv.

## 1.0.38 – 2026-06-12

### Behoben (ALTCHA löst jetzt wirklich automatisch)

- **`auto="onfocus"` statt `onload`.** Das altcha-Script lädt als ES-Modul
  (deferred) und wertet das server-injizierte `<altcha-widget>` erst nach dem
  `window.load`-Event auf — ein `onload`-Auto-Trigger verpasst das Event und
  löste nie (im Test: Widget registriert, aber `bbf_altcha` blieb leer; manuelles
  `verify()` lieferte sofort eine 324-Zeichen-Lösung). `onfocus` hängt am
  Formular-Fokus und feuert immer nach dem Upgrade → bis zum Absenden ist die
  Lösung da.
- **Konsolen-Fehler behoben:** `bbfdesign-captcha.js` rief `ConsentManager.hasConsent()`
  auf, auch wenn das Shop-Consent-Objekt diese Methode nicht hat (TypeError, der
  die Init abbrach). Jetzt zusätzlich auf `typeof … === 'function'` geprüft.
- **Frontend-Asset-Cache-Bust** (`?v={Pluginversion}`) für CSS/JS, damit Updates
  ohne Hard-Reload ankommen.

## 1.0.37 – 2026-06-12

### Hinzugefügt (theme-unabhängige ALTCHA-Widget-Platzierung)

- **Das `<altcha-widget>` wird jetzt automatisch ins passende Formular
  injiziert** – unabhängig davon, ob das Theme den Slot `{$bbfCaptchaWidget}`
  rendert (NOVA-Child u. a. tun das nicht). Damit greift der ALTCHA-Schutz
  endlich auch dort, wo das Widget bisher fehlte.
- **Sicher per Design — Checkout wird nie getroffen:** Injektion nur für
  Formulartypen mit ALTCHA als aktiver Methode (Registrierung/Kontakt/Bewertung)
  UND nur in das Formular mit passendem Signatur-Feld (`pass2`/`nachricht`/
  `sterne`). Checkout, Login, Warenkorb und Suche haben weder ein aktives ALTCHA
  noch ein passendes Signatur-Feld → werden nie verändert (Ticketverkauf bleibt
  unberührt). Idempotent (kein Doppel-Widget, falls ein Theme den Slot doch rendert).
- Greift in beiden Output-Pfaden (JTL 5.6/5.7 phpQuery-Dokument **und**
  String-Fallback); Honeypot/Timing-Injektion unverändert.

## 1.0.36 – 2026-06-12

### Behoben (ALTCHA-Script `type=module` an der richtigen Stelle)

- Der tatsächlich ausgelieferte ALTCHA-Script-Tag stammt aus `IncludeAssets`
  (nicht aus dem ungenutzten `AltchaService::getWidgetScriptTag`). Dort wird
  `altcha.min.js` jetzt mit `type="module"` eingebunden, damit sich das
  Custom-Element registriert (1.0.35 hatte die Änderung nur in der ungenutzten
  Methode → ohne Wirkung).

### Wichtige Erkenntnis (ALTCHA hat nie funktioniert)

- Auf **keinem** Theme (auch nicht im Standard-Theme von weinewald24) wird das
  `<altcha-widget>`-Element ins Formular gerendert — der Slot `{$bbfCaptchaWidget}`
  existiert in den Theme-Templates nicht. ALTCHA war damit **auf allen Shops
  faktisch inaktiv**; „ALTCHA-Lösung fehlt" kam IMMER. Erst der Fail-open-Fix
  (1.0.33) verhindert, dass das echte Kunden blockt. Das tatsächliche Aktivieren
  von ALTCHA erfordert die **theme-unabhängige Injektion des Widgets in die
  richtigen Formulare** (Login/Registrierung/Kontakt — **ohne Checkout!**) und
  wird als getesteter Folgeschritt umgesetzt.

## 1.0.35 – 2026-06-12

### Behoben (ALTCHA-Script als ES-Modul laden)

- **`altcha.min.js` wird jetzt mit `type="module"` eingebunden.** Die Datei ist
  ein ES-Modul (registriert `customElements.define('altcha-widget')`); als
  klassisches `<script>` wurde die Modul-Syntax nicht ausgeführt → das
  Custom-Element registrierte nie → das ALTCHA-Widget blieb tot. Mit `type=module`
  registriert sich das Element; in Verbindung mit `auto="onload"` (1.0.34) löst
  das Widget dann automatisch.

### Offen (separat, mit Test)

- **Theme-unabhängige Widget-Platzierung:** Auf Themes, die den Slot
  `{$bbfCaptchaWidget}` NICHT rendern (z. B. NOVA-Child auf Bikepark), erscheint
  das `<altcha-widget>` gar nicht im Formular → ALTCHA kann dort (noch) nicht
  greifen (Schutz bleibt durch Honeypot/Timing/Smart-Filter; Kunden werden dank
  Fail-open NICHT geblockt). Die automatische Injektion in die *richtigen*
  Formulare (Login/Registrierung/Kontakt) ist bewusst als getesteter Folgeschritt
  ausgelagert (Frontend-Injektion darf Formular-Rendering nicht brechen).

## 1.0.34 – 2026-06-12

### Behoben (Schutz wiederhergestellt – ALTCHA löste nie automatisch)

- **ALTCHA-Widget bekam `auto="onload"`.** Bisher fehlte das Attribut → das
  Widget rechnete den Proof-of-Work NICHT von selbst, der Nutzer hätte eine
  Checkbox anklicken müssen. Reale Kunden sendeten daher keine Lösung
  („ALTCHA-Lösung fehlt") – das war (zusammen mit der JS-Challenge) die Wurzel
  des Aussperr-Vorfalls. Jetzt löst ALTCHA automatisch beim Laden; echte Kunden
  haben die Lösung beim Absenden dabei. (Keine Expiry-Prüfung im Code, nur HMAC →
  `onload` ist sicher gegen veraltete Lösungen.)

### Bekannt/Hinweis

- **JS-Challenge (`bot_js_challenge`) ist totes Feature:** `generateJsChallenge()`
  wird nirgends injiziert, `validateJsChallenge()` prüft also ein Token, das nie
  erzeugt wird → „JS-Challenge nicht gelöst" für jeden. Seit 1.0.33 fail-open
  (Score 0, harmlos). Empfehlung: „JS-Challenge" in den Schutzmethoden
  deaktivieren (oder Injektion separat nachrüsten). ALTCHA-PoW ist der stärkere,
  jetzt funktionierende JS-Schutz.

## 1.0.33 – 2026-06-12

### Behoben (KRITISCH – echte Kunden konnten kein Konto anlegen)

- **Fehlende ALTCHA-/JS-Lösung blockierte legitime Registrierungen.** Wenn das
  ALTCHA-Widget bzw. die JS-Challenge im Browser nicht lief (z. B. weil ein Theme
  das Widget nicht rendert), kassierte die Anmeldung **ALTCHA-fehlt (+60)** und
  **JS-Challenge-fehlt (+50)** = 110 ≥ Schwelle 60 → **echte Kunden wurden
  ausgesperrt** (Live-Vorfall auf bikepark-winterberg.de, mehrere reale Kunden).
- Beide **„fehlt"-Fälle sind jetzt FAIL-OPEN** (Score 0): eine *fehlende*
  Client-Lösung ist kein verlässliches Bot-Signal und darf legitime Nutzer nie
  blockieren (oberste Regel + dokumentierte Fail-open-Grundhaltung). Eine
  *präsente, aber ungültige/gefälschte* ALTCHA-Lösung bleibt ein Spam-Signal
  (Score 80). Bots werden weiterhin über Honeypot, Timing und Smart-Spamfilter
  erkannt.
- Hinweis: Warum das ALTCHA-Widget auf dem Bikepark-Theme nicht rendert, wird
  separat untersucht (Wiederherstellung des vollen ALTCHA-Schutzes). Die
  Kundensicherheit ist mit diesem Fix sofort wiederhergestellt.

## 1.0.32 – 2026-06-12

### Geändert (Lizenz)

- Produkt-Slug `bbfcaptcha` als Default hinterlegt (kein Secret). Damit muss im
  Backend nur noch das Signing-Secret eingetragen werden; der Slug wird
  automatisch mitgeschickt (überschreibbar via Setting/Konstante).

## 1.0.31 – 2026-06-12

### Geändert (Lizenz-Enforcement nach ForgePush-Vorgabe)

- **Fail-closed bei klarem Negativ-Verdikt.** Wie von ForgePush vorgegeben
  deaktiviert ein hartes Lizenz-Verdikt (revoked/expired/suspended/
  domain_mismatch/instance_limit_exceeded) den Spam-Schutz: die Schutz-Hooks
  (Widget-/Honeypot-Injektion, Registrierungs-Block, Formular-Validierung)
  greifen dann nicht mehr. Der gecachte Verdikt wird im Hotpath nur als Setting
  gelesen (kein Netz-Call); der Cron prüft weiter, sodass sich der Schutz nach
  Behebung automatisch reaktiviert.
- **Bewusst NICHT betroffen (Fail-open):** „unkonfiguriert" (kein Secret) und
  transiente Fehler (Netz/Signatur, 24-h-Kulanz) lassen den Schutz aktiv – Shops
  ohne hinterlegte Lizenz werden also nicht abgeschaltet.
- Hinweis in der Lizenz-Sektion entsprechend angepasst (Schutz „deaktiviert"
  statt „bleibt aktiv").

## 1.0.30 – 2026-06-12

### Geändert (Lizenz-UI)

- Lizenz-Status zeigt bei fehlendem Signing-Secret „Nicht konfiguriert"
  (statt „Unbekannt") mit neutralem Badge.

## 1.0.29 – 2026-06-12

### Hinzugefügt (ForgePush-Lizenzsystem)

- **Signierte Lizenzprüfung gegen ForgePush.** Neuer `LicenseService` ruft
  `POST https://forgepush.bbfdesign.de/api/v1/licenses/check` (Auto-Licensing-by-
  Domain mit `instanceId` + `host`, optional `productSlug`/`licenseKey`) und
  verifiziert die Antwort per HMAC-SHA256 über „RAW-Body|X-Signed-At" plus
  Anti-Replay (±5 min). `instanceId` wird einmalig erzeugt und in den
  Plugin-Settings persistiert; `serverFingerprint` = SHA-256 über
  PHP-Version + Server-Software + Hostname.
- **Robust & live-sicher:** transiente Fehler (Netz/Signatur) → Fail-open über
  24-h-Cache; klares Negativ-Verdikt (revoked/expired/…) → Fail-closed. Wichtig:
  Eine ungültige Lizenz **deaktiviert den Spam-Schutz NICHT** – sie wird nur im
  Backend gemeldet (Enforcement = informativ).
- **Über den nativen Cron geprüft** (CleanupCron, alle 12 h gedrosselt) – kein
  Hotpath, kein Blocking beim Seitenaufruf.
- **Backend-Sektion „ForgePush-Lizenz"** in den Einstellungen: Status-Badge +
  Verdikt, „zuletzt geprüft", Host/Instance, Host-Wechsel-Hinweis (`pluginMoved`),
  sowie write-only Felder für Produkt-Slug, Signing-Secret und License-Key
  („Jetzt prüfen"-Button). **Secrets liegen nie im Repo und werden nie an den
  Browser ausgeliefert** (Konstante `FORGEPUSH_SIGNING_SECRET` bevorzugt, sonst
  Setting; aus dem an den Browser gesendeten `settingsJson` herausgefiltert).

### Geändert

- Einstellungs-Hilfetexte zu Auto-Cleanup / Cron-Bereinigung an den nativen
  JTL-Cron angepasst (vorher „über Shop-Traffic").

## 1.0.28 – 2026-06-12

### Behoben (Cron-Frequenz-Einheit)

- **`frequency` ist in JTL Stunden, nicht Sekunden.** Der Wert 900 wurde als
  900 Stunden interpretiert (nächster Lauf ~38 Tage in der Zukunft) – der Job
  wäre faktisch nie gelaufen. Frequenz jetzt auf **1 Stunde** gesetzt (Override:
  Setting `cron_frequency_hours`, 0 = bei jedem Cron-Lauf, Grenze [0, 168]).
  Der Installer setzt bei einer Frequenz-Korrektur zusätzlich `nextStart` auf
  jetzt zurück, damit eine bereits zu weit in die Zukunft geplante Ausführung
  sofort wieder greift. (Beide Wartungs-Teilaufgaben drosseln sich ohnehin
  selbst: Wellen-Alarm 1 h, Cleanup 24 h.)

## 1.0.27 – 2026-06-12

### Behoben (KRITISCH: /admin/cron 500 durch v1.0.26)

- **Cron-Admin-Seite lief auf 500.** Das in v1.0.26 ergänzte
  `GET_AVAILABLE_CRONJOBS`-Listener hängte ein Objekt an die Liste der
  verfügbaren Jobs. JTL 5.7 erwartet dort aber ein `string[]` (jobType-Strings):
  `cron.tpl` rendert jeden Eintrag direkt als `{$type}`, der Objekt-zu-String-Cast
  warf einen Fatal → HTTP 500 auf `/admin/cron`. Jetzt wird nur der
  jobType-String angehängt (wie vom JTL-Core erwartet). Der Job selbst läuft
  unverändert; `MAP_CRONJOB_TYPE` löst ihn weiterhin korrekt auf.

## 1.0.26 – 2026-06-12

### Geändert (Cron & Mail – nativ ins JTL-System integriert)

- **Nativer JTL-Cron statt Pseudo-Cron.** Wartung (Spam-Wellen-Alarm + Log-/
  Rate-Limit-/IP-Block-Bereinigung) läuft jetzt als echter JTL-Cron-Job
  (`bbf_captcha_maintenance`, Standard alle 15 min) über das Shop-Cron-System.
  Registrierung wie im Ticket-Plugin über `Event::MAP_CRONJOB_TYPE` /
  `GET_AVAILABLE_CRONJOBS`; der `tcron`-Eintrag wird bei Install/Update **und**
  idempotent beim Boot angelegt (Git-Deploys lösen keine Update-Routine aus) und
  bei Deinstallation wieder entfernt. Neue Klassen: `src/Cron/CleanupCron.php`,
  `src/Services/JtlCronInstallerService.php`, `src/Services/JtlCronBootstrapService.php`.
  Beide Teilaufgaben drosseln sich selbst (Welle 1 h, Cleanup 24 h).
- **Mailversand über das Shop-Mailsystem.** Die Spam-Wellen-Benachrichtigung
  geht jetzt über `\JTL\Mail\Mailer` (HTML-Mail, Absender aus der Shop-Konfig)
  statt über das nackte PHP-`mail()`. Damit greifen die im Shop konfigurierten
  Versandeinstellungen (SMTP etc.) korrekt.
- **Dashboard entlastet:** Cleanup und Wellen-Alarm laufen nicht mehr synchron
  beim Dashboard-Load (kein Mailversand mehr in einem Admin-AJAX-Request).
- **URL-Cron-Fallback** (`…/api/v1/cron?token=…`) ruft jetzt denselben Einstieg
  (`CleanupCron::run()`) wie der native Cron; der gedrosselte Traffic-Fallback im
  Boot bleibt für Shops ohne eingerichteten Cron erhalten.

## 1.0.25 – 2026-06-12

### Behoben (Dashboard-Statistik)

- **Deutsche Beschriftungen sofort sichtbar.** Die neuen KPI-/Karten-Labels
  (Erkannt gesamt, Protokolliert, Ø Spam-Score, Geblockte IPs, Bedrohungen,
  Aktivität nach Tageszeit) nutzen jetzt deutsche Fallback-Texte. Vorher standen
  sie bis zum nächsten Plugin-Locale-Import auf Englisch, weil neue
  Sprachvariablen erst beim JTL-Update in die DB importiert werden.

## 1.0.24 – 2026-06-12

### Hinzugefügt (Dashboard-Statistik, ALTCHA-Stil)

- **KPI-Strip im ALTCHA-Look:** 8 Kennzahlen mit farbigem Akzentbalken je Karte –
  Erkannt gesamt, Geblockt, Protokolliert, Heute geblockt, Erkennungsrate,
  Ø Spam-Score, Geblockte IPs, Aktive Methoden. Alles aus echten Log-Daten.
- **„Bedrohungen"-Liste:** auffälligste geblockte IPs im Zeitraum mit Trefferzahl
  und „zuletzt gesehen" als Relativzeit (z. B. „vor 18 Stunden").
- **Aktivität nach Tageszeit (0–23 Uhr):** neuer Balkenchart, zeigt wann Bots am
  aktivsten sind (`HOUR(created_at)`-Aggregation).
- **Range-Wähler (7/30/90) funktioniert jetzt wirklich:** alle zeitraumabhängigen
  Charts und die Bedrohungsliste werden bei Wechsel neu aufgebaut (vorher No-Op).
- Neue, günstige SQL-Aggregationen (Ø-Score, eindeutige IPs, Stundenverteilung,
  Aktions-Split, Top-IPs mit „zuletzt gesehen"). Keine Geo-/Netzwerk-Kacheln –
  dafür liegen keine echten Daten vor (kein GeoIP); nichts wird erfunden.

## 1.0.23 – 2026-06-12

### Behoben (Backend-UI)

- **Drawer-Einblendung lief nicht.** Die Off-Canvas-Animation hing im
  Startzustand fest (verschachteltes Alpine `x-show`+`x-transition` auf
  derselben Bedingung kam von `enter-start` nie zu `enter-end`). Die
  Ein-/Ausblendung läuft jetzt rein über eine CSS-Klasse (`.is-open`):
  Backdrop blendet ein, Panel slidet zuverlässig von rechts herein.

## 1.0.22 – 2026-06-12

### Behoben (Backend-UI)

- **CSS-Cache-Bust.** Die Admin-Stylesheets (`admin-base.css`, `admin.css`)
  werden jetzt mit `?v={Pluginversion}` eingebunden. Bisher luden bestehende
  Admin-Sitzungen nach einem Update das alte, browsergecachte CSS – neue Styles
  (z. B. der Off-Canvas-Drawer) erschienen erst nach manuellem Hard-Reload.
  Mit jedem Versions-Bump zieht der Browser nun automatisch frisches CSS.

## 1.0.21 – 2026-06-12

### Geändert (Backend-UI)

- **Spam-Log-Detail als Off-Canvas-Drawer.** Die „Eingereichte Daten"-Ansicht
  wird beim Klick auf „Details" nicht mehr unten an die Seite angehängt, sondern
  schwebt – wie im Ticket-Plugin – als seitliches Panel von rechts ein. Der
  Hintergrund wird ausgegraut und weichgezeichnet (Backdrop-Blur). Schließen per
  ✕-Button, Klick auf den Hintergrund oder Escape-Taste; Seiten-Scroll wird
  währenddessen gesperrt. Rein kosmetisch, kein Einfluss auf Erkennung/Schutz.

## 1.0.20 – 2026-06-11

### Behoben (KRITISCH: Spam-Registrierung wurde nicht geblockt)

Bot-Registrierungen (Krypto-/Domain-Spam im Namensfeld) kamen weiterhin durch –
das Konto wurde trotz „Spam" angelegt. Zwei zusammenwirkende Ursachen, beide gefixt:

- **Falscher Hook (Konto wurde trotzdem angelegt).** Das Plugin hing nur an
  `HOOK_REGISTRIEREN_PAGE` (40), der **vor** der Validierung feuert und die
  Konto-Erstellung nicht verhindern kann. JTL legt das Konto im Block
  `if ($nReturnValue)` an, und `$nReturnValue` wird **nur** bei
  `HOOK_REGISTRIEREN_PAGE_REGISTRIEREN_PLAUSI` (41) **per Referenz** übergeben.
  Neuer Listener auf Hook 41 setzt bei Spam `nReturnValue = false` → **JTL legt das
  Konto nicht mehr an**. Hook 40 stellt nur noch das Widget bereit (keine
  Doppel-Protokollierung).
- **Spam-Inhalt wurde nicht geprüft.** JTL liefert die Registrierungsdaten
  **verschachtelt** (`register[vorname]`), der Smart-Filter schaute aber nur flach
  und sah den Spam-Namen gar nicht (Score 0). `AISpamService` zieht die POST-Daten
  jetzt **rekursiv flach** zusammen – der Name wird gefunden, der konkrete Spam
  erreicht Score ~90 (Schwelle 60) → geblockt. (Verifiziert.)
- Fail-open: Ein Fehler im Plausi-Listener blockiert legitime Registrierungen nie.

## 1.0.19 – 2026-06-10

### Recht/Lizenz (Drittanbieter-Attribution)

- **ALTCHA-Lizenz sauber dokumentiert.** Das mitgelieferte lokale Captcha **ALTCHA**
  (`frontend/js/vendor/altcha.min.js`, v1.5.1) ist **MIT**-lizenziert und darf frei
  mit dem Plugin weitergegeben werden. MIT verlangt das Mitführen des
  Copyright-/Lizenzhinweises – dieser fehlte in der minifizierten Datei und ist jetzt
  ergänzt:
  - `frontend/js/vendor/altcha.LICENSE.txt` (vollständiger MIT-Text,
    © 2023-2026 Daniel Regeci, BAU Software s.r.o.)
  - MIT-Banner-Kommentar am Anfang der ausgelieferten `altcha.min.js`
  - `THIRD-PARTY-NOTICES.md` im Plugin-Stamm (ALTCHA, Alpine.js, Chart.js – alle MIT;
    Manrope-Font OFL 1.1)
- Damit ist der lokale, DSGVO-konforme Captcha-Schutz auch lizenzrechtlich sauber
  zum Mitliefern.

## 1.0.18 – 2026-06-10

### Behoben (Formular-Aktiv-Schalter, der eigentliche Bug)

- **Der AKTIV-Schalter im Formular-Schutz speicherte über die UI weiterhin nicht
  zuverlässig** – Ursache war diesmal das **Frontend** (nicht der Server, der per
  Direkt-Test nachweislich korrekt persistiert): Das Alpine-Binding
  `x-model` + `:true-value="1"` + `@change` sendete beim Umschalten einen
  **veralteten** `is_active`-Wert. Ersetzt durch robustes Binding
  (`:checked` + `@change`, das `is_active` direkt aus dem Event setzt). Der Schalter
  persistiert nun zuverlässig. (Der Server-seitige DELETE+INSERT-Fix aus 1.0.15
  bleibt korrekt.)

## 1.0.17 – 2026-06-10

### Neu (Logging-Privacy + Spam-Begründung)

- **Schalter „Formulardaten protokollieren"** (Einstellungen, DSGVO). Standard an.
  Aus = es werden nur Metadaten (IP, Formular, Methode, Score, Begründung)
  geloggt, **nicht** die eingereichten Felder (Name/E-Mail …). Greift in
  `CaptchaService::logSpam`.
- **Spam-Begründung im Detail-Panel.** Jeder Spam-Log-Eintrag speichert jetzt die
  Begründung (welche Methode/Regel mit welchem Score ausgelöst hat) und zeigt sie
  im „Details"-Panel an. Die Begründung ist kein personenbezogenes Datum und wird
  auch bei deaktiviertem Daten-Logging gespeichert (im `request_data`-JSON unter
  `_bbf_reason`, daher ohne Schema-Änderung/Migration).

## 1.0.16 – 2026-06-10

### Neu (Logging-Detail + Selbstbereinigung)

- **Erweitertes Logging:** Im Spam-Log gibt es pro Eintrag jetzt einen
  „Details"-Button, der die **eingereichten Formulardaten** der abgelehnten
  Übermittlung zeigt (Name, E-Mail usw. – z. B. bei Bot-Registrierungen),
  plus Methode/Score/User-Agent. (Die Daten wurden bereits sanitisiert
  gespeichert; jetzt sind sie einsehbar.)
- **Selbstbereinigung der Logs über Cron:** Neuer token-geschützter Endpoint
  `…/bbfdesign-captcha/api/v1/cron?token=…`, der Spam-Log (Aufbewahrung),
  alte Rate-Limit-Fenster und abgelaufene IP-Auto-Blocks bereinigt – für den
  **JTL-/Server-Cron**. Die URL steht im Backend unter Einstellungen →
  „Cron-Bereinigung (URL)". `HOOK_CRON_INC_SWITCH` ist in JTL 5.7 nicht mehr
  aktiv, daher dieser saubere Weg.
- **Automatischer Fallback:** Ist „Auto-Cleanup" an, läuft die Bereinigung auch
  ohne eingerichteten Cron automatisch – gedrosselt höchstens einmal pro
  Intervall (`cleanup_interval_hours`, Standard 24 h) über den normalen
  Shop-Traffic. Cron-Token wird bei Install/erstem Start erzeugt.

## 1.0.15 – 2026-06-10

### Behoben (KRITISCH: Formular-Schalter speicherte nicht)

- **„Aktiv"-Schalter (und Speichern) im Formular-Schutz wurden nicht dauerhaft
  gespeichert** – beim Hin- und Herklicken sprang der Schalter zurück. Ursache:
  Der Unique-Key ist `(form_type, form_identifier)`, aber `saveFormConfig` setzte
  `form_identifier` nicht (= NULL). In MySQL ist `NULL` im Unique-Index *distinct*,
  daher griff `ON DUPLICATE KEY UPDATE` nicht und **jeder Speichervorgang legte eine
  neue Zeile an** (Duplikate → nicht-deterministisches Zurücklesen).
- Fix: `saveFormConfig` speichert jetzt deterministisch per `DELETE` + `INSERT`
  (genau eine Zeile je Formular; räumt vorhandene Duplikate auf). Lese-Pfade
  (`getFormConfigsData`, `CaptchaService::getFormConfig`/`getActiveMethodsForForm`)
  sortieren `ORDER BY id`, sodass auch bei Altbeständen die zuletzt gespeicherte
  Zeile gewinnt. Der Schalter persistiert nun korrekt.

## 1.0.14 – 2026-06-10

### Geändert (Smart-Filter-Härtung)

- **Weitere Spam-Muster im Smart-Filter** (`checkSpamPhrases`, code-basiert):
  Pharma (viagra/cialis/kamagra – in einem Weinshop nie legitim, daher hoch
  gewichtet), SEO-/Marketing-Spam (SEO-Services, Backlinks, „rank your website")
  und Geld-/Scam-Phrasen (business proposal, „you have won", „dear sir"). Bewusst
  englischsprachig/hochsignifikant gewählt – legitime deutschsprachige Shop-Kontakte
  lösen sie praktisch nie aus (getestet: legitime DE-Anfrage Score 0, legitime
  Domain-Erwähnung 25 < Schwelle 60). Reale Spam-Mails (mit Link/Domain) erreichen
  über die Kombination die Schwelle.

## 1.0.13 – 2026-06-10

### Behoben (Spam rutschte durch – Registrierung)

Eine Bot-Registrierung mit Krypto-/Domain-Spam im Namensfeld
(„… 0.487 BTC for Review … yiuyoifjghhf.blogspot.com.uy") kam durch. Zwei Ursachen,
beide gefixt:

- **Smart-Spamfilter lief nicht für die Registrierung**, weil ohne gespeicherte
  `form_config`-Zeile (Tabelle nach Update/Reinstall leer) zur Laufzeit nur der
  Minimal-Default `['honeypot','timing']` griff. Jetzt liefern
  `CaptchaService::getActiveMethodsForForm`/`getFormConfig` **pro Formulartyp** die
  vollen Default-Methoden (Registrierung/Kontakt/Bewertung inkl. ALTCHA +
  Smart-Filter) – robust gegen verlorene Seed-Zeilen.
- **Smart-Filter erkennt jetzt diesen Inhalt**: Domains/URLs **ohne** `http://`
  (z. B. `*.blogspot.com.uy`, inkl. Spam-TLD-/Free-Hoster-Bonus) und
  **Krypto-/Investment-Muster** (BTC/ETH, „for review", Geldbeträge, Wallet). Beide
  Prüfungen sind code-basiert und greifen auch bei leerer Spam-Wörter-Tabelle.
  Der konkrete Spam erreicht damit Score ~90 (Schwelle 60) → geblockt.

## 1.0.12 – 2026-06-10

### Geändert (Backend-Konsistenz)

- **„Aktiv"-Anzeige im Formular-Schutz konsistent mit der Laufzeit.** Ohne
  gespeicherte DB-Zeile behandelt `CaptchaService::getFormConfig` ein Formular als
  aktiv (`is_active=1`) – das Backend zeigt jetzt denselben Zustand, statt
  unkonfigurierte Formulare fälschlich als „aus" darzustellen. So spiegelt der
  „Formulare"-Tab den tatsächlichen Schutz wider; einzelne Formulare lassen sich
  per Schalter deaktivieren (legt eine Zeile mit `is_active=0` an).

## 1.0.11 – 2026-06-10

### Behoben (Backend-Robustheit)

- **Formular-Schutz-Tabelle blieb leer**, wenn die DB-Zeilen in
  `bbf_captcha_form_config` fehlten (z. B. nach einem Plugin-Update/Reinstall –
  die `down()`-Migration macht `DROP TABLE`, der Install-Seed läuft beim Update
  nicht erneut). `getFormConfigs` lieferte dann nur die (leeren) DB-Zeilen.
- Jetzt liefert das Backend **immer alle 8 Standardformulare** (Kontakt,
  Registrierung, Newsletter, Bewertung, Checkout, Passwort vergessen, Wunschzettel,
  Login) mit den Default-Konfigurationen; gespeicherte DB-Werte überschreiben die
  Defaults, nicht gespeicherte Formulare sind opt-in inaktiv. Beim Aktivieren legt
  `saveFormConfig` die Zeile wieder an. Logik in `getFormConfigsData()`, auch fürs
  serverseitige Vorladen (kein Leer-Flash, robust gegen AJAX-Fehler).

## 1.0.10 – 2026-06-10

### Geändert (Backend-Optik, Phase 2)

- **Methodenauswahl im Formular-Schutz als saubere Pills statt roher Checkboxen.**
  Im Tab „Formulare" saßen in jedem Methoden-Chip kleine native Checkboxen – jetzt
  ist jede Methode ein anklickbarer Pill mit Status-Punkt, der bei Auswahl magenta
  füllt (Hover- und Tastatur-Fokus inklusive). Die Checkbox bleibt für
  Funktion/Barrierefreiheit erhalten, ist aber visuell ausgeblendet
  (`.bbf-method-chip`). Alle übrigen Backend-Schalter nutzen bereits die
  `.bbf-toggle`-Komponente und sind unverändert.

## 1.0.9 – 2026-06-10

### Behoben (KRITISCH, Lauffähigkeit JTL 5.6/5.7)

- **Schutz-Injektion in JTL 5.6/5.7 wiederhergestellt.** `HOOK_SMARTY_OUTPUTFILTER`
  übergibt seit JTL 5.6 ein **phpQuery-Dokument** (`$args['document']`) statt eines
  HTML-Strings (`$args['output']`). Der Output-Filter prüfte nur auf `output` und
  brach in 5.6/5.7 daher **immer ab** – es wurden **keine** Assets, Honeypot- oder
  Timing-Felder in die Formulare injiziert. Beim Live-Test gegen JTL 5.7.1
  (weinewald24.de) aufgefallen.
- Der Listener behandelt jetzt **beide** Übergabeformen: String (`output`, ältere
  JTL) und phpQuery-Dokument (`document`, 5.6/5.7, Injektion per `head`/`form`-
  Selektoren). Defensiv gekapselt (`try/catch`): der Filter lässt den Output im
  Fehlerfall unverändert und kann die Seite nie zerstören.
- Hintergrund-Relevanz: Ohne Injektion fehlte echten Absendungen das Timing-Token,
  was bei niedriger Schwelle zu Fehlsperren hätte führen können – mit dem Fix
  greifen Schutz **und** Fail-open wieder wie vorgesehen.

> Verifikation am echten Shop steht nach Redeploy aus; statisch gegen den
> 5.7.1-Quellcode (`JTLSmarty::outputFilter`, phpQuery-API) entwickelt, PHP-Lint grün.

## 1.0.8 – 2026-06-09

### Behoben (Diagnose)

- **Health-Endpoint meldet jetzt die echte Plugin-Version** statt hartkodiert
  „1.0.0". `/bbfdesign-captcha/api/v1/health` und der Header `X-BBF-Captcha-Version`
  spiegeln den live ausgelieferten Build wider – nützlich, um nach einem Update zu
  prüfen, welche Version tatsächlich aktiv ist. Defensiv gekapselt (der Health-Check
  scheitert nie an der Versionsermittlung).

## 1.0.7 – 2026-06-09

### Kompatibilität (Phase 1.1 des Masterplans)

- **JTL 5.7.1 quellcode-verifiziert kompatibel.** Alle genutzten Hooks (inkl.
  `HOOK_ROUTER_PRE_DISPATCH` für die API-/Altcha-Challenge-Routen) und SDK-Klassen/
  Methoden gegen den echten 5.7.1-Quellcode geprüft – keine Code-Änderung nötig.
- **`MinShopVersion` von 5.2.0 auf 5.5.0 korrigiert.** Das Plugin nutzt
  PHP-8.0-Funktionen (`str_contains`/`str_starts_with`) und Hook 400, die es in
  JTL 5.2 (PHP 7.4) nicht gibt – der alte Boden war irreführend. PHP-Syntax ist
  reines 8.0 (keine 8.1+-Features), daher abwärtskompatibel bis JTL 5.5 (PHP 8.0).
- Details und Verifikationsmatrix: `docs/compat/compatibility-jtl-5.x.md`.

## 1.0.6 – 2026-06-09

### Geändert (Frontend-Default, Phase 3.1 des Masterplans)

- **Frontend-Widget-CSS modernisiert und themable gemacht.** Feste Farben durch
  `--bbf-captcha-*` Custom Properties ersetzt (vom Admin-Custom-CSS/Theme
  überschreibbar), Fehler-/Erfolgs-Zustände einheitlich als dezente Boxen mit
  klarem Kontrast. Heller Default (passt zu den meisten Shops); eine Dark-Variante
  ist **opt-in** über die Vorfahren-Klasse `.bbf-captcha-theme-dark` – bewusst NICHT
  via `prefers-color-scheme`, das dem OS des Besuchers statt dem Shop-Theme folgen
  und in hellen Formularen falsch wirken würde.
- Honeypot-Positionierung (funktionaler Schutz) und Reduced-Motion unverändert.

> Reine CSS-Änderung am Widget; Layout des Kundenformulars unberührt. Visuelle
> Abnahme im echten Shop steht noch aus.

## 1.0.5 – 2026-06-09

### Geändert (Backend-Design, Phase 2 des Masterplans)

- **Admin-Backend an das Ticket-Designsystem angeglichen.** Primärakzent von Blau
  auf **Magenta `#db2e87`** (BBF-CI) umgestellt; das Security-Blau bleibt als
  Sekundär-Token (`--bbf-secondary`) erhalten. Token-Werte (Hintergründe, Border,
  Status) auf die Ticket-Werte gebracht; Akzent-Tokens `--bbf-cyan`/`--bbf-green`
  und ein Magenta-Fokus-Ring (`--bbf-focus-ring`) ergänzt.
- **Token-basierter Dark-Mode** in `admin-base.css` (Selektor `html.theme-dark`),
  sodass alle token-konsumierenden Komponenten automatisch dunkeln. Zusätzlich
  Kontrast-Fixes: Alert-Texte nutzen im Dark-Mode die helle Status-Farbe statt der
  fixen dunklen, und Input-Focus verwendet die Card-Farbe statt hartem Weiß.
- Hartkodierte Blau-Werte in `admin.css` und den Dashboard-Charts auf Magenta
  umgestellt (kategoriale Methoden-Palette der Charts bleibt bewusst vielfältig).

> Reine Admin-Backend-Änderung (kein Kunden-Hotpath). Token-Vollständigkeit
> deterministisch geprüft (keine undefinierte Variable), PHP-Lint/Template-Gate grün.
> Visuelle Abnahme hell/dunkel im echten Backend steht noch aus.

## 1.0.4 – 2026-06-09

### Neu (DSGVO, Phase 0.5 des Masterplans)

- **Optionale IP-Anonymisierung im Spam-Log (Opt-in, standardmäßig AUS).** Neuer
  Schalter `log_ip_anonymize`. Ist er aktiv, werden gespeicherte IP-Adressen
  DSGVO-konform gekürzt (IPv4 → /24, IPv6 → /48); der Auto-Block-Zähler arbeitet
  dann konsistent auf derselben Granularität, während die reale IP des
  Verursachers weiterhin präzise gesperrt wird. Default ist AUS – bestehendes
  Verhalten (volle IP, 90 Tage) bleibt unverändert, kein Risiko von
  CGNAT-Fehlsperren. Helper `PluginHelper::anonymizeIp()`, Default per
  Migration registriert (Gruppe `privacy`).

## 1.0.3 – 2026-06-09

### Robustheit (Phase 0.3 des Masterplans)

- **Render-Fail-safe bestätigt + Diagnose ergänzt.** Schlägt das Rendern des
  sichtbaren Captcha-Widgets fehl, bleibt der Schutz erhalten: Honeypot/Timing
  werden über den separaten Output-Filter eingespielt und die serverseitige
  POST-Validierung läuft unabhängig weiter – ein Render-Fehler öffnet das Formular
  also nicht. Der bislang **stille** Render-Fehler wird nun (im `debug_mode`)
  protokolliert; das Logging ist gekapselt und kann den Absende-Hotpath nie stören.

## 1.0.2 – 2026-06-09

### Behoben (Sicherheit/Robustheit, Phase 0.2 des Masterplans)

- **LLM-Zweitprüfung ist jetzt strikt non-blocking.** Eine LLM-Spam-Einstufung
  blockiert ein Absenden nur noch, wenn der lokale Heuristik-Filter bereits ein
  Korroborations-Signal liefert (mindestens „verdächtig", Score ≥ `ai_threshold_ok`).
  Eine LLM-Fehlklassifikation kann damit keinen echten Kunden mehr allein aussperren;
  LLM-Fehler/Timeouts sind weiterhin fail-open (kein Block).
- **Harte Wartezeit-Obergrenze für die LLM-Prüfung.** Das konfigurierbare
  `llm_timeout` ist von max. 60 s auf **max. 10 s** gedeckelt, damit die synchrone
  Prüfung ein echtes Absenden (auch Checkout) nie spürbar blockiert.

> Live-Smoke steht noch aus (`BBF_CAPTCHA_SMOKE_URL` nicht gesetzt). Fail-open
> konstruiert, PHP-Lint grün.

## 1.0.1 – 2026-06-09

### Behoben (Sicherheit/Robustheit, Phase 0 des Masterplans)

- **Fail-open bei Provider-Ausfall (Leitplanke „Kunden nie aussperren").** Ist ein
  externer Captcha-Dienst (Turnstile, reCAPTCHA, hCaptcha, Friendly Captcha) wegen
  Netzwerk-/Timeout-Fehler nicht erreichbar, liefert die Server-Verifizierung jetzt
  Score **0** statt 30 Strafpunkte. Ein Infrastruktur-Ausfall, den der Kunde nicht
  zu verantworten hat, kann damit nicht mehr zum Block eines echten Absendens
  beitragen. Fehlende oder ungültige Tokens (echte Bot-Signale) werden unverändert
  bestraft.

> Hinweis: Live-Smoke gegen einen echten Shop steht noch aus
> (`BBF_CAPTCHA_SMOKE_URL` war nicht gesetzt). Die Änderung ist fail-open
> konstruiert und statisch (PHP-Lint) geprüft.
