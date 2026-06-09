# Changelog

Alle nennenswerten Änderungen an BBF Captcha. Format an [Keep a Changelog]
angelehnt; Versionierung nach SemVer (Pflicht-Gate der Entwicklungssteuerung).

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
