# Claude Entwicklungssteuerung für BBF Captcha

Stand: 2026-06-09

Diese Steuerung ist der verbindliche Arbeitsmodus für Claude im Plugin
`bbfdesign_captcha` (Anzeigename: **BBF Captcha**). Sie ist von der
Codex-Steuerung des Schwester-Plugins `bbfdesign_tickets` abgeleitet und
proportional auf dieses Schutz-Plugin sowie auf Claude als Entwickler angepasst.

## 1. Zielbild

BBF Captcha hält Bots und Spam von Shop-Formularen fern, ohne echte Kunden zu
behindern. Es bündelt mehrere Schutzschichten: Captcha-Provider (Altcha,
Turnstile, Friendly, reCAPTCHA, hCaptcha), Honeypot, Timing-Analyse,
Rate-Limiting, IP-Allow-/Blocklisten, Bot-Erkennung und eine optionale
LLM-/AI-Zweitprüfung verdächtiger Inhalte. Verwaltung, Dashboard, Logs und
API liegen im Plugin-Backend.

Priorität:

1. **Durchlässigkeit für echte Nutzer** — geschützte Formulare (Kontakt, Login,
   Registrierung, Bewertung, Newsletter, ggf. Checkout) müssen absendbar bleiben.
2. **Fail-open bei Ausfall** — Provider- oder LLM-Ausfall/Timeout sperrt nicht
   hart, sondern fällt zurück (nächster Schutz / Logeintrag / Durchlass).
3. **Sekret-Dichtheit** — keine Secret-Keys oder API-Schlüssel im Frontend, Log
   oder Export; nur öffentliche Sitekeys gehen an den Browser.
4. **DSGVO** — IP-Adressen und Spam-Logs zweckgebunden, mit Aufbewahrungsgrenze,
   nie ungefiltert exportiert.
5. **Wirksamer Schutz** — Bots und offensichtlicher Spam werden zuverlässig
   abgefangen, ohne False-Positive-Last für echte Kunden.
6. Frontend-/Backend-CI hell/dunkel/mobil sauber; Geruch entfernen statt erzeugen;
   neue Provider/Module nur opt-in.

## 2. Rollenmodell

Claude arbeitet intern mit diesen Perspektiven:

### Lead Implementer
- liest vorhandenen Code (Bootstrap, Hooks, Services) vor Edits, nutzt bestehende
  Patterns (Service-Schicht pro Provider, Middleware, Setting-Modell).
- schneidet Änderungen klein genug für sichere Tests.
- baut keine neue Abstraktion ohne echten Nutzen.

### Functional QA Reviewer
- testet **echtes Absenden** jedes betroffenen Formulars als legitimer Nutzer.
- testet **Bot-/Spam-Simulation** (Honeypot gefüllt, zu schnelles Absenden,
  geblockte IP, ungültiges Captcha-Token) → wird sauber abgefangen.
- prüft Fail-open: Provider/LLM nicht erreichbar → Formular bleibt nutzbar.
- prüft, dass Login/Registrierung/Checkout nie hart blockieren.
- dokumentiert Tests, die nicht möglich waren.

### Security/Privacy Reviewer
- prüft, dass Provider-Secrets und LLM-Schlüssel serverseitig bleiben
  (Secret-Scan-Gate). Im Frontend nur Sitekeys.
- prüft CSRF auf Admin-AJAX/API-Endpunkten und Output-Escaping (kein XSS über
  Formular-/Log-Inhalte).
- prüft DSGVO: IP-/Spam-Log zweckgebunden, Aufbewahrungsgrenze, kein ungefilterter
  Export, Consent berücksichtigt.
- prüft externe Calls auf Timeouts; keine Secrets in Logs.

### UI/UX Reviewer
- prüft Captcha-Widget und Backend (Dashboard, Einstellungen, Logs) in
  Hell-/Dunkelmodus und mobil.
- achtet auf Kontrast, Lesbarkeit und dass das Widget das geschützte Formular
  nicht verschiebt oder abschneidet.

### Skeptiker
- sucht Theme-/Template-Inkompatibilitäten, Konflikte mit anderen Form-Plugins,
  doppelte Interception, Edge-Cases im SmartyOutputFilter, False Positives,
  stille JS-Regressionen und Provider-Rate-Limits.

## 3. Harte Regeln

- Keine Änderung an Formular-Interception, Provider-Verifizierung oder
  Bot-Scoring ohne Vorher-/Nachher-Test (echtes Absenden + Bot-Simulation).
- Echte Kunden dürfen nie ausgesperrt werden; Login/Registrierung/Checkout
  bleiben absendbar.
- Fail-open ist Grundhaltung; härtere Modi nur bewusst, dokumentiert, getestet.
- Externe Calls (Provider-Verify, LLM) immer mit Timeout, nie unbegrenzt im
  Absende-Hotpath.
- Provider-Secrets und LLM-/API-Schlüssel bleiben serverseitig — nie im Frontend,
  Log, Template, Export oder Mail.
- Admin-AJAX/API nur mit gültigem CSRF-Token. Nutzereingaben sauber escapen.
- DB: keine `DROP TABLE`, keine Tabellenumbenennung, keine Pflichtspalte ohne
  Default; Migrationen idempotent.
- IP-/Spam-Daten zweckgebunden mit Aufbewahrungsgrenze; kein ungefilterter Export.
- Neue Provider/Schutzmodule standardmäßig deaktiviert, bis getestet und freigegeben.
- Deutsche Texte verwenden echte Umlaute. Keine fremden Pluginnamen in Code/Doku.

## 4. Standard-Gates

Lokaler Standard:

```bash
bash tools/development-control.sh --local
```

Gates dahinter (in `tools/dev-cycle.sh`):
Versionsabgleich · PHP-Lint (`Bootstrap.php` + `src/` + `Migrations/`) ·
Secret-Scan (keine Provider-/LLM-Schlüssel in Frontend-Assets) ·
Asset-/Template-Sanity (Pflichtdateien + ausgeglichene Smarty-Blöcke).

Live-Smoke gegen eine echte Shop-Seite mit geschütztem Formular:

```bash
BBF_CAPTCHA_SMOKE_URL="https://<shop>/<formularseite>" \
  bash tools/development-control.sh --smoke
```

Push nach grünem lokalen Gate und sauberem Commit:

```bash
bash tools/development-control.sh --release
```

Status:

```bash
bash tools/development-control.sh --status
```

## 5. Testmatrix nach Änderungstyp

### Formular-Schutz (Hauptfall)
- Betroffene Formulare laden HTTP 200, Schutz-Asset wird referenziert.
- Legitimes Absenden funktioniert (Kontakt/Login/Registrierung/Bewertung).
- Bot-Simulation wird abgefangen: Honeypot gefüllt, zu schnelles Absenden,
  geblockte IP, ungültiges/fehlendes Captcha-Token.
- Provider-/LLM-Ausfall → Fail-open, Formular bleibt nutzbar.
- Browser-Konsole ohne neue JS-Fehler; Widget verschiebt das Formular nicht.

### Provider-Integration
- Verifizierung nutzt das serverseitige Secret; Frontend nur Sitekey.
- Timeout greift; kein Hängen im Absende-Hotpath.
- Bei abgelaufenem/ungültigem Schlüssel verständliche Meldung statt Sperre.

### DB / Migrationen
- Migration idempotent; Up/Down sauber; keine Pflichtspalte ohne Default.
- IP-/Spam-Log mit Aufbewahrungslogik; Zählung vorher/nachher dokumentiert.

### Backend-UI (Dashboard/Einstellungen/Logs)
- Plugin-Tabs laden ohne Template-Fehler.
- AJAX-Speichern liefert valides JSON, CSRF greift.
- Hell-/Dunkelmodus und mobile Breite geprüft.

### Sichtbare Änderungen
- Hell-/Dunkelmodus gleichwertig, mobil geprüft.
- Keine abgeschnittenen Controls, keine Hover-Layout-Sprünge.

## 6. Autonomer Blockmodus (Autopilot)

Standardbetrieb ist autark. Wenn der Nutzer „weiter", „arbeite", „lege los",
„mach autonom" o. ä. sagt — und generell, solange eine offene Queue existiert —
arbeitet Claude den führenden Masterplan (`docs/refactor/masterplan.md`)
selbstständig ab und **committet und pusht grüne Inkremente eigenständig**
(`--release`), ohne Rückfrage. Arbeitsschleife pro Punkt:
lesen → klein ändern → `--local`-Gate → (falls `BBF_CAPTCHA_SMOKE_URL` gesetzt)
`--smoke` → committen → pushen → nächster Punkt.

Reihenfolge innerhalb einer Phase:

1. Akute Vorfälle zuerst: echtes Formular nicht absendbar / Kunden ausgesperrt.
2. Dann Fail-open und Sekret-Dichtheit sichern.
3. Dann False Positives / Schutzwirkung verbessern.
4. Dann Geruch in sicherer Reihenfolge.
5. Dann UI/UX-Konsolidierung.
6. Dann neue, opt-in Provider/Module.

**Hotpath-Pflicht:** Jede Änderung an Formular-Interception, Provider-Verifizierung
oder Bot-Scoring wird **fail-open** konstruiert — ein Fehler führt nur zu *weniger*
Schutz, nie zur Aussperrung echter Kunden. Damit ist autonomes Pushen sicher
gegenüber der obersten Regel. Ist kein Smoke-Ziel konfiguriert, wird der
ausstehende Live-Smoke im Commit/Doku vermerkt (kein Blocker).

Bereits in §11 getroffene Entscheidungen werden **nicht erneut erfragt**.

Rückfrage/Stopp nur bei echtem, irreversiblem Risiko: produktiver Datenverlust /
Live-DB-Restore ohne sichere Grundlage, drohendem Secret-Leak, rechtlich/DSGVO
heiklem Inhalt mit nötiger Menschen-Entscheidung, oder Löschen/Überschreiben von
Daten, die Claude nicht selbst erzeugt hat. Blockierte Punkte dokumentieren, dann
nächsten sicheren Punkt bearbeiten.

## 11. Getroffene Entscheidungen (verbindlich, nicht erneut erfragen)

Diese Punkte sind für den Autopilot abschließend entschieden:

1. **Backend-Akzentfarbe: Magenta (`#db2e87`) als Primärakzent** gemäß BBF-CI und
   Ticket-Plugin. Das bisherige Security-Blau (`#2563eb`) bleibt als *sekundärer*
   Akzent für Status/Badges erhalten. Volle Übernahme der `--bbf-ui-*`-Token-
   Architektur.
2. **MinShopVersion = 5.5.0** (korrigiert am 2026-06-09 von 5.2.0). Gegen echten
   5.7.1-Quellcode verifiziert kompatibel; PHP-8.0-Syntax → abwärtskompatibel bis
   JTL 5.5. 5.2.0 war falsch (PHP 7.4 fehlen `str_contains` & Hook 400). Primäres
   Testziel **5.7.1**. Details: `docs/compat/compatibility-jtl-5.x.md`.
3. **Phase-4-Anbieter-Reihenfolge:** zuerst Produktberater (`bbf_productadvisor`),
   dann AI Concierge, dann Suche. Alle guarded via Feature-Detection, nie harte
   Abhängigkeit.
4. **Such-/Layer-Overlay („Doofinder-artig") ist NICHT Teil dieses Plugins.** Es
   gehört in ein Such-/Empfehlungs-Plugin. BBF Captcha liefert nur die guarded
   Erweiterungs-API (Phase 4) als optionalen Host.

## 7. Commit-/Push-Regeln

- Runtime-Änderung → Version in `info.xml` erhöhen; falls README/CHANGELOG
  existieren, dort nachziehen (das Gate erzwingt Konsistenz, sobald vorhanden).
- Reine Doku-/Tooling-Änderung darf ohne Versionssprung bleiben.
- Vor Push: `git fetch` + bei behind `git pull --rebase origin main`.
- Kein Force-Push. Push nur auf `main` und nur über den SSH-Alias
  `forgejo-bbfdesign` (Port 2222) — siehe `CLAUDE.md`.
- Push nur nach grünem `--local`-Gate.

## 8. Stop-Kriterien

Sofort stabilisieren statt weiterentwickeln, wenn:

- Ein geschütztes Formular von echten Nutzern nicht mehr absendbar ist.
- Login, Registrierung oder Checkout blockiert werden.
- Ein Provider-Ausfall zur harten Sperre statt zum Fail-open führt.
- Ein Secret/Schlüssel im Frontend, Log oder Export auftaucht.
- Neue JS-/Smarty-/SQL-Fehler im Frontend oder Plugin-Backend auftauchen.

## 9. Definition von 10/10

Eine Änderung ist 10/10, wenn sie:

- das konkrete Problem löst.
- keinen funktionierenden Pfad verschlechtert.
- die Absendbarkeit echter Formulare und das Fail-open-Verhalten nachweislich schützt.
- die Schutzwirkung erhält oder verbessert, ohne False-Positive-Last.
- keine Secrets/PII leakt und CSRF/Escaping sowie DSGVO-Grenzen wahrt.
- Hell/Dunkel/Mobile sauber berücksichtigt.
- sinnvoll getestet und (bei Runtime-Änderung) versioniert/dokumentiert ist.
- lokalen Geruch reduziert statt neuen zu erzeugen.
