# BBF Captcha — Produktrahmen (PRD)

Umfassendes Anti-Spam-/Bot-Schutz-Plugin für JTL-Shop 5 (Honeypot, Timing,
ALTCHA, externe Captchas, Smart-Spamfilter, optionale LLM-Zweitprüfung, IP-/
Rate-Management). Schützt Kontakt, Registrierung, Login, Bewertung, Newsletter,
Widerruf u. a. Neu: Anbindung an das zentrale **CaptchaCockpit** (Telemetrie +
zentrale Regeln ohne Plugin-Update).

**Führende Quellen (Tech-Wahrheit):** `CLAUDE.md`, `AGENTS.md`,
`docs/claude-development-control.md`, Masterplan `docs/refactor/masterplan.md`,
`docs/COCKPIT-INTEGRATION.md`.

**Steuerung:** globale Verfassung `~/.claude/entwicklungssteuerung/STEUERUNG.md`
+ `features/STEUERUNG.md`. Feature-Status: `features/INDEX.md` (ID-Schema `CAP-NN`).

**Oberste Regel:** echte Kunden dürfen nie ausgesperrt werden (fail-open). Roadmap
und Detail im Masterplan; abgeschlossene/laufende Features in `features/INDEX.md`.
