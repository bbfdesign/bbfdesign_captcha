# BBF Captcha – Führender Masterplan

Stand: 2026-06-09 · Plugin `bbfdesign_captcha` (Anzeigename **BBF Captcha**) ·
Version 1.0.0 · MinShopVersion 5.2.0

Dieser Masterplan ist der verbindliche Fahrplan, um BBF Captcha sauber, robust
und erweiterbar zu machen. Er folgt der Entwicklungssteuerung
(`CLAUDE.md`, `docs/claude-development-control.md`) und baut auf den
verifizierten Befunden in [`review-2026-06-09.md`](review-2026-06-09.md) auf.
Vorgehen analog zum Schwester-Plugin Ajaxcart: **Standalone-Lauffähigkeit zuerst**.

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
5. Jede Phase wird über `bash tools/development-control.sh --local` grün gefahren;
   Runtime-Änderungen erhöhen `info.xml` (und CHANGELOG, sobald vorhanden).

## Zielbild-Reihenfolge (vom Nutzer vorgegeben)

1. Plugin läuft **sauber & korrekt** über Templates und Shop-Versionen — standalone.
2. **Backend-Design = Ticket-Plugin** (BBF-Designsystem).
3. **Frontend-Default**: sauber, korrekt, modern, hell/dunkel — und vorbereitet,
   Inhalte aus Fremdplugins (AI Concierge, Produktberater/Cross-Selling, Suche)
   anzuzeigen.
4. **Erweiterungs-API**, über die solche Fremdplugin-Vorschläge eingespeist werden.
5. **Hinten angestellt**: Instant-Search-/Layer-Overlay (vom Nutzer als
   „Doofinder-artig" beschrieben).

---

## Phase 0 — Fundament & Härtung (Lauffähigkeit/Sicherheit zuerst)

Ziel: Das Plugin sperrt nie Kunden aus, leakt nichts und bricht nicht still.
Reihenfolge bewusst vor der Versions-/Template-Matrix, weil diese Punkte
Live-Risiko tragen.

- **0.1 Fallback-Kaskade für externe Provider.** Timeout/Netzfehler eines externen
  Captchas darf nicht in eine harte Sperre münden: automatischer Rückfall auf den
  lokalen Pfad (Altcha/Honeypot/Timing), klare, freundliche Nutzermeldung statt
  „Security check failed". Strafscore nur additiv, nie allein blockierend.
- **0.2 LLM strikt non-blocking.** LLM-Zweitprüfung nie als alleiniges
  Block-Kriterium im Absende-Hotpath; striktes Fail-open bei Timeout/Fehler;
  Ergebnis-Caching pro Text-Hash; harte Obergrenze für die Wartezeit.
- **0.3 Render-Fail-safe.** Schlägt das Widget-Rendering fehl, wird ein sicherer
  Minimal-Schutz (Honeypot/Timing) ausgeliefert, nie ein leeres/offenes Formular.
- **0.4 Custom-CSS härten.** Admin-CSS nicht mehr roh in `<style>`; Whitelist-/
  Validator-Pipeline (nur erlaubte Eigenschaften/Zeichen), kein `url(...)`-Exfil,
  kein Tag-Breakout. Secret-Scan-Gate bleibt grün.
- **0.5 DSGVO-Logging.** IP-Anonymisierungs-Option (IPv4 /24, IPv6 /64),
  konfigurierbare Aufbewahrung, Export mit Anonymisierungs-Filter; Log-Cleanup
  zuverlässig auslösen (Hook/Cron statt nur vorhandener Methode).
- **0.6 DB-Härtung.** Migrationen auf Idempotenz, Defaults und Down-Pfade prüfen;
  keine Pflichtspalte ohne Default; Zählungen vorher/nachher dokumentieren.

**Definition of Done:** Provider-Ausfall und LLM-Ausfall simuliert → Formular
bleibt absendbar; Custom-CSS-Breakout-Versuch wirkungslos; Logs anonymisierbar;
`--local`-Gate grün.

## Phase 1 — Template- & Versionsrobustheit (Kernziel „läuft überall")

Ziel: Identisches, korrektes Verhalten in Bikepark **und** in einem Fremdshop
ohne BBF-Plugins, über die relevanten JTL-Versionen.

- **1.1 Versionsmatrix.** Zielbild: JTL **5.2–5.7.x**, primär entwickelt gegen
  **5.7.1** (Bikepark-Live). MinShopVersion 5.2.0 bestätigen oder begründet anheben.
  Nur stabile JTL-5-APIs nutzen; wo nötig, defensiv per Feature-Detection.
- **1.2 Theme-Unabhängige Auto-Platzierung.** Das Widget muss auch dann sauber
  erscheinen, wenn das Template `{$bbfCaptchaWidget}` nicht setzt: zuverlässige
  Platzierung über einen HTML-Marker und eine robuste „vor dem Submit"-Heuristik —
  ohne harte Theme-/Form-ID-Selektoren. NOVA, NOVA-Child und ein neutrales
  Fremdtemplate gelten als Pflichtziele.
- **1.3 Form-Abdeckung verifizieren.** Kontakt, Login, Registrierung, Newsletter,
  Bewertung, Passwort-Reset, Checkout, Wunschliste: je einmal legitimes Absenden
  und je eine Bot-Simulation (Honeypot gefüllt, zu schnell, geblockte IP,
  ungültiges Token) durchspielen.
- **1.4 Standalone-Smoke.** `--smoke` gegen eine Bikepark-Formularseite **und**
  gegen einen sauberen Fremdshop ohne BBF-Plugins; Schutz-Asset wird referenziert,
  HTTP 200, keine neuen JS-/Smarty-/SQL-Fehler.

**Definition of Done:** In beiden Zielshops sind alle Formulare geschützt und
absendbar, das Widget erscheint theme-unabhängig, keine Konsolen-/Shop-Log-Fehler.

## Phase 2 — Backend an das Ticket-Designsystem angleichen

Ziel: Das Plugin-Backend folgt dem verbindlichen BBF-Designsystem des
Ticket-Plugins (operative Arbeitsflächen, ruhige Dichte, hell/dunkel).

- **2.1 Token-Architektur übernehmen.** `--bbf-ui-*`-Familie einführen
  (`-bg`, `-surface`/`-soft`/`-muted`, `-border`/`-strong`, `-text`/`-strong`/
  `-muted`/`-subtle`, `-accent`/`-cyan`/`-green`/`-warning`/`-danger`,
  `-radius`/`-sm`, `-shadow`/`-soft`, `-focus`), Manrope-Font, Dark-Mode über
  `html.theme-dark .bbf-plugin-page`. Die alte `--bbf-primary/--bbf-*`-Familie wird
  abgelöst.
- **2.2 Komponenten angleichen.** Gradient-Cards (Radius 18px, weiche Schatten,
  kein Layout-Sprung bei Hover), Buttons (min-height 38px, Gradient-Primary,
  Fokus-Ring), Inputs (Fokus-Ring), Status-Badges (Pill), Tabellen mit
  Sticky-Head und **Mobile-Kartenlayout** (`data-label`/`::before`), optionaler
  Detail-Drawer für Log-/IP-Detailansichten.
- **2.3 AJAX/CSRF vereinheitlichen.** `_bbfPost`-Pattern des Ticket-Backends
  übernehmen (`jtl_token`, Array-/JSON-Handling, JSON-statt-HTML-Guard),
  einheitliches Notification-System, Alpine.js nur dort, wo reaktiv nötig.
- **2.4 Alle Backend-Tabs reskinnen.** Dashboard, Einstellungen, Formularschutz,
  IP-Verwaltung, LLM, Log, API, Doku — hell/dunkel/mobil geprüft, nichts
  abgeschnitten, Tabellen scanbar.

**Offene Entscheidung (Akzentfarbe):** Das Captcha-Backend nutzt heute bewusst
**Blau** („Security/Trust"). Der Auftrag „Design wie das Ticket-Plugin" spricht
für **Magenta** (BBF-CI). Vorschlag: vollständige Übernahme der Ticket-
Token-Architektur und -Komponenten; **Magenta als Primärakzent** gemäß BBF-CI,
Blau optional als sekundärer Security-Akzent (Badges/Status). Endgültige Festlegung
vor Phase-2-Umsetzung.

**Definition of Done:** Das Backend ist optisch nicht vom Ticket-Standard zu
unterscheiden (Tokens/Komponenten/Dark-Mode/AJAX), funktional unverändert.

## Phase 3 — Moderne, saubere Default-Frontend-Ansicht

Ziel: Das, was Kunden sehen, ist sauber, modern, hell/dunkel-fähig und stört das
geschützte Formular nie.

- **3.1 Frontend-CSS modernisieren.** Custom-Properties einführen
  (`--bbf-captcha-*`), Dark-Mode (`prefers-color-scheme` + optionaler Theme-Hook),
  saubere Spacing/Typo, responsive; Widget verschiebt/zerschneidet das Formular
  nicht; A11y (ARIA-Live, Reduced-Motion) bleibt erhalten.
- **3.2 Zustände themable.** Fehler-/Erfolgs-/Lade-Zustände über Tokens statt
  Festfarben; sauberer Kontrast hell/dunkel.
- **3.3 Settings wirken.** Sicht- und Verhaltens-Settings müssen echte
  Frontend-Wirkung haben (keine toten Optionen); Custom-CSS aus der gehärteten
  Pipeline (0.4).

**Definition of Done:** Default-Widget wirkt modern und ruhig, hell/dunkel/mobil
gleichwertig, ohne Layout-Sprünge; keine toten Settings.

## Phase 4 — Erweiterungs-API für Fremdplugin-Inhalte (guarded)

Ziel: Andere BBF-Plugins können in definierte Slots Inhalte einspeisen
(z. B. Produktvorschläge des Produktberaters, Antworten/Empfehlungen des AI
Concierge, Suchergebnisse) — **ohne** dass BBF Captcha jemals von ihnen abhängt.
Gleiches Kopplungsmuster wie Produktberater ↔ Ajaxcart: Feature-Detection,
JS-Events, nie harte Abhängigkeit.

- **4.1 Template-Slots.** Definierte, sichere Smarty-Slots vor/nach dem Widget
  (`$bbfCaptchaBeforeWidget` / `$bbfCaptchaAfterWidget`) plus ein Hook, über den
  serverseitige Anbieter Inhalt beisteuern.
- **4.2 JS-Event-/Provider-API.** `window.BBFCaptcha` um echte `CustomEvent`s
  erweitern (z. B. `bbf:captcha:form-ready`, `bbf:captcha:slot-render`) und eine
  schlanke Registrierungs-API für „Content-Provider", die in die Slots rendern.
  Stabiler, dokumentierter Vertrag (Event-Namen + Payload).
- **4.3 Guarded Consumption.** Anbieter werden per Feature-Detection erkannt
  (vorhandene JS-Globals/Events der jeweiligen Plugins). Fehlt der Anbieter,
  bleibt der Slot leer und das Captcha unverändert. Standalone-Betrieb ist nie
  betroffen.
- **4.4 Optionaler Content-Endpunkt.** `…/api/v1/content/{form_type}` als neutraler
  Kanal, über den ein Anbieter serverseitig Vorschläge liefern kann (CSRF/Rate-Limit
  wie die übrige API), ohne Pflicht.

**Definition of Done:** Mit installiertem Produktberater/AI Concierge erscheinen
Vorschläge im Slot; ohne sie ist das Frontend identisch und fehlerfrei. Der
Vertrag ist dokumentiert (`docs/extension-api.md`).

## Phase 5 (hinten angestellt) — Instant-Search-/Layer-Overlay

Ziel: Ein modernes Such-Overlay mit Sofortergebnissen und Produktvorschlägen
(vom Nutzer als „Doofinder-artig" beschrieben). **Erst beginnen, wenn Phasen 0–4
stabil sind.** Konsumiert ausschließlich über die Phase-4-API; kein eigener
Suchindex, sondern Anbindung an einen Such-/Empfehlungs-Anbieter (Suche,
Produktberater, AI Concierge), guarded.

- **5.1 Konzeptdokument** (`docs/refactor/search-overlay-concept.md`): UX, Layer-UI,
  Datenfluss, Anbieter-Vertrag, Performance/Debounce, A11y, Dark-Mode.
- **5.2 Eigenständiges Frontend-Modul** (separates JS/CSS, getrennt vom
  Schutz-Hotpath, damit das Kerngeschäft nie ausgebremst wird).
- **5.3 Layer/Overlay** mit Sucheingabe, Sofortergebnissen, Produktkarten;
  responsive, hell/dunkel, animiert; Ergebnisse über die Phase-4-Provider-API.

**Definition of Done:** Overlay läuft mit angebundenem Anbieter; ohne Anbieter ist
es unsichtbar/inaktiv; der Schutz-Hotpath bleibt unbeeinflusst.

---

## Querschnitt & Reihenfolge-Disziplin

- **Standalone bleibt Vorrang:** Phasen 4–5 dürfen den Standalone-Pfad nie
  verschlechtern. Jede Kopplung ist optional und guarded.
- **Gates pro Schritt:** `--local` muss grün sein; bei Frontend-Nähe `--smoke`
  gegen Bikepark **und** Fremdshop.
- **Versionierung:** Jede Runtime-Änderung erhöht `info.xml`; CHANGELOG/README
  anlegen, sobald die erste Runtime-Phase startet (das Gate erzwingt Konsistenz).
- **Autonomer Blockmodus:** Bei „weiter/arbeite/lege los" wird die Phasen-Queue
  von oben abgearbeitet; akute Live-Risiken (Phase 0) zuerst.

## Offene Entscheidungen

1. **Akzentfarbe Backend** — Magenta (BBF-CI, wie Ticket-Plugin) vs. bewusstes
   Security-Blau. Vorschlag: Magenta primär, Blau als sekundärer Akzent.
2. **MinShopVersion** — bei 5.2.0 belassen oder auf 5.5.0 anheben (engere, besser
   testbare Matrix wie bei Ajaxcart).
3. **Vorschlags-Anbieter Phase 4** — Reihenfolge der Anbindung
   (Produktberater zuerst, dann AI Concierge, dann Suche?).
