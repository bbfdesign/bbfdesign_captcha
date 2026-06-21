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
- Sendet HMAC-signierte Batches (≤500) an `POST {endpoint}/api/v1/ingest`.
- **Fail-open:** jeder Fehler/Timeout → kein Throw; Cursor wird nur bei Erfolg
  vorgerückt (Retry beim nächsten Lauf). Läuft gedrosselt über den nativen Cron.
- Gated: nur wenn `cockpit_enabled` + `cockpit_endpoint` + `cockpit_secret` gesetzt.

### 2. `RemoteRulesetService` (umgesetzt, Inkrement 2 – v1.0.49)
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
- `ipBlocklist` → `IPEntry`: **offen** (Folge-Inkrement).
- Damit wirken neue zentrale Erkenntnisse **ohne Plugin-Update**.

## Settings (Default AUS)
| Key | Default | Zweck |
|---|---|---|
| `cockpit_enabled` | `0` | Master-Schalter Telemetrie/Ruleset |
| `cockpit_endpoint` | `''` | z. B. `https://captchacockpit.bbfdesign.de` |
| `cockpit_secret` | `''` | Shared-Secret (write-only, nie ins Frontend) |
| `cockpit_share_ip_prefix` | `0` | opt-in: zusätzlich anonymisiertes /24-/48-Prefix senden |
| `cockpit_pepper` | auto | serverseitiger HMAC-Pepper für `ipHash` |
| `cockpit_cursor_id` | `0` | zuletzt gesendete spam_log-id |
| `cockpit_ruleset_version` | `0` | zuletzt angewandte Ruleset-Version (Inkr. 2) |

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
