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

Weitere Roadmap: führender Masterplan `docs/refactor/masterplan.md`
(Phase 0–4: Härtung/Fail-open, Template-/Versionsrobustheit, Backend-Designsystem,
moderne Frontend-Ansicht, guarded Erweiterungs-API).

> Specs werden je Feature unter `features/CAP-NN-<slug>.md` angelegt (`/write-spec`).
