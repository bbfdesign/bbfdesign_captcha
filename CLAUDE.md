# BBF Captcha – Claude Entwicklungssteuerung

Dieses Repo ist ein Live-Plugin für den JTL-Shop. **BBF Captcha** schützt
Shop-Formulare (Kontakt, Login, Registrierung, Bewertung, Newsletter, ggf.
Checkout) gegen Bots und Spam — über mehrere Captcha-Provider (Altcha,
Turnstile, Friendly, reCAPTCHA, hCaptcha), Honeypot, Timing, Rate-Limit,
IP-Listen, Bot-Erkennung und eine optionale LLM-Zweitprüfung. Claude entwickelt
hier ausschließlich über die projektinterne Entwicklungssteuerung. Die
ausführliche Fassung steht in `docs/claude-development-control.md`.

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

## Autonomer Blockmodus

Bei „weiter", „arbeite", „lege los" o. ä. arbeitet Claude die offene Queue
selbstständig ab. Rückfragen nur bei: irreversibler externer Aktion, einem
Secret/Schlüssel, rechtlichem/DSGVO-Inhalt oder einer Live-DB-Aktion ohne
sichere Grundlage. Blockierte Punkte dokumentieren, dann den nächsten sicheren
Punkt bearbeiten.

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
