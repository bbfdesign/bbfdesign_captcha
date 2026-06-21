# BBF Captcha – Claude Entwicklungssteuerung

Dieses Repo ist ein Live-Plugin für den JTL-Shop. **BBF Captcha** schützt
Shop-Formulare (Kontakt, Login, Registrierung, Bewertung, Newsletter, ggf.
Checkout) gegen Bots und Spam — über mehrere Captcha-Provider (Altcha,
Turnstile, Friendly, reCAPTCHA, hCaptcha), Honeypot, Timing, Rate-Limit,
IP-Listen, Bot-Erkennung und eine optionale LLM-Zweitprüfung. Claude entwickelt
hier ausschließlich über die projektinterne Entwicklungssteuerung. Die
ausführliche Fassung steht in `docs/claude-development-control.md`.

## Entwicklungssteuerung (Verfassung)

Gemeinsame Grundlage: **globale Verfassung**
`~/.claude/entwicklungssteuerung/STEUERUNG.md` + lokaler Pointer
`features/STEUERUNG.md`. Diese CLAUDE.md, `docs/…` und der Masterplan
`docs/refactor/masterplan.md` bleiben **führend**. 6-Bot-Workflow
(`/write-spec → /architecture → /frontend → /backend → /qa → /deploy`), autonom
mit 2 harten Gates (Design nach `/architecture`, Live vor `/deploy`). Feature-Status:
`features/INDEX.md` (ID-Schema `CAP-NN`), Spec-Vorlage `features/_TEMPLATE.md`.

## Nicht verhandelbar

- **Echte Kunden dürfen nie ausgesperrt werden.** Jeder Schutz muss für legitime
  Nutzer durchlässig bleiben — ein Schutz, der Login/Registrierung/Checkout
  blockiert, ist ein Live-Vorfall.
- **Fail-open als Grundhaltung:** Fällt ein externer Verifizierungs-Provider oder
  die LLM-Prüfung aus oder läuft in den Timeout, wird das Formular nicht hart
  blockiert, sondern auf den nächsten Schutz/Logeintrag zurückgefallen. Härtere
  Modi nur bewusst, dokumentiert und getestet.
- **Keine Secrets im Browser, Log, Template, Export oder Mail.** Provider-Secrets
  (Secret-Keys) und LLM-/API-Schlüssel bleiben serverseitig. Im Frontend nur
  öffentliche Sitekeys.
- Keine Änderung an Formular-Interception, Provider-Verifizierung oder
  Bot-Scoring ohne Vorher-/Nachher-Test (echtes Absenden + Bot-Simulation).
- Externe Calls (Provider-Verify, LLM) immer mit Timeout; nie unbegrenzt im
  Absende-Hotpath blockieren.
- DSGVO: IP-Adressen und Spam-Logs sind personenbezogen — nur zweckgebunden,
  mit Aufbewahrungsgrenze speichern, nie ungefiltert exportieren.
- Admin-AJAX/API nur mit gültigem CSRF-Token. Kein XSS: Nutzereingaben in
  Templates sauber escapen.
- Keine `DROP TABLE`, keine Tabellenumbenennung, keine Pflichtspalte ohne
  Default. Migrationen idempotent.
- Neue Provider/Schutzmodule bleiben deaktiviert, bis getestet und freigegeben.
- Deutsche Texte verwenden echte Umlaute. Keine fremden Pluginnamen in Code/Doku.

## Standardablauf

1. Arbeitsstand prüfen:

   ```bash
   git status -sb
   git remote -v
   ```

2. Relevanten Kontext lesen:

   - `docs/claude-development-control.md` (vollständige Steuerung)

3. Änderung klein schneiden, Code lesen, dann ändern.
4. Entwicklungssteuerung ausführen:

   ```bash
   bash tools/development-control.sh --local
   ```

5. Bei Frontend-/Formular-Nähe zusätzlich Live-Smoke (Shop-URL setzen):

   ```bash
   BBF_CAPTCHA_SMOKE_URL="https://<shop>/<formularseite>" \
     bash tools/development-control.sh --smoke
   ```

6. Nur bei grünem Stand committen und pushen (`--release`).

## Autonomer Blockmodus (Autopilot)

Standardbetrieb ist autark. Bei „weiter", „arbeite", „lege los", „mach autonom"
o. ä. — und generell, solange eine offene Queue existiert — arbeitet Claude den
führenden Masterplan (`docs/refactor/masterplan.md`) selbstständig Punkt für Punkt
ab: lesen → ändern → `--local`-Gate → bei grünem Stand **eigenständig committen
und pushen** (`bash tools/development-control.sh --release`), ohne Rückfrage.
Smoke: ist `BBF_CAPTCHA_SMOKE_URL` gesetzt, läuft `--smoke` mit; sonst wird der
ausstehende Live-Smoke im Commit/Doku vermerkt und weitergearbeitet (kein Blocker).

Bereits im Masterplan getroffene Entscheidungen werden **nicht erneut erfragt**
(siehe dort „Getroffene Entscheidungen").

**Pflicht: Hotpath-Änderungen sind fail-open konstruiert** — ein Fehler darf nur
zu *weniger* Schutz führen, niemals dazu, dass echte Kunden ausgesperrt werden.
Dadurch ist autonomes Pushen solcher Änderungen sicher gegenüber der obersten Regel.

Rückfrage/Stopp nur bei echtem, irreversiblem Risiko:
- produktiver Datenverlust / Live-DB-Restore ohne sichere Grundlage,
- ein Secret/Schlüssel würde geleakt,
- rechtlich/DSGVO heikler Inhalt, der eine menschliche Entscheidung braucht,
- Löschen/Überschreiben von Daten, die Claude nicht selbst erzeugt hat.

Blockierte Punkte werden dokumentiert, danach der nächste sichere Punkt bearbeitet.

## Git-Remote (wichtig)

`origin` muss auf den SSH-Alias zeigen — der Forgejo-Server hört SSH auf
**Port 2222**, Port 22 ist zu und HTTPS hat keinen Credential-Helper:

```
origin → forgejo-bbfdesign:biggitboss/bbfdesign_captcha.git
```

Der Alias liegt in `~/.ssh/config` (`Host forgejo-bbfdesign`, Port 2222,
Key `~/.ssh/forgejo_bbfdesign`). Auth-Test:
`ssh forgejo-bbfdesign` muss „Hi there, biggitboss! ..." zeigen.
Falls `git remote -v` abweicht:
`git remote set-url origin forgejo-bbfdesign:biggitboss/bbfdesign_captcha.git`

## Pflicht bei sichtbaren Änderungen

- Hell- und Dunkelmodus prüfen, mobile Darstellung prüfen.
- Captcha-Widget, Buttons, Backend-Kacheln und Formulare folgen der BBF-Plugin-CI.
- Das Widget darf das geschützte Formular nicht verschieben oder abschneiden;
  keine Hover-Effekte, die Layout springen lassen.

## Stop-Kriterien (sofort stabilisieren)

- Ein geschütztes Formular lässt sich von echten Nutzern nicht mehr absenden.
- Login, Registrierung oder Checkout werden blockiert.
- Ein Provider-Ausfall führt zur harten Sperre statt zum Fail-open.
- Ein Secret/Schlüssel taucht im Frontend, Log oder Export auf.
- Neue JS-/Smarty-/SQL-Fehler im Frontend oder im Plugin-Backend.
