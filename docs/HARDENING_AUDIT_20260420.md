# Hardening-, Cleanup- und Performance-Audit

**Plugin:** `bbfdesign_captcha`
**Datum:** 2026-04-20
**Branch:** `chore/hardening-audit-20260420`
**Ziel:** Settings-Integritaet, Wachstumskontrolle der Content-Tabellen, Hotpath-Optimierung aller Queries, die bei einem Formular-Submit durchlaufen werden.

---

## Zusammenfassung

Sieben Phasen in jeweils eigenem Commit. Keine Breaking Changes an der
oeffentlichen PHP-API oder an der Smarty-Integration. Alle Migrationen
idempotent (information_schema-Checks vor ALTER TABLE).

**Kritischster Fund:** `bbf_captcha_rate_limits` hatte nur einen INDEX
auf `(ip_address, form_type, window_start)` statt UNIQUE. Der Upsert im
Code nutzt `INSERT ... ON DUPLICATE KEY UPDATE`, erkennt den Konflikt aber
nie — jeder Request hat eine neue Zeile erzeugt. Tabelle wuchs unbegrenzt,
das Rate-Limiting war effektiv wirkungslos. Phase 2 fixt das.

---

## Phase 1 — Settings-Integritaet

Commit: `fix(settings): unique key + upsert + filtered load (align with bbfdesign baseline)`

### Ist-Zustand
- Tabelle `bbf_captcha_settings`
- Spalte `setting_key VARCHAR(100) NOT NULL`
- `PRIMARY KEY (setting_key)` — funktional aequivalent zu `UNIQUE KEY uq_setting`
- `Setting::set()` nutzt `ON DUPLICATE KEY UPDATE` (PK-safe)
- Migrationen nutzen `INSERT IGNORE` (PK-safe)
- Keine Duplikate moeglich

### Aenderungen
- **Neue Migration `Migration20260420120000`**: verifiziert idempotent, dass
  eine `UNIQUE`-Property (PK oder UNIQUE) auf `setting_key` existiert. Nur
  falls beides fehlt, wird dedupliziert und `uq_setting` angelegt. Fuer
  dieses Plugin: No-Op.
- **`Setting::getMany(array $keys)`**: neuer gefilterter Loader fuer
  Hot-Path-Queries. Laedt nur die benoetigten Keys, cached das Ergebnis,
  vermeidet `SELECT *` pro Formular-Submit.

### Nicht geaendert
- Spaltennamen / Typen (kein Rename auf `setting VARCHAR(191)`) — waere
  Breaking Change, funktional aequivalent.
- `Setting::loadAll()` bleibt, wird aber nicht mehr als einziger Pfad
  verwendet.

---

## Phase 2 — Hot-Path Indexes

Commit: `perf(captcha): add missing hot-path indexes on content tables`

### Kritischer Bugfix
- `bbf_captcha_rate_limits`: `UNIQUE KEY uq_rate_bucket (ip_address, form_type, window_start)` angelegt.
- Der alte redundante `idx_lookup` auf denselben Spalten wurde entfernt.
- **Migration dedupliziert vor dem `ALTER TABLE`**:
  `MAX(id)` pro Bucket wird behalten, `request_count` wird mit
  `SUM(request_count)` der Gruppe ueberschrieben, alle uebrigen Zeilen
  geloescht.

### Neue Composite-Indizes
- `bbf_captcha_spam_log.idx_form_created (form_type, created_at)` — fuer
  `getTopForms`, `getSpamHistory`.
- `bbf_captcha_spam_log.idx_action_created (action_taken, created_at)` —
  fuer `getKPIs`, `getTrend`.
- `bbf_captcha_ip_entries.idx_type_ip (entry_type, ip_address)` — fuer den
  Blacklist-Lookup im Hot-Path.

### Bereits vorhanden (kein Handlungsbedarf)
- `api_keys.idx_hash (key_hash)` — schneller SHA256-Lookup.
- `spam_words.uk_word (word)` — verhindert Learn-Dupes.
- `disposable_domains.uk_domain (domain)`.
- `ip_entries.idx_expires`, `idx_ip`, `idx_type`.
- `spam_log.idx_created`, `idx_ip`, `idx_form`, `idx_method`.

---

## Phase 3 — Retention

Commit: `feat(captcha): retention policies for log/rate-limit/ip tables`

### Neuer Service
`src/Services/RetentionService.php`:
- `cleanRateLimits()` — alles aelter als 1 Stunde weg, `LIMIT 1000`
- `cleanSpamLog()` — nach `log_retention_days` (Default 30), `LIMIT 5000`,
  plus harte Obergrenze `spam_log_max_rows` (Default 100 000) als Notbremse
- `cleanExpiredIps()` — abgelaufene Blacklist-Eintraege physisch weg, `LIMIT 1000`
- `maybeRun()` — 60s-Gate, Timestamp in Setting `retention_last_run`
- `runAll()` — ungateter manueller Aufruf
- `getTableSizes()` — fuer Dashboard-Widget

### Integration
- `FormProtection::handleFormHook()` ruft `maybeRun()` nach jedem POST.
  Im `try/catch` — Retention darf nie den Formular-Flow brechen.
- `RateLimitService::pseudoCleanup()` bekommt `LIMIT 1000`.
- `IPEntry::cleanupExpired()` bekommt `LIMIT 1000`.

### Neue Settings (Migration20260420122000)
- `spam_log_max_rows` (Default: `100000`)
- `retention_last_run`
- `retention_last_run_stats` (JSON)

### Admin-Actions
- `getRetentionStatus` — aktuelle Tabellengroessen + letzter Run
- `runRetention` — manueller Cleanup

---

## Phase 4 — Hotpath-Caching

Commit: `perf(captcha): in-memory cache for ip/spamword/domain lookups`

### IPService
- Prozess-weiter Static-Cache mit exakten Blacklist/Whitelist-IPs
  (Assoc-Array, O(1)-Lookup) und CIDR-Ranges (Array-Scan, in PHP gematcht).
- Invalidation per `IPService::invalidateCache()` nach jedem Write:
  - `blockIp()`, `unblockIp()`, `whitelistIp()`, `checkAutoBlock()`
  - `AdminController::blockIp/unblockIp/addIpEntry/deleteIpEntry`
  - `RateLimit/Retention`-Flows betreffen die Tabelle nicht
- CIDR-Matching passiert in `PluginHelper::ipInCidr()` gegen den Cache.

### AISpamService
- Spam-Wortliste als Static-Cache mit pre-lowercased-Worten.
- Invalidation nach `addSpamWord`, `deleteSpamWord`, `learnFromFeedback`.
- Disposable-Domains waren bereits gecacht.

### Nicht gecacht (per Definition volatile)
- `rate_limits` — wird bei jedem Request geschrieben.
- `spam_log` — write-heavy, Cache waere sinnlos.

### Settings-Cache
- Bereits im Model (`Setting::$cache`) — bleibt unveraendert.

---

## Phase 5 — Asset-Loading

Commit: `perf(captcha): load frontend assets only on pages with forms`

### Form-Detection verschaerft
- Vorher: `stripos($html, '<form') === false` — matchte auch Such- und
  Filter-Formulare (GET).
- Jetzt: 2-stufiger Check (stripos-Shortcut + Regex auf `method="post"`).
  GET-Formulare triggern keinen Asset-Load mehr.

### Bereits umgesetzt (nur dokumentiert)
- Externe Captcha-Scripts (Turnstile, reCAPTCHA, hCaptcha, Friendly)
  werden nur ueber den Consent-Manager geladen.
- `frontend/js/bbfdesign-captcha.js` laedt sie erst bei Form-Fokus bzw.
  via `IntersectionObserver` im Scroll-in-View.
- ALTCHA (self-hosted) wird weiterhin beim Form-Detect injiziert —
  Web-Component braucht das JS vor dem Render, um als Custom Element
  zu registrieren.

---

## Phase 6 — API Security

Commit: `fix(captcha-api): timing-safe key lookup, deferred last-used updates`

### `ApiKey::validateKey()`
- **Timing-Jitter** (50–150 ms) im Miss-Pfad: versteckt die
  Response-Zeit-Differenz zwischen "Key existiert nicht" und "Key existiert,
  Hash stimmt nicht".
- **`last_used_at` deferred**: Update nur noch alle 60 s pro Key, statt
  bei jedem Request. Verhindert Write-Storm auf Hochlast-APIs.

### `CaptchaAPIController::handleRequest()`
- Wrap in `try/catch` — unerwartete Exceptions (z.B. DB-Outage) liefern
  einheitlichen 500er, kein Stacktrace-Leak.
- Fehler werden ins Shop-Log geschrieben, nicht an den Client.

### Rate-Limit
- Der Bucket-Upsert in `CaptchaAPIController::checkRateLimit()` nutzt
  jetzt die neue `UNIQUE KEY` aus Phase 2 und inkrementiert wirklich.
  **Bemerkung:** Vor dem Fix war API-Rate-Limiting ebenfalls wirkungslos.

---

## Phase 7 — Verifikation

### Erwartete SQL-Checks nach Deploy

```sql
-- Duplikate in Settings?  (erwartet: 0 Zeilen)
SELECT setting_key, COUNT(*) c
  FROM bbf_captcha_settings
  GROUP BY setting_key HAVING c > 1;

-- Unique-Property auf setting_key?  (erwartet: PRIMARY oder uq_setting)
SHOW INDEX FROM bbf_captcha_settings
  WHERE Key_name IN ('PRIMARY', 'uq_setting');

-- Rate-Limit-UNIQUE vorhanden?  (erwartet: 1 Zeile mit Non_unique=0)
SHOW INDEX FROM bbf_captcha_rate_limits
  WHERE Key_name = 'uq_rate_bucket';

-- Composite-Indexes auf spam_log?
SHOW INDEX FROM bbf_captcha_spam_log
  WHERE Key_name IN ('idx_form_created', 'idx_action_created');

-- Composite-Index auf ip_entries?
SHOW INDEX FROM bbf_captcha_ip_entries
  WHERE Key_name = 'idx_type_ip';

-- Tabellengroessen (sollten nach Retention-Run moderat bleiben)
SELECT table_name, table_rows, data_length+index_length AS size_bytes
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name LIKE 'bbf_captcha_%';
```

### Smoke-Tests (manuell)
1. Kontaktformular absenden (Happy Path) — kein Log-Eintrag, Weiterleitung
   zur Erfolgsseite.
2. Kontaktformular mit Spam-Wort absenden — Log-Eintrag, Form-Error.
3. Rate-Limit ueberschreiten — 10 Requests in 5 Min, 11. wird geblockt
   (nach Phase-2-Fix wirksam).
4. API: `POST /bbfdesign-captcha/api/v1/validate` mit gueltigem und
   ungueltigem Key — Response-Zeiten messen (Miss sollte ~100 ms durch
   Jitter dauern).
5. Nach 1 h Betrieb: `SELECT COUNT(*) FROM bbf_captcha_rate_limits` —
   sollte nicht unbegrenzt steigen.

### Benchmarks (vor/nach)
Noch nicht durchgefuehrt — bitte nach Deploy auf Staging messen:
- TTFB auf `/kontakt`, `/registrieren`, `/newsletter` mit leerem Formular
- TTFB auf `/` (Startseite ohne POST-Formular) — sollte nach Phase 5
  *sinken*, da keine Assets mehr geladen werden.
- API-Validate-Endpoint p95-Latency.

### PHP-Syntax
`php -l` auf allen geaenderten Dateien — lokal war keine PHP-Binary
verfuegbar, muss im Deploy-Pipeline passieren.

---

## Dateien

### Neu
- `Migrations/Migration20260420120000.php` (Settings-Guard)
- `Migrations/Migration20260420121000.php` (Hot-Path-Indexes)
- `Migrations/Migration20260420122000.php` (Retention-Settings)
- `src/Services/RetentionService.php`
- `docs/HARDENING_AUDIT_20260420.md`

### Geaendert
- `src/Models/Setting.php` (getMany)
- `src/Models/IPEntry.php` (LIMIT)
- `src/Models/ApiKey.php` (timing jitter, deferred last_used_at)
- `src/Services/IPService.php` (Static-Cache)
- `src/Services/AISpamService.php` (Spam-Words-Cache)
- `src/Services/RateLimitService.php` (LIMIT)
- `src/Hooks/FormProtection.php` (Retention-Pseudo-Cron)
- `src/Hooks/IncludeAssets.php` (POST-Form-Check)
- `src/Controllers/Admin/AdminController.php` (Retention-Actions, Cache-Invalidation)
- `src/Controllers/API/CaptchaAPIController.php` (try/catch)

---

## Regeln, die eingehalten wurden

- ✅ Keine Breaking Changes an der oeffentlichen PHP-API oder Smarty-Funktion
- ✅ Alle Migrationen idempotent (information_schema-Checks)
- ✅ Retention-Policies konservativ + via Setting konfigurierbar
- ✅ Pseudo-Cron nicht-blockierend (LIMIT + try/catch)
- ✅ Keine neuen externen Dependencies
- ✅ Branch erstellt, kein Direct-Push auf `main`
- ✅ UI / Primaerfarbe `#2563eb` unveraendert
