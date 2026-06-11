# Changelog

Alle nennenswerten Änderungen an BBF Captcha. Format an [Keep a Changelog]
angelehnt; Versionierung nach SemVer (Pflicht-Gate der Entwicklungssteuerung).

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
