#!/usr/bin/env bash
set -euo pipefail

# BBF Captcha – Detail-Gates der Entwicklungssteuerung.
# Abgeleitet aus bbfdesign_tickets (Codex), proportional auf dieses
# Schutz-Plugin und auf Claude als Entwickler angepasst. Schwerpunkte hier:
# Sekret-Dichtheit (Provider- und LLM-Schlüssel), DSGVO/IP-Sensibilität und
# das Frontend-Asset, das auf jeder geschützten Seite ausgeliefert wird.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

EXPECTED_VERSION=""
PUSH=0
SMOKE=0
SMOKE_URL="${BBF_CAPTCHA_SMOKE_URL:-}"
EXPECTED_ORIGIN="${BBF_CAPTCHA_ORIGIN_URL:-forgejo-bbfdesign:biggitboss/bbfdesign_captcha.git}"
REF_REMOTE="${BBF_CAPTCHA_REF_REMOTE:-origin}"
REF_BRANCH="${BBF_CAPTCHA_REF_BRANCH:-main}"

usage() {
  cat <<'USAGE'
Usage:
  tools/dev-cycle.sh [--expected-version X.Y.Z] [--push] [--smoke]

Standard:
  Lokale Checks: Versionsabgleich, PHP-Lint, Secret-Scan, Asset-/Template-Sanity.
  Keine Git-Aktion.

Optionen:
  --expected-version X.Y.Z  Erwartete Version. Ohne Wert wird info.xml gelesen.
  --push                    Fetch + Rebase + Push auf den konfigurierten Remote/Branch.
  --smoke                   Live-Smoke gegen BBF_CAPTCHA_SMOKE_URL.

Env:
  BBF_CAPTCHA_REF_REMOTE    Standard: origin
  BBF_CAPTCHA_REF_BRANCH    Standard: main
  BBF_CAPTCHA_ORIGIN_URL    Standard: Forgejo-Remote (SSH-Alias forgejo-bbfdesign)
  BBF_CAPTCHA_SMOKE_URL     Shop-URL einer Seite mit geschütztem Formular (z. B. Kontakt/Login)
USAGE
}

section() { printf '\n== %s ==\n' "$1"; }
fail()    { printf 'FAIL: %s\n' "$1" >&2; exit 1; }
ok()      { printf 'OK:   %s\n' "$1"; }

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

verify_versions() {
  local info_version
  info_version="$(current_version)"
  printf 'info.xml:  %s\n' "$info_version"
  printf 'Erwartet:  %s\n' "$EXPECTED_VERSION"
  [[ "$info_version" == "$EXPECTED_VERSION" ]] \
    || fail "info.xml-Version weicht von der erwarteten Version ab."

  # README/CHANGELOG sind optional. Wenn vorhanden, muss die Version stehen.
  if [[ -f README.md ]] && ! grep -Fq "$EXPECTED_VERSION" README.md; then
    fail "README.md vorhanden, aber ohne Version $EXPECTED_VERSION."
  fi
  if [[ -f CHANGELOG.md ]] && ! grep -Fq "$EXPECTED_VERSION" CHANGELOG.md; then
    fail "CHANGELOG.md vorhanden, aber ohne Eintrag für Version $EXPECTED_VERSION."
  fi
  ok "Versionsabgleich"
}

php_lint() {
  local errors=0
  while IFS= read -r file; do
    if ! php -l "$file" >/dev/null 2>&1; then
      printf 'PHP-Syntaxfehler: %s\n' "$file" >&2
      php -l "$file" >&2 || true
      errors=1
    fi
  done < <(find Bootstrap.php src Migrations -name '*.php' -type f 2>/dev/null)
  [[ "$errors" == "0" ]] || fail "PHP-Lint fehlgeschlagen."
  ok "PHP-Lint (Bootstrap.php + src/ + Migrations/)"
}

# Kernschärfung dieses Plugins: Provider-Secrets (reCAPTCHA/hCaptcha/Turnstile/
# Friendly/Altcha) und LLM-/API-Schlüssel dürfen niemals in browserseitig
# ausgelieferten Dateien landen. Sitekeys sind öffentlich und daher erlaubt.
# Geprüft werden nur die wirklich ausgelieferten Frontend-Dateien (ohne vendor).
secret_scan() {
  local hits=0
  local pattern='secret[_-]?key|api[_-]?secret|private[_-]?key|client[_-]?secret|->secret|\bsecret\b\s*[:=]|sk_live|sk_test|[A-Za-z0-9]{0,4}_secret'
  while IFS= read -r file; do
    if grep -Eniq "$pattern" "$file" 2>/dev/null; then
      printf 'Mögliches Secret in ausgeliefertem Asset: %s\n' "$file" >&2
      grep -Eni "$pattern" "$file" >&2 | head -5
      hits=1
    fi
  done < <(find frontend -type f \( -name '*.js' -o -name '*.css' -o -name '*.tpl' \) \
             -not -path '*/vendor/*' 2>/dev/null)
  [[ "$hits" == "0" ]] || fail "Secret-Scan: verdächtige Schlüssel in Frontend-Assets."
  ok "Secret-Scan (keine Provider-/LLM-Schlüssel im Frontend)"
}

asset_sanity() {
  local required=(
    "info.xml"
    "Bootstrap.php"
    "frontend/js/bbfdesign-captcha.js"
    "frontend/css/bbfdesign-captcha.css"
    "frontend/template/captcha_widget.tpl"
  )
  local f missing=0
  for f in "${required[@]}"; do
    [[ -f "$f" ]] || { printf 'Fehlende Datei: %s\n' "$f" >&2; missing=1; }
  done
  [[ "$missing" == "0" ]] || fail "Pflicht-Assets fehlen."

  # Leichte Template-Sanity: ausgeglichene Smarty-Blöcke der häufigsten Tags.
  # Mehrzeilen-fähig (perl), damit über mehrere Zeilen umbrochene {if ...} zählen.
  local tpl
  while IFS= read -r tpl; do
    for tag in if foreach block; do
      local open close
      open="$(perl -0777 -ne "BEGIN{\$/=undef} \$c=()=/\\{$tag(?=[\\s}])/g; print \$c" "$tpl")"
      close="$(perl -0777 -ne "BEGIN{\$/=undef} \$c=()=/\\{\\/$tag\\}/g; print \$c" "$tpl")"
      if [[ "$open" != "$close" ]]; then
        printf 'Unausgeglichene {%s}-Blöcke in %s (offen=%s, zu=%s)\n' "$tag" "$tpl" "$open" "$close" >&2
        fail "Template-Sanity fehlgeschlagen."
      fi
    done
  done < <(find frontend/template adminmenu/templates -name '*.tpl' -type f 2>/dev/null)
  ok "Asset-/Template-Sanity"
}

require_main_origin() {
  local branch origin_url
  branch="$(git rev-parse --abbrev-ref HEAD)"
  origin_url="$(git remote get-url "$REF_REMOTE")"
  [[ "$branch" == "$REF_BRANCH" ]] \
    || fail "Push ist nur auf $REF_BRANCH erlaubt, aktuell: $branch"
  [[ "$origin_url" == "$EXPECTED_ORIGIN" ]] \
    || fail "$REF_REMOTE zeigt nicht auf $EXPECTED_ORIGIN (aktuell: $origin_url)"
  ok "Remote/Branch"
}

require_clean_tree() {
  if ! git diff --quiet || ! git diff --cached --quiet; then
    git status -sb >&2
    fail "Push nur mit sauberem getrackten Arbeitsstand. Bitte erst committen."
  fi
  ok "Sauberer Arbeitsstand"
}

run_smoke() {
  if [[ -z "$SMOKE_URL" ]]; then
    printf 'SKIP: Live-Smoke übersprungen — BBF_CAPTCHA_SMOKE_URL nicht gesetzt.\n'
    return 0
  fi
  printf 'Prüfe %s ...\n' "$SMOKE_URL"
  local code
  code="$(curl --connect-timeout 8 --max-time 30 -s -o /dev/null -w '%{http_code}' "$SMOKE_URL" || echo "000")"
  [[ "$code" == "200" ]] || fail "Smoke: HTTP $code von $SMOKE_URL"
  # Marker, dass der Schutz auf der Seite überhaupt ausgeliefert wird.
  if ! curl --connect-timeout 8 --max-time 30 -fsS "$SMOKE_URL" | grep -qiE "bbfdesign-captcha|bbfdesign_captcha"; then
    fail "Smoke: Seite lädt, aber Captcha-/Schutz-Asset wird nicht referenziert."
  fi
  ok "Live-Smoke (HTTP 200 + Schutz-Asset)"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --expected-version)
      [[ $# -ge 2 && "${2:-}" != "" && "${2:-}" != --* ]] \
        || fail "--expected-version braucht eine Version, z. B. 1.0.0."
      EXPECTED_VERSION="${2:-}"; shift 2 ;;
    --push)  PUSH=1; shift ;;
    --smoke) SMOKE=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) printf 'FAIL: Unbekannte Option: %s\n\n' "$1" >&2; usage >&2; exit 1 ;;
  esac
done

[[ -n "$EXPECTED_VERSION" ]] || EXPECTED_VERSION="$(current_version)"

section "Arbeitsstand"
git status -sb

section "Versionsabgleich"
verify_versions

section "PHP-Lint"
php_lint

section "Secret-Scan"
secret_scan

section "Asset-/Template-Sanity"
asset_sanity

if [[ "$PUSH" == "1" ]]; then
  section "Git Push"
  require_main_origin
  require_clean_tree
  git fetch "$REF_REMOTE"
  git pull --rebase "$REF_REMOTE" "$REF_BRANCH"
  verify_versions
  git push "$REF_REMOTE" "$REF_BRANCH"
  ok "Push auf $REF_REMOTE/$REF_BRANCH"
fi

if [[ "$SMOKE" == "1" ]]; then
  section "Live Smoke"
  run_smoke
fi

section "Fertig"
printf 'Entwicklungssteuerung für Version %s abgeschlossen.\n' "$EXPECTED_VERSION"
