# Codex-Rollensteuerung

Dieses Repo nutzt die vorhandene Entwicklungssteuerung plus eine Codex-Rollenrunde
für autonome Arbeitsblöcke. Die Rollen sind Prüfperspektiven; Integration und
finale Verantwortung bleiben im Hauptarbeitsfluss.

## Rollen

- **Lead:** priorisiert Risiken, hält die Queue klein und entscheidet die Reihenfolge.
- **Dev:** setzt Änderungen entlang bestehender Plugin-Patterns um.
- **Kritiker:** sucht Geruch, Regressionen, Theme-/JTL-Kantenfälle und blinde Gates.
- **UX:** gleicht Backend-Shell, Komponenten und Dichte mit dem BBF-v2-Prototyp ab.
- **Security:** prüft Secrets, CSRF, XSS/CSS, DSGVO, externe Calls und Fail-open.
- **QA:** führt `bash tools/development-control.sh --local` aus und benennt offene Smokes.

## Ablauf

1. Lead liest `CLAUDE.md`, `docs/claude-development-control.md`,
   `docs/refactor/masterplan.md` und relevante Handoff-Dateien.
2. Dev setzt den kleinsten sicheren Schnitt um.
3. Kritiker, UX, Security und QA prüfen parallel oder direkt nacheinander.
4. Findings mit P0/P1 werden vor UI-Politur integriert.
5. QA-Gate ist mindestens `bash tools/development-control.sh --local`; bei
   Formular-/Frontend-Hotpath zusätzlich `--smoke`, falls `BBF_CAPTCHA_SMOKE_URL`
   gesetzt ist.

## Aktueller Referenzstandard

Backend-Optik folgt der BBF-v2-Schicht aus dem Prototyp:

- `adminmenu/css/tokens/*.css` als Token-Quelle.
- `adminmenu/css/admin-v2.css` als scoped Komponenten- und Shell-Layer.
- Dunkle Sidebar, 3px-CI-Toplinie, 64px Topbar, kompakte Cards, 40px Controls,
  Status-Badges mit 4px Radius und mobile Tabellenkarten.
