# BBF Captcha ↔ CaptchaCockpit – Plugin-Integration

> Plugin-Seite der zentralen Erkennung. Gegenstück: `captchacockpit.bbfdesign.de`
> (Konzept: `~/captchacockpit/docs/KONZEPT.md`, Vertrag: `…/docs/API-CONTRACT.md`).
> Grundsatz: **Default AUS, fail-open/fail-safe, DSGVO-minimiert** – das Plugin
> bleibt ohne Cockpit voll funktionsfähig.

## Komponenten

### 1. `CockpitTelemetryService` (umgesetzt, Inkrement 1)
- Liest neue `bbf_captcha_spam_log`-Zeilen ab Cursor (`cockpit_cursor_id`).
- Mappt sie **anonymisiert** auf das Ingest-Format (keine PII im Klartext):
  - `ipHash` = `hash_hmac('sha256', ip, pepper)` (Pepper = `cockpit_pepper`, lazy erzeugt, serverseitig)
  - `contentFp` = `sha256(normalisierter Inhalt)`; `contentShape` = `{len, upperRatio, transitions, digits, urls}`
  - `emailDomain` = nur Domain-Teil; **kein** Name/Freitext/volle E-Mail
  - `action` ∈ BLOCKED|LOGGED|ALLOWED, `score`, `detectionMethod`, `reasons`
  - optional `reviewSnippet` nur bei `cockpit_review_enabled=1` und nur für
    BLOCKED|LOGGED; max. 180 Zeichen, lokal redigiert via `CockpitReviewRedactor`
  - zum Snippet wird `reviewMeta` mit `redacted=true`, Quelle `plugin`,
    Redaction-Version, nicht-sensiblen Quellfeldern und entfernten PII-Kategorien
    gesendet
- Sendet HMAC-signierte Batches (≤500) an `POST {endpoint}/api/v1/ingest`.
- **Fail-open:** jeder Fehler/Timeout → kein Throw; Cursor wird nur bei Erfolg
  vorgerückt (Retry beim nächsten Lauf). Läuft gedrosselt über den nativen Cron.
- Gated: nur wenn `cockpit_enabled` + `cockpit_endpoint` + `cockpit_secret` gesetzt.

### 2. `RemoteRulesetService` (umgesetzt, Inkrement 2 – v1.0.49, Firewall-Policy v1.0.69)
- `GET {endpoint}/api/v1/ruleset?since=<v>`; Integrität per HMAC verifiziert
  (Header `X-Ruleset-Signature` über den Rohbody, Shop-Secret); lokal gecacht;
  gedrosselt (stündlich) im Boot-/Cron-Pfad.
- **Interpreter** wendet deklarative Felder an (kein Remote-Code), je mit
  Sicherheits-Klammer gegen Fehl-Rulesets:
  `tokenHeuristics` → `checkRandomGibberish` (minLen ≥ 8 / Wechsel ≥ 2 / Upper 0–1);
  `thresholds` → `CaptchaService::getFormConfig` (20–200);
  `blockedEmailDomains` + `phrases` → `AISpamService::checkRulesetLists` (Phrase ≤ 60).
- **fail-safe:** Cockpit nicht erreichbar / Signatur ungültig → letztes gültiges
  Ruleset bzw. Defaults bleiben aktiv. Greift nur bei aktiver Cockpit-Integration.
- `ipBlocklistUrl` wird signiert gezogen und als zentrale Blocklist gecacht.
- `firewallPolicyUrl` wird signiert gezogen und als Full-Snapshot gecacht:
  `ALLOW` übersteuert zentrale Blocks, `BLOCK` wirkt als Sperrgrund, `WATCH`
  blockiert nicht hart. Der Cache wird vollständig ersetzt, damit entfernte
  Cockpit-Reputationen lokal verschwinden.
- Bei Login-/Admin-Flächen (`login`, `password_reset`, `wp_login`, `wp_admin`,
  `jtl_admin`) erhöht `WATCH` den lokalen Score weich und erzeugt einen
  nachvollziehbaren Log-Grund. Kontakt-/Lead-Formulare werden dadurch nicht
  verschärft.
- Damit wirken neue zentrale Erkenntnisse **ohne Plugin-Update**.

### 3. `AuthFirewallService` (umgesetzt – v1.0.71)
- Zählt Login-, Passwort-Reset-, WordPress-Admin- und JTL-Admin-Versuche lokal
  in `bbf_captcha_rate_limits` mit getrennten `authfw:*`-Keys.
- Standardmodus ist `monitor`: Wiederholungsmuster und Cockpit-WATCH werden
  gescored und ab Schwelle als Log sichtbar, blockieren aber echte Logins nicht.
- Modus `enforce` ist ein bewusstes Betreiber-Setting im Backend. Erst dann setzt
  das Plugin nach Grenzwertüberschreitung eine temporäre IP-Sperre via
  `IPEntry::autoBlock`.
- Grenzwerte aus der signierten Cockpit-Firewall-Policy werden innerhalb harter
  Sicherheitsklammern übernommen:
  - Versuche: 3..100
  - Zeitfenster: 60..3600 Sekunden
  - Sperrdauer: 60..86400 Sekunden
- Fail-open: Fehler in Policy, Counter oder Persistenz dürfen keinen Login
  blockieren.

## Settings (Default AUS)
| Key | Default | Zweck |
|---|---|---|
| `cockpit_enabled` | `0` | Master-Schalter Telemetrie/Ruleset |
| `cockpit_endpoint` | `''` | z. B. `https://captchacockpit.bbfdesign.de` |
| `cockpit_secret` | `''` | Shared-Secret (write-only, nie ins Frontend) |
| `cockpit_share_ip_prefix` | `0` | opt-in: zusätzlich anonymisiertes /24-/48-Prefix senden |
| `cockpit_review_enabled` | `0` | opt-in: redigierte Review-Vorschau für Quarantäne-Listen senden |
| `cockpit_auth_firewall_mode` | `monitor` | Login/Admin-Firewall: `off`, `monitor`, `enforce` |
| `cockpit_auth_firewall_max_attempts` | `8` | lokaler Fallback-Grenzwert für Login/Admin-Versuche |
| `cockpit_auth_firewall_window_seconds` | `300` | lokales Fallback-Zeitfenster für Login/Admin-Firewall |
| `cockpit_auth_firewall_lockout_seconds` | `900` | lokale Fallback-Sperrdauer im Modus `enforce` |
| `cockpit_pepper` | auto | serverseitiger HMAC-Pepper für `ipHash` |
| `cockpit_cursor_id` | `0` | zuletzt gesendete spam_log-id |
| `cockpit_ruleset_version` | `0` | zuletzt angewandte Ruleset-Version (Inkr. 2) |
| `cockpit_firewall_policy_cache` | auto | signierter Policy-Full-Snapshot aus dem Cockpit |

Backend-Karte „Zentrale Erkennung (Cockpit)" unter Einstellungen; `cockpit_secret`
wird wie das ForgePush-/LLM-Secret aus `settingsJson` gefiltert (kein Frontend-Leak).

## Auth
`instanceId` = `LicenseService::instanceId()` (bereits vorhanden). Signatur:
`HMAC_SHA256(rawBody + "|" + signedAt, cockpit_secret)` → Header `X-Cockpit-Instance`,
`X-Signed-At`, `X-Signature` (siehe API-CONTRACT §1).

## DSGVO
Aktivierung nur durch Betreiber nach AVV. Es verlassen den Shop ausschließlich
pseudonyme/aggregierte Merkmale (siehe `~/captchacockpit/docs/DSGVO.md`). Bestehende
Plugin-IP-Anonymisierung (`log_ip_anonymize`) bleibt unberührt; das Cockpit
bekommt ohnehin nur `ipHash`.

Der Review-Modus ist separat opt-in: Dedizierte Namens-, E-Mail-, Telefon-,
Adress-, Token- und Passwortfelder werden nicht übernommen; typische PII-Muster
werden maskiert. Details und Cockpit-Anforderungen siehe
`docs/cockpit-review-workflow-2026-08-21.md`.

Die Login/Admin-Firewall überträgt keine zusätzlichen Klartextdaten ans Cockpit.
Die Wiederholungszählung bleibt lokal im Shop; zentral wirken nur pseudonyme
Policy-Buckets (`ALLOW`, `WATCH`, `BLOCK`) und bereits bestehende Telemetrie.
