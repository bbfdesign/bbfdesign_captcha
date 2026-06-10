# Changelog

Alle nennenswerten Änderungen an BBF Captcha. Format an [Keep a Changelog]
angelehnt; Versionierung nach SemVer (Pflicht-Gate der Entwicklungssteuerung).

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
