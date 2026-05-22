#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

find . \
  -path './vendor' -prune -o \
  -path './frontend/js/vendor' -prune -o \
  -name '*.php' -print0 \
  | xargs -0 -n1 php -l >/dev/null

for template in adminmenu/templates/index.tpl adminmenu/templates/api.tpl; do
  awk '
    /<script>/ { in_script = 1 }
    /<\/script>/ { in_script = 0; in_literal = 0 }
    in_script && /{literal}/ { in_literal = 1; next }
    in_script && /{\/literal}/ { in_literal = 0; next }
    in_script && in_literal { print }
  ' "$template" | node --check >/dev/null
done

grep -q 'uk_bbf_captcha_rate_bucket' Migrations/Migration20260522113000.php
grep -q 'uk_bbf_captcha_api_key_hash' Migrations/Migration20260522113000.php
grep -q 'ON DUPLICATE KEY UPDATE `request_count` = `request_count` + 1' src/Services/RateLimitService.php
grep -q 'extractPathParameters' src/Controllers/API/CaptchaAPIController.php

echo 'quality gate passed'
