#!/usr/bin/env bash
set -euo pipefail

# BBF Captcha – Entwicklungssteuerung (Einstieg)
# Spiegelt die Steuerung aus bbfdesign_tickets, proportional auf dieses
# Schutz-Plugin und auf Claude als Entwickler angepasst.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MODE="--local"

usage() {
  cat <<'USAGE'
Usage:
  tools/development-control.sh [--local|--smoke|--release|--status]

Modes:
  --local    Lokale Entwicklungssteuerung: Versionsabgleich, PHP-Lint, Secret-Scan, Asset-/Template-Sanity.
  --smoke    Live-Smoke gegen die konfigurierte Shop-URL (BBF_CAPTCHA_SMOKE_URL).
  --release  Push auf den Forgejo-Remote nach grünem lokalen Gate. Nur mit sauberem Commit.
  --status   Kurzer Repo-/Versionsstatus.

Die Detail-Gates liegen in tools/dev-cycle.sh.
USAGE
}

current_version() {
  php -r '
  $xml = @simplexml_load_file("info.xml");
  if (!$xml || !isset($xml->Version)) {
      fwrite(STDERR, "Version aus info.xml konnte nicht gelesen werden.\n");
      exit(1);
  }
  echo (string)$xml->Version;
  '
}

section() {
  printf '\n== %s ==\n' "$1"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --local|--smoke|--release|--status)
      MODE="$1"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      printf 'FAIL: Unbekannte Option: %s\n\n' "$1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

VERSION="$(current_version)"

case "$MODE" in
  --status)
    section "Git"
    git status -sb
    git remote -v
    section "Version"
    printf 'Aktuelle Version: %s\n' "$VERSION"
    ;;
  --local)
    bash tools/dev-cycle.sh --expected-version "$VERSION"
    ;;
  --smoke)
    bash tools/dev-cycle.sh --expected-version "$VERSION" --smoke
    ;;
  --release)
    bash tools/dev-cycle.sh --expected-version "$VERSION" --push --smoke
    ;;
esac
