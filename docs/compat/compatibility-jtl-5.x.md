# BBF Captcha – JTL-Shop-Kompatibilität

Stand: 2026-09-18 · gegen echten Quellcode von **JTL Shop 5.8.0** verifiziert
(`/Users/bjornalexanderbiner/Downloads/shop-v5-8-0 2`) und weiterhin auf den
bereits geprüften 5.7-Verträgen gehalten.

## Ergebnis

- **JTL 5.8.0: voll kompatibel** — quellcode-verifiziert, keine Runtime-Code-Änderung nötig.
- **JTL 5.7.x: weiterhin kompatibel** — die genutzten Hook-/SDK-Verträge bleiben
  identisch zum bereits geprüften 5.7.1-Stand.
- **Boden: JTL 5.5.0** (`MinShopVersion`). Begründet aus PHP-Syntax (reines PHP 8.0)
  und der seit JTL 5.0 stabilen Plugin-SDK. 5.5/5.6 sind nicht quellcode-verifiziert,
  aber durch die genutzten APIs gedeckt.
- Frühere `MinShopVersion 5.2.0` war falsch: 5.2 läuft auf PHP 7.4, dort fehlen
  `str_contains`/`str_starts_with` → das Plugin hätte dort fatal versagt.

## Verifizierte Hooks (alle in 5.8.0 vorhanden)

| Hook | Wert | Zweck |
|---|---|---|
| HOOK_SMARTY_OUTPUTFILTER | 140 | Honeypot/Timing + Asset-Injection |
| HOOK_KONTAKT_PAGE | 29 | Kontaktformular |
| HOOK_REGISTRIEREN_PAGE | 40 | Registrierung |
| HOOK_NEWSLETTER_PAGE | 36 | Newsletter |
| HOOK_BEWERTUNG_INC_SPEICHERBEWERTUNG | 78 | Produktbewertung |
| HOOK_BESTELLVORGANG_PAGE | 19 | Checkout |
| HOOK_KUNDE_CLASS_HOLLOGINKUNDE | 145 | Login |
| HOOK_WUNSCHLISTE_CLASS_FUEGEEIN | 127 | Wunschliste |
| HOOK_JTL_PAGE | 23 | Passwort-Reset (Feld-Erkennung) |
| HOOK_ROUTER_PRE_DISPATCH | 400 | API-Routen `/bbfdesign-captcha/api/*` + Altcha-Challenge |
| CONSENT_MANAGER_GET_ACTIVE_ITEMS | 320 | Consent-Integration (in 5.7.1 **Core**) |

## Verifizierte SDK-Klassen/Methoden (5.8.0 / 5.7-Vertrag)

- `JTL\Plugin\Bootstrapper` (boot/installed/getPlugin/renderAdminMenuTab) ✓
- `JTL\Events\Dispatcher::listen(array|string, callable, int)` ✓ (Plugin nutzt String-Eventnamen)
- `JTL\Shop::isFrontend()`, `::getURL()`, `::Container()` ✓
- Container `getDB(): DbInterface`, `getLogService(): LoggerInterface` ✓
- `PluginInterface::getPluginID()`, `getCurrentVersion()`, `getPaths()`, `getLocalization()` ✓
- `Paths::getFrontendURL/getAdminPath/getAdminURL/getBackendURL` ✓
- `Localization::getTranslation(string, ?string)` ✓
- `DbInterface::queryPrepared(...)` ✓
- `JTL\Plugin\Migration` + `JTL\Update\IMigration` (Plugin-Migrationen: nur `up()/down()`,
  Rest liefert die Basisklasse via MigrationTrait) ✓

## PHP-Kompatibilität

- Plugin nutzt **nur PHP-8.0-Syntax**: `str_contains`, `str_starts_with`,
  `str_ends_with`, Null-Coalescing, Konstruktor-Property-Promotion-frei.
- **Keine** PHP-8.1+-Features (keine Enums, kein `readonly`, kein `never`, keine
  First-Class-Callables, keine Intersection-Types).
- → lauffähig ab **PHP 8.0** = JTL **5.5**.

| JTL-Version | PHP-Minimum | Status |
|---|---|---|
| 5.8.0 | 8.1 | **verifiziert** (Quellcode, lokaler Shop-Stand) |
| 5.7.1 | 8.1 | **verifiziert** (Quellcode) |
| 5.6.x | 8.1 | abgedeckt (API + PHP) |
| 5.5.x | 8.0 | abgedeckt (API + PHP-8.0-Syntax) — Boden |
| ≤ 5.4 | 7.4 | **nicht** unterstützt (PHP-8.0-Funktionen fehlen) |

## 5.8-spezifische Prüfpunkte

- `JTL\Smarty\JTLSmarty::outputFilter()` baut weiterhin ein phpQuery-Dokument
  und ruft `HOOK_SMARTY_OUTPUTFILTER` mit `['document' => $doc]` auf. Das Plugin
  unterstützt diesen Pfad über `IncludeAssets::injectIntoDocument()` und
  `SmartyOutputFilter::filterDocument()`.
- `ContactController` führt `HOOK_KONTAKT_PAGE_PLAUSI` weiterhin vor
  `Form::honeypotWasFilledOut($_POST)` und `Form::editMessage()` aus. Die
  saubere Spam-Unterbindung über JTLs Honeypot-Marker bleibt damit kompatibel.
- Registrierung und Bewertungen behalten die Hooks
  `HOOK_REGISTRIEREN_PAGE_REGISTRIEREN_PLAUSI` und
  `HOOK_BEWERTUNG_INC_SPEICHERBEWERTUNG`.
- Das Frontend-Asset-Performance-Gate bleibt funktional sicher: sichtbare
  Widgets und vorgemerkte theme-unabhängige ALTCHA-Widgets erzwingen Assets,
  reine Formularseiten ohne CAPTCHA laden die großen Dateien nicht.

## Offen / Empfehlung

- 5.5/5.6 sind aus API- und PHP-Stabilität abgeleitet, nicht quellcode-verifiziert.
  Wer 5.5/5.6 produktiv betreibt, sollte einen kurzen Install-/Smoke-Test auf
  einer solchen Instanz fahren.
- Primäres Entwicklungs- und Testziel ist **5.8.0**; **5.7.x** bleibt explizit
  kompatibel und darf nicht durch 5.8-spezifische Annahmen gebrochen werden.
