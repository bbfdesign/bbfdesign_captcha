# Entwicklungssteuerung — BBF Captcha (lokaler Pointer)

Dieses Projekt arbeitet nach der **globalen Verfassung**
`~/.claude/entwicklungssteuerung/STEUERUNG.md` (Gate-Modell, Status, Stack-Erkennung,
Deploy-Rezepte, Live-Sicherheit). Jeder Phasen-Command liest sie zuerst.

- **Stack:** JTL-Shop-5-Plugin (Bootstrap.php/Hooks, src/Controllers·Services, Smarty/Alpine).
- **ID-Schema:** `CAP-NN`. Index: `features/INDEX.md`. Spec-Vorlage: `features/_TEMPLATE.md`.
- **Gate-Modell:** autonom; **⛔ GATE 1** Design-Freigabe nach `/architecture`,
  **⛔ GATE 2** Live-Freigabe vor `/deploy` (Push auf `main` = Auto-Deploy live).
- **Führend (Tech-Wahrheit):** `CLAUDE.md`, `AGENTS.md`,
  `docs/claude-development-control.md`, Masterplan `docs/refactor/masterplan.md`.
  Gates: `tools/development-control.sh --local|--smoke|--release`.
- **Plugin-Besonderheiten** (nicht hier duplizieren — stehen in CLAUDE.md):
  fail-open, Secret-Scan, Versions-Bump bei Template-Änderung, idempotenter Self-Heal.
