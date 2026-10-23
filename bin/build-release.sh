#!/usr/bin/env bash
#
# Builds a ready-to-upload copy of the site for a server with FTP access only (no shell, so no composer or
# bin/console there). Everything that normally runs on the server runs here: production packages, the settings
# (.env.local.php) and compiled CSS/JS. Upload the contents of the output folder over FTP.
#
# Usage: bin/build-release.sh [--with-uploads] [--out=release]
#   --with-uploads  also copy public/uploads (photos and files; needed on the first upload only)
#   --out=…         output folder (default: release)
#
# The production settings come from .env.prod.local (not in git): DATABASE_URL, APP_SECRET, MAILER_DSN,
# DEFAULT_URI and the API keys. bin/check-env.sh --env=prod shows what is needed.
# The code is taken from the working tree, including changes that aren't committed.

set -euo pipefail
cd "$(dirname "$0")/.." || exit 2
# shellcheck source=lib/deploy.sh
source bin/lib/deploy.sh

APP_ENV=prod
WITH_UPLOADS=0
OUT=release
for arg in "$@"; do
    case "$arg" in
        --with-uploads) WITH_UPLOADS=1 ;;
        --out=*) OUT="${arg#--out=}" ;;
        -h|--help) awk 'NR > 2 && /^#/ { sub(/^# ?/, ""); print; next } NR > 2 { exit }' "$0"; exit 0 ;;
        *) fail "Unknown option: $arg (see --help)" ;;
    esac
done
export APP_ENV
[ -n "$OUT" ] && [ "$OUT" != . ] && [ "$OUT" != / ] || fail "Choose another --out folder."

[ -f .env.prod.local ] || fail "No .env.prod.local with the production settings. Create it (see bin/check-env.sh --env=prod), then run this again."
command -v rsync >/dev/null || fail "rsync is not installed (sudo apt install rsync)."
START=$(date +%s)
printf '%sBuilding a release of Verzeilberg.nl%s into %s\n' "$BOLD" "$RESET" "$OUT/"

step "Code"
# Hidden files (.git, .env.local, editor folders) stay out, except .env. vendor/ is excluded, so the previous
# build's packages stay and composer only installs what changed
excludes=(
    --include=/.env --exclude='/.*' --exclude="/$OUT/"
    --exclude=/var/ --exclude=/vendor/ --exclude=/tests/ --exclude=/.phpunit.cache/
    --exclude=/public/assets/ --exclude=/public/bundles/ --exclude=/public/share-images/
    --exclude='/compose*.yaml' --exclude=/cookies.txt --exclude='/phpunit*' --exclude=/phpstan.neon
)
[ "$WITH_UPLOADS" = 1 ] || excludes+=(--exclude=/public/uploads/)
mkdir -p "$OUT"
rsync -a --delete "${excludes[@]}" ./ "$OUT/"
cp .env.prod.local "$OUT/.env.prod.local"
ok "Copied the working tree"
cd "$OUT"

step "Packages"
find_composer
composer_install

step "Settings"
"${COMPOSER[@]}" dump-env prod >/dev/null 2>&1 || fail "composer dump-env prod failed."
rm -f .env.prod.local
bash bin/check-env.sh --env=prod --no-color > /dev/null 2>&1 || warn "Required settings are missing; see: bin/check-env.sh --env=prod"
ok ".env.local.php written (holds passwords: never put it in git or share it)"

step "CSS and JavaScript"
build_assets

step "Writable folders"
# The server builds its own cache on the first request; a cache built here would hold this machine's paths
rm -rf var
mkdir -p var/cache var/log var/storage var/share public/share-images
[ "$WITH_UPLOADS" = 1 ] || mkdir -p public/uploads/blog/images public/uploads/blog/files public/uploads/events/events public/uploads/avatars
ok "Empty var/ and public/share-images"

printf '\n%s✔ Built in %ss%s: %s (%s)\n' "$GREEN$BOLD" "$(( $(date +%s) - START ))" "$RESET" "$OUT/" "$(du -sh . | cut -f1)"
cat <<EOF

    Upload over FTP:
      - the contents of $OUT/ into the site folder (the one that holds public/)
      - vendor/ only when composer.lock changed; public/uploads/ only on the first upload (--with-uploads)
    Then on the server (FTP):
      - delete var/cache/prod/, so the new code is used
      - files removed here are not removed there; delete those by hand (or use a sync tool)
    Writable for the web server: var/ (and everything in it), public/uploads/, public/share-images/
EOF
