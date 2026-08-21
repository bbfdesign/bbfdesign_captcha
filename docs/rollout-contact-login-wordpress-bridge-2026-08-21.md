# Rollout-Abgleich: Kontakt/Login, WordPress-Brücke, Cockpit-Review

Datum: 2026-08-21  
Status: Plugin geprüft, keine Live-Änderung ausgelöst

## Korrektur der Lage

Die Aussage, dass Spam "in jeder zweiten Bestellung" vorkomme, wird für diesen
Task nicht als bestätigter bbfdesign.de-Produktionsbefund behandelt. Checkout und
Bestellungen bleiben hier nur als vorhandene bzw. spätere Plattformabdeckung
erwähnt. Der konkrete Fokus ist:

- Kontakt- und Login-Signale
- WordPress-Brücke für bbfdesign.de
- Cockpit-Review-Snippet-Vertrag
- Version und Rollout-Nachweis

## JTL-Plugin-Stand

Repository: `bbfdesign_captcha`  
Aktueller veröffentlichter Stand im Repo: `1.0.66`  
Commit: `6947b36 Align Cockpit review meta contract`  
Tag: `v1.0.66`

Die Plugin-Version wird im JTL-Plugin aus `info.xml` und zur Cockpit-Registrierung
über `getCurrentVersion()` gelesen:

- `info.xml` enthält `1.0.66`
- `CockpitTelemetryService::registerIfDue()` sendet `pluginVersion`
- `CockpitEnrollService::instancePayload()` sendet `pluginVersion`
- `CaptchaAPIController` setzt `X-BBF-Captcha-Version`

Wenn CaptchaCockpit für `bbfdesign.de` `0.2.0` meldet, ist das nach aktuellem
Befund kein Hinweis auf diesen JTL-Pluginstand.

## Kontakt/Login im JTL-Plugin

Kontakt:

- `HOOK_KONTAKT_PAGE` stellt nur Widget/Assets bereit.
- `HOOK_KONTAKT_PAGE_PLAUSI` validiert vor dem Versand.
- Bei Spam setzt das Plugin JTLs Honeypot-Marker, damit der Kontaktversand sauber
  übersprungen wird.
- Fehler im Schutzpfad sind fail-open.

Registrierung/Login:

- Registrierung wird über `HOOK_REGISTRIEREN_PAGE_REGISTRIEREN_PLAUSI` vor der
  Kontoanlage blockierbar validiert.
- Login ist in der Formularabdeckung vorhanden und standardmäßig fail-open auf
  `log`, damit echte Kunden nicht ausgesperrt werden.
- Cockpit-Telemetrie entsteht aus dem lokalen Spam-Log, nicht aus Klartextdaten.

## Cockpit-Review-Snippet-Vertrag

Der Pluginstand `1.0.66` erfüllt den Cockpit-Vertrag:

- `reviewSnippet` wird nur gesendet, wenn `cockpit_review_enabled=1`.
- `reviewSnippet` wird nur für `BLOCKED|LOGGED` ergänzt.
- Das Snippet ist auf 180 Zeichen limitiert.
- `reviewMeta` wird gesendet mit:
  - `redacted=true`
  - `source=plugin`
  - `redactionVersion=pii-redaction-v1`
  - nicht-sensiblen `fields`
  - entfernten PII-Kategorien in `piiRemoved`
  - `maxChars=180`
- Dedizierte Namens-, E-Mail-, Telefon-, Adress-, Firmen-, Token-, Passwort-,
  Kunden- und Bestelldatenfelder werden nicht als Snippet-Quelle verwendet und
  erscheinen nicht in `reviewMeta.fields`.

Standard-Ingest bleibt DSGVO-minimiert:

- `ipHash`
- `contentFp`
- `contentShape`
- `emailDomain`
- `reasons`
- `score`
- `action`
- `formType`

## WordPress-Brücke bbfdesign.de

Öffentliche Prüfung von `https://www.bbfdesign.de/` zeigt WordPress-Merkmale:

- `wp-json`
- `wp-content`
- WordPress-REST-Namespaces

Im JTL-Plugin-Repo gibt es keine WordPress-Brücke. Lokaler Befund:

- Cockpit-Doku: `/Users/bjornalexanderbiner/Documents/Captcha Cockpit/docs/WORDPRESS-BBF-INTEGRATION.md`
- Live-Backup-Hinweis:
  `/Users/bjornalexanderbiner/Documents/BBF Website Relaunch/audit/live-backups/captchacockpit-bridge-20260807-1912/bbfdesign-captchacockpit-bridge.php.before-0.2.0`
- Diese Backup-Datei ist eine separate WordPress-MU-Plugin-Bridge und trägt
  `Version: 0.1.0`; sie sendet im gelesenen Stand nur `wp_login_failed` als
  `formType=login`.

Schlussfolgerung:

- Eine Cockpit-Meldung `0.2.0` für `bbfdesign.de` gehört sehr wahrscheinlich zur
  separaten WordPress-Brücke oder einem anderen WordPress-Artefakt.
- Sie ist kein Nachweis, dass das JTL-Plugin `bbfdesign_captcha` falsch versioniert
  oder ausgerollt ist.

## Erforderlicher Rollout-Nachweis für WordPress

Keine Live-Änderung ohne separaten Deploy-Nachweis. Für die WordPress-Brücke ist
ein eigener Rollout erforderlich:

1. Quellartefakt der aktuellen MU-Bridge ermitteln.
2. Version der Bridge in der Plugin-Datei eindeutig erhöhen.
3. Kontaktformular-Signale ergänzen, falls bbfdesign.de-Kontaktformulare aktuell
   noch nicht angebunden sind.
4. Review-Snippet-Vertrag analog zum JTL-Plugin übernehmen:
   `reviewSnippet` + `reviewMeta`, nur opt-in, lokal redigiert, kein Klartext.
5. Smoke gegen CaptchaCockpit:
   - Login-Fehlversuch erzeugt Event `formType=login`
   - kontrollierte Kontaktprobe erzeugt Event `formType=contact` oder
     `contact_modal`
   - Cockpit-Shop zeigt aktualisierte letzte Meldung
   - Version entspricht der Bridge-Version, nicht der JTL-Plugin-Version
6. Deploy-Nachweis ablegen:
   - Artefakt/Commit
   - Upload-Zeitpunkt
   - Hash oder Dateigröße
   - Smoke-Screenshot/Log
   - Rollback-Datei

## Checkout/Bestellung

Checkout ist im JTL-Plugin als vorhandene Formularfähigkeit konfiguriert, aber
bewusst fail-open bzw. standardmäßig auf `log`, um echte Kunden nicht hart aus dem
Bestellprozess auszuschließen. Für eine spätere Plattformabdeckung kann der
Cockpit-Review-Vertrag auf `checkout`/`order` erweitert werden; das ist hier kein
bestätigter bbfdesign.de-Produktionsvorfall.
