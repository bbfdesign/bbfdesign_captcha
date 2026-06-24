# Feature-Index — BBF Captcha

**Steuerung:** globale Verfassung `~/.claude/entwicklungssteuerung/STEUERUNG.md`
+ lokaler Pointer `features/STEUERUNG.md`. **Führend** bleiben `CLAUDE.md`,
`AGENTS.md`, `docs/claude-development-control.md` und der Masterplan
`docs/refactor/masterplan.md`.

**Stack:** JTL-Shop-5-Plugin (Bootstrap.php, src/, Smarty/Alpine). **ID-Schema:** `CAP-NN`.

## Status-Legende
⬜ Roadmap · 🔵 Planned · 🟣 Architected · 🟡 In Progress · 🔍 In Review · ✅ Approved · 🚀 Deployed

## Gate-Modell (autonom mit 2 harten Gates)
`/write-spec → /architecture → ⛔ GATE 1 → /frontend → /backend → /qa → ⛔ GATE 2 → /deploy`
(Push auf `main` = Auto-Deploy live.)

| ID | Feature | Status | Version | Letzte Phase |
|---|---|---|---|---|
| CAP-01 | Kontakt-/Reg-Spam-Härtung (Gibberish-Token, B2B-Akquise, Unicode-Evasion, Zufalls-Token) | 🚀 Deployed | 1.0.41–1.0.47 | deploy |
| CAP-02 | Widerrufsformular-Schutz (JTL 5.7, PRE_DISPATCH + jtl_hp_input) | 🚀 Deployed | 1.0.45 | deploy |
| CAP-03 | Native JTL-Captcha-Integration (CaptchaServiceInterface, Default AUS) | 🚀 Deployed | 1.0.45 | deploy |
| CAP-04 | Cockpit-Telemetrie (anonymisiert, Default AUS) | 🚀 Deployed | 1.0.48 | deploy |
| CAP-05 | Cockpit-Ruleset anwenden (RemoteRulesetService, Default AUS) | 🚀 Deployed | 1.0.49 | deploy |
| CAP-06 | Ruleset-Interpreter: Fehlalarm-Allowlist (Vorrang) + zentrale IP-/Domain-Blocklist | 🚀 Deployed | 1.0.50 | deploy |
| CAP-07 | Feedback-Loop: Spam-Log-Melde-Buttons (FN „ist Spam"/FP „Fehlalarm") → POST /api/v1/feedback | 🚀 Deployed | 1.0.51 | deploy |
| CAP-08 | Reibungsarmes Opt-in + AVV-Pflicht (Backend-Karte „Zentrale Erkennung": Status, AVV-Häkchen, Default AUS) | 🚀 Deployed | 1.0.52 | deploy |
| CAP-09 | Periodisches Register (Plugin-/Shop-Version, Metadaten-Komfort) | 🚀 Deployed | 1.0.53 | deploy |
| CAP-10 | Settings-UX-Rework: Tabs (Allgemein/Cockpit/Benachrichtigungen/Sicherheit) + Sticky-Speichern + mobiloptimiert | 🚀 Deployed | 1.0.55 | deploy |
| CAP-11 | Cockpit-Auto-Anmeldung (Self-Registration via /api/v1/enroll, holt pro-Shop-Secret, 1-Klick + AVV) | 🚀 Deployed | 1.0.56 | deploy |
| CAP-12 | Auto-Lizenzierung (ForgePush keyless by-domain: checkIfDue in boot, 12h, fail-open, schaltet Schutz nie ab) | 🚀 Deployed | 1.0.56 | deploy |
| CAP-13 | Zero-Touch-Selbstanmeldung: enrollIfDue() im Boot (Key+Endpoint aus Server-Konstante BBFCAPTCHA_ENROLLMENT_SECRET/Default, kein Secret im Repo) | 🚀 Deployed | 1.0.57 | deploy |

Weitere Roadmap: führender Masterplan `docs/refactor/masterplan.md`
(Phase 0–4: Härtung/Fail-open, Template-/Versionsrobustheit, Backend-Designsystem,
moderne Frontend-Ansicht, guarded Erweiterungs-API).

> Specs werden je Feature unter `features/CAP-NN-<slug>.md` angelegt (`/write-spec`).
