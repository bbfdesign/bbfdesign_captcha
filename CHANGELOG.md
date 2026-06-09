# Changelog

Alle nennenswerten Änderungen an BBF Captcha. Format an [Keep a Changelog]
angelehnt; Versionierung nach SemVer (Pflicht-Gate der Entwicklungssteuerung).

## 1.0.3 – 2026-06-09

### Robustheit (Phase 0.3 des Masterplans)

- **Render-Fail-safe bestätigt + Diagnose ergänzt.** Schlägt das Rendern des
  sichtbaren Captcha-Widgets fehl, bleibt der Schutz erhalten: Honeypot/Timing
  werden über den separaten Output-Filter eingespielt und die serverseitige
  POST-Validierung läuft unabhängig weiter – ein Render-Fehler öffnet das Formular
  also nicht. Der bislang **stille** Render-Fehler wird nun (im `debug_mode`)
  protokolliert; das Logging ist gekapselt und kann den Absende-Hotpath nie stören.

## 1.0.2 – 2026-06-09

### Behoben (Sicherheit/Robustheit, Phase 0.2 des Masterplans)

- **LLM-Zweitprüfung ist jetzt strikt non-blocking.** Eine LLM-Spam-Einstufung
  blockiert ein Absenden nur noch, wenn der lokale Heuristik-Filter bereits ein
  Korroborations-Signal liefert (mindestens „verdächtig", Score ≥ `ai_threshold_ok`).
  Eine LLM-Fehlklassifikation kann damit keinen echten Kunden mehr allein aussperren;
  LLM-Fehler/Timeouts sind weiterhin fail-open (kein Block).
- **Harte Wartezeit-Obergrenze für die LLM-Prüfung.** Das konfigurierbare
  `llm_timeout` ist von max. 60 s auf **max. 10 s** gedeckelt, damit die synchrone
  Prüfung ein echtes Absenden (auch Checkout) nie spürbar blockiert.

> Live-Smoke steht noch aus (`BBF_CAPTCHA_SMOKE_URL` nicht gesetzt). Fail-open
> konstruiert, PHP-Lint grün.

## 1.0.1 – 2026-06-09

### Behoben (Sicherheit/Robustheit, Phase 0 des Masterplans)

- **Fail-open bei Provider-Ausfall (Leitplanke „Kunden nie aussperren").** Ist ein
  externer Captcha-Dienst (Turnstile, reCAPTCHA, hCaptcha, Friendly Captcha) wegen
  Netzwerk-/Timeout-Fehler nicht erreichbar, liefert die Server-Verifizierung jetzt
  Score **0** statt 30 Strafpunkte. Ein Infrastruktur-Ausfall, den der Kunde nicht
  zu verantworten hat, kann damit nicht mehr zum Block eines echten Absendens
  beitragen. Fehlende oder ungültige Tokens (echte Bot-Signale) werden unverändert
  bestraft.

> Hinweis: Live-Smoke gegen einen echten Shop steht noch aus
> (`BBF_CAPTCHA_SMOKE_URL` war nicht gesetzt). Die Änderung ist fail-open
> konstruiert und statisch (PHP-Lint) geprüft.
