# BBF Captcha – Führender Masterplan

Stand: 2026-06-09 · Plugin `bbfdesign_captcha` (Anzeigename **BBF Captcha**) ·
Version 1.0.0 · MinShopVersion 5.2.0

Verbindlicher Fahrplan, um BBF Captcha sauber, robust und erweiterbar zu machen.
Folgt der Entwicklungssteuerung (`CLAUDE.md`, `docs/claude-development-control.md`)
und den verifizierten Befunden in [`review-2026-06-09.md`](review-2026-06-09.md).
**Standalone-Lauffähigkeit zuerst.**

## Autopilot

Dieser Plan ist die Arbeits-Queue für den **autonomen Blockmodus**: Claude
arbeitet die Punkte selbstständig ab und **committet und pusht grüne Inkremente
ohne Rückfrage** (`bash tools/development-control.sh --release`). Die
Entscheidungen unten sind getroffen und werden nicht erneut erfragt. Hotpath-
Änderungen sind fail-open konstruiert (ein Fehler senkt nur den Schutz, sperrt
nie Kunden aus). Ist `BBF_CAPTCHA_SMOKE_URL` gesetzt, läuft der Live-Smoke mit;
sonst wird er als ausstehend vermerkt und weitergearbeitet.

## Leitplanken (über alle Phasen)

1. **Echte Kunden dürfen nie ausgesperrt werden.** Login, Registrierung und
   Checkout bleiben immer absendbar. Fail-open ist Grundhaltung.
2. **Standalone-Vorrang.** Das Plugin muss in **zwei** Zielbildern perfekt laufen:
   - **Bikepark** (JTL 5.7.x, NOVA/NOVA-Child, primäres Entwicklungsziel),
   - **Fremdshop ohne ein einziges BBF-Plugin** (kein Tickets, kein Produktberater,
     kein AI Concierge, kein Ajaxcart).
   Jede Fremdplugin-Kopplung ist **guarded** (Feature-Detection) und rein additiv.
3. **Sekret-Dichtheit & DSGVO** sind nicht verhandelbar (Secret-Scan-Gate, IP-Schutz).
4. **Theme-Unabhängigkeit** über Feature-Detection statt harter Selektoren.
5. Jede Phase wird über `--local` grün gefahren; Runtime-Änderungen erhöhen
   `info.xml` und CHANGELOG.

## Getroffene Entscheidungen (verbindlich)

1. **Backend-Akzent: Magenta `#db2e87`** primär (BBF-CI/Ticket-Plugin), Security-Blau
   `#2563eb` sekundär für Status/Badges; volle `--bbf-ui-*`-Token-Übernahme.
2. **MinShopVersion bleibt 5.2.0**; primäres Testziel 5.7.1; Feature-Detection wo nötig.
3. **Phase-4-Anbieter-Reihenfolge:** Produktberater → AI Concierge → Suche, alle guarded.
4. **Kein Such-/Layer-Overlay in diesem Plugin** (siehe „Bewusst nicht in diesem
   Plugin").

---

## Phase 0 — Fundament & Härtung (Lauffähigkeit/Sicherheit zuerst)

Ziel: Das Plugin sperrt nie Kunden aus, leakt nichts, bricht nicht still. Vor der
Versions-/Template-Matrix, weil diese Punkte Live-Risiko tragen.

- **0.1 Fallback-Kaskade für externe Provider.** Timeout/Netzfehler eines externen
  Captchas darf nicht in eine harte Sperre münden: automatischer Rückfall auf den
  lokalen Pfad (Altcha/Honeypot/Timing), freundliche Nutzermeldung statt
  „Security check failed". Strafscore nur additiv, nie allein blockierend.
- **0.2 LLM strikt non-blocking.** LLM-Zweitprüfung nie alleiniges Block-Kriterium
  im Absende-Hotpath; striktes Fail-open bei Timeout/Fehler; Ergebnis-Caching pro
  Text-Hash; harte Wartezeit-Obergrenze.
- **0.3 Render-Fail-safe.** Schlägt das Widget-Rendering fehl, wird sicherer
  Minimal-Schutz (Honeypot/Timing) ausgeliefert, nie ein offenes Formular.
- **0.4 Custom-CSS härten.** Admin-CSS nicht mehr roh in `<style>`; Whitelist-/
  Validator-Pipeline (nur erlaubte Eigenschaften/Zeichen, kein `url(...)`-Exfil,
  kein Tag-Breakout). Secret-Scan-Gate bleibt grün.
- **0.5 DSGVO-Logging.** IP-Anonymisierungs-Option (IPv4 /24, IPv6 /64),
  konfigurierbare Aufbewahrung, Export mit Anonymisierungs-Filter; Log-Cleanup
  zuverlässig auslösen (Hook/Cron statt nur Methode).
- **0.6 DB-Härtung.** Migrationen auf Idempotenz, Defaults und Down-Pfade prüfen;
  keine Pflichtspalte ohne Default; Zählungen vorher/nachher dokumentieren.

**Done:** Provider-/LLM-Ausfall simuliert → Formular bleibt absendbar;
CSS-Breakout wirkungslos; Logs anonymisierbar; `--local` grün.

## Phase 1 — Template- & Versionsrobustheit (Kernziel „läuft überall")

Ziel: Identisches, korrektes Verhalten in Bikepark **und** in einem Fremdshop ohne
BBF-Plugins, über die relevanten JTL-Versionen.

- **1.1 Versionsmatrix.** JTL **5.2–5.7.x**, primär gegen **5.7.1** entwickelt;
  nur stabile JTL-5-APIs; defensiv per Feature-Detection.
- **1.2 Theme-unabhängige Auto-Platzierung.** Das Widget erscheint sauber, auch
  wenn das Template `{$bbfCaptchaWidget}` nicht setzt: zuverlässige Platzierung
  über HTML-Marker und robuste „vor dem Submit"-Heuristik — ohne harte Theme-/
  Form-ID-Selektoren. NOVA, NOVA-Child und ein neutrales Fremdtemplate sind Pflicht.
- **1.3 Form-Abdeckung verifizieren.** Kontakt, Login, Registrierung, Newsletter,
  Bewertung, Passwort-Reset, Checkout, Wunschliste: je einmal legitimes Absenden
  und je eine Bot-Simulation (Honeypot, zu schnell, geblockte IP, ungültiges Token).
- **1.4 Standalone-Smoke.** `--smoke` gegen eine Bikepark-Formularseite **und**
  gegen einen sauberen Fremdshop; Schutz-Asset referenziert, HTTP 200, keine
  neuen JS-/Smarty-/SQL-Fehler.

**Done:** In beiden Zielshops alle Formulare geschützt und absendbar, Widget
theme-unabhängig, keine Konsolen-/Shop-Log-Fehler.

## Phase 2 — Backend an das Ticket-Designsystem angleichen

Ziel: Das Plugin-Backend folgt dem verbindlichen BBF-Designsystem des
Ticket-Plugins (operative Arbeitsflächen, ruhige Dichte, hell/dunkel).

- **2.1 Token-Architektur übernehmen.** `--bbf-ui-*`-Familie (`-bg`, `-surface`/
  `-soft`/`-muted`, `-border`/`-strong`, `-text`/`-strong`/`-muted`/`-subtle`,
  `-accent`/`-cyan`/`-green`/`-warning`/`-danger`, `-radius`/`-sm`, `-shadow`/`-soft`,
  `-focus`), Manrope, Dark-Mode über `html.theme-dark .bbf-plugin-page`. Akzent
  **Magenta** primär, Blau sekundär. Alte `--bbf-primary/--bbf-*`-Familie ablösen.
- **2.2 Komponenten angleichen.** Gradient-Cards (Radius 18px, weiche Schatten,
  kein Hover-Sprung), Buttons (min-height 38px, Gradient-Primary, Fokus-Ring),
  Inputs (Fokus-Ring), Status-Badges (Pill), Tabellen mit Sticky-Head und
  **Mobile-Kartenlayout** (`data-label`/`::before`), optionaler Detail-Drawer für
  Log-/IP-Detailansichten.
- **2.3 AJAX/CSRF vereinheitlichen.** `_bbfPost`-Pattern übernehmen (`jtl_token`,
  Array-/JSON-Handling, JSON-statt-HTML-Guard), einheitliches Notification-System,
  Alpine.js nur wo reaktiv nötig.
- **2.4 Alle Backend-Tabs reskinnen.** Dashboard, Einstellungen, Formularschutz,
  IP-Verwaltung, LLM, Log, API, Doku — hell/dunkel/mobil, nichts abgeschnitten,
  Tabellen scanbar.

**Done:** Backend optisch nicht vom Ticket-Standard zu unterscheiden, funktional
unverändert.

## Phase 3 — Moderne, saubere Default-Frontend-Ansicht

Ziel: Was Kunden sehen, ist sauber, modern, hell/dunkel-fähig und stört das
geschützte Formular nie.

- **3.1 Frontend-CSS modernisieren.** Custom-Properties (`--bbf-captcha-*`),
  Dark-Mode (`prefers-color-scheme` + optionaler Theme-Hook), saubere Spacing/Typo,
  responsive; Widget verschiebt/zerschneidet das Formular nicht; A11y (ARIA-Live,
  Reduced-Motion) bleibt.
- **3.2 Zustände themable.** Fehler-/Erfolgs-/Lade-Zustände über Tokens statt
  Festfarben; sauberer Kontrast hell/dunkel.
- **3.3 Settings wirken.** Sicht-/Verhaltens-Settings haben echte Frontend-Wirkung
  (keine toten Optionen); Custom-CSS aus der gehärteten Pipeline (0.4).

**Done:** Default-Widget modern und ruhig, hell/dunkel/mobil gleichwertig, ohne
Layout-Sprünge; keine toten Settings.

## Phase 4 — Erweiterungs-API für Fremdplugin-Inhalte (guarded)

Ziel: Andere BBF-Plugins können in definierte Slots Inhalte einspeisen
(Produktvorschläge des Produktberaters, Empfehlungen des AI Concierge,
Suchergebnisse) — **ohne** dass BBF Captcha je von ihnen abhängt. Gleiches
Kopplungsmuster wie Produktberater ↔ Ajaxcart: Feature-Detection, JS-Events.

- **4.1 Template-Slots.** Sichere Smarty-Slots vor/nach dem Widget
  (`$bbfCaptchaBeforeWidget` / `$bbfCaptchaAfterWidget`) plus ein Hook für
  serverseitige Anbieter.
- **4.2 JS-Event-/Provider-API.** `window.BBFCaptcha` um echte `CustomEvent`s
  erweitern (`bbf:captcha:form-ready`, `bbf:captcha:slot-render`) und eine schlanke
  Registrierungs-API für Content-Provider. Stabiler, dokumentierter Vertrag.
- **4.3 Guarded Consumption.** Anbieter per Feature-Detection erkennen
  (Reihenfolge: Produktberater → AI Concierge → Suche). Fehlt der Anbieter, bleibt
  der Slot leer und das Captcha unverändert. Standalone nie betroffen.
- **4.4 Optionaler Content-Endpunkt.** `…/api/v1/content/{form_type}` als neutraler,
  optionaler Kanal (CSRF/Rate-Limit wie die übrige API).

**Done:** Mit installiertem Anbieter erscheinen Vorschläge im Slot; ohne ihn ist
das Frontend identisch und fehlerfrei. Vertrag dokumentiert (`docs/extension-api.md`).

---

## Bewusst NICHT in diesem Plugin

- **Instant-Search-/Layer-Overlay („Doofinder-artig").** Eine vollwertige
  Such-/Vorschlags-Oberfläche ist ein eigenes Such-/Empfehlungs-Produkt und gehört
  in den **Produktberater** (`bbf_productadvisor`) oder ein dediziertes Suchplugin —
  nicht in die Schutz-Schicht. Sie würde das Captcha-Plugin aufblähen, ein schweres
  Frontend-Modul neben den Schutz-Hotpath setzen und Verantwortung duplizieren.
  BBF Captcha stellt über Phase 4 nur den optionalen Host bereit, in den ein solches
  Plugin rendern *könnte*. (Ursprünglich vom Nutzer als „hinten anstellen" angefragt,
  nach Prüfung als Plugin-fremd verworfen.)

## Querschnitt & Reihenfolge-Disziplin

- **Standalone bleibt Vorrang:** Phase 4 darf den Standalone-Pfad nie
  verschlechtern. Jede Kopplung ist optional und guarded.
- **Gates pro Schritt:** `--local` grün; bei Frontend-Nähe `--smoke` gegen Bikepark
  und Fremdshop, sofern `BBF_CAPTCHA_SMOKE_URL` gesetzt.
- **Versionierung:** Jede Runtime-Änderung erhöht `info.xml` und CHANGELOG.
- **Autonom:** Die Queue wird von oben abgearbeitet; akute Live-Risiken (Phase 0)
  zuerst; grüne Inkremente werden ohne Rückfrage committet und gepusht.
