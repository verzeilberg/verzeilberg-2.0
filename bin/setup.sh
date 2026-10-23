#!/usr/bin/env bash
#
# First installation of the site on a server (or a new development machine), from a copy of the code.
# Safe to run again: every step checks what is already there.
#
# Usage: bin/setup.sh [--env=prod|dev] [--web-user=www-data] [--yes] [--skip-admin]
#   --env=…       prod (default) or dev
#   --web-user=…  the user PHP-FPM runs as (default: detected, usually www-data)
#   --yes         don't ask; use the defaults (the settings must already be in .env.local)
#   --skip-admin  don't create an administrator account
#
# For an admin account without questions: ADMIN_EMAIL, ADMIN_NAME and ADMIN_PASSWORD in the environment.
# Run it as the user that owns the code (not as root), from anywhere.

set -euo pipefail
cd "$(dirname "$0")/.." || exit 2
# shellcheck source=lib/deploy.sh
source bin/lib/deploy.sh

APP_ENV=prod
ASSUME_YES=0
SKIP_ADMIN=0
WEB_USER=''
for arg in "$@"; do
    case "$arg" in
        --env=prod|--env=dev) APP_ENV="${arg#--env=}" ;;
        --env=*) fail "Use --env=prod or --env=dev." ;;
        --web-user=*) WEB_USER="${arg#--web-user=}" ;;
        --yes|-y) ASSUME_YES=1 ;;
        --skip-admin) SKIP_ADMIN=1 ;;
        -h|--help) awk 'NR > 2 && /^#/ { sub(/^# ?/, ""); print; next } NR > 2 { exit }' "$0"; exit 0 ;;
        *) fail "Unknown option: $arg (see --help)" ;;
    esac
done
export APP_ENV
detect_web_user
mkdir -p var

printf '%sSetting up Verzeilberg.nl%s in %s (%s), web server user %s\n' "$BOLD" "$RESET" "$(pwd)" "$APP_ENV" "$WEB_USER"
[ "$(id -u)" = 0 ] && warn "Running as root: files will be handed to $WEB_USER at the end. Better: run as the user that owns the code."

step "Requirements"
check_php
find_composer
command -v git >/dev/null && ok "Git $(git --version | awk '{print $3}')" || warn "Git is not installed (only needed if you update with git)"

step "Settings (.env.local)"
if [ ! -f .env.local ]; then
    info "No .env.local yet: creating it. Press Enter to skip a question and fill it in later."
    ask() { local answer=''; [ "$ASSUME_YES" = 1 ] || read -r -p "    $1: " answer; printf '%s' "$answer"; }
    database_url="$(ask 'Database (mysql://USER:PASSWORD@HOST:3306/DATABASE?serverVersion=8.0.32&charset=utf8mb4)')"
    default_uri="$(ask 'Public address of the site (e.g. https://verzeilberg.nl)')"
    mailer_dsn="$(ask 'Mail server (e.g. smtp://USER:PASSWORD@smtp.example.com:587)')"
    {
        echo "# Real settings of this server. NOT in git. Every setting is explained in .env;"
        echo "# bin/check-env.sh shows what is missing and how to get it."
        echo "APP_ENV=$APP_ENV"
        echo "APP_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')"
        [ -n "$database_url" ] && echo "DATABASE_URL=\"$database_url\""
        [ -n "$default_uri" ] && echo "DEFAULT_URI=$default_uri"
        [ -n "$mailer_dsn" ] && echo "MAILER_DSN=$mailer_dsn"
        echo
        echo "# Optional features (empty = switched off); see bin/check-env.sh"
        echo "GOOGLE_MAPS_API_KEY="
        echo "GOOGLE_CLIENT_ID="
        echo "GOOGLE_CLIENT_SECRET="
        echo "STRAVA_CLIENT_ID="
        echo "STRAVA_CLIENT_SECRET="
        echo "STRAVA_REDIRECT_URI="
        echo "STRAVA_ATHLETE_ID="
        echo "STRAVA_GOOGLE_MAPS_KEY="
        echo "STEAM_API_KEY="
        echo "STEAM_ID="
        echo "HANDBAL_PUBLIC_PASSWORD="
    } > .env.local
    chmod 600 .env.local
    ok "Created .env.local with a new random APP_SECRET"
else
    ok ".env.local exists (not changed)"
    grep -q '^APP_ENV=' .env.local || warn "Add APP_ENV=$APP_ENV to .env.local, otherwise the site runs as development"
fi

step "Packages"
composer_install

step "Settings check"
if ! bin/check-env.sh --env="$APP_ENV" --no-color > var/check-env.txt 2>&1; then
    grep -E '✘|!' var/check-env.txt | sed 's/^/    /'
    fail "Required settings are missing or wrong (above). Fill them in .env.local; bin/check-env.sh --env=$APP_ENV explains how. Then run this script again."
fi
grep -E '○|!' var/check-env.txt | sed 's/^/    /' || true
ok "Required settings are filled in (○ = optional feature switched off)"

step "Folders and permissions"
fix_permissions

step "Database"
console doctrine:database:create --if-not-exists >/dev/null || fail "Could not create or reach the database: check DATABASE_URL in .env.local and that the database server runs."
ok "Database exists"
run_migrations

step "Administrator"
if [ "$SKIP_ADMIN" = 1 ]; then
    info "Skipped (--skip-admin)"
elif [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    console app:user:create-admin --no-interaction --email="$ADMIN_EMAIL" --name="${ADMIN_NAME:-Administrator}" --password-from-env=ADMIN_PASSWORD
elif [ "$ASSUME_YES" = 0 ] && confirm "Create an administrator account now?" y; then
    console app:user:create-admin
else
    info "Later: php bin/console app:user:create-admin"
fi

step "CSS and JavaScript"
build_assets

step "Cache"
clear_cache
fix_permissions >/dev/null

step "Done"
ok "Verzeilberg.nl is set up ($APP_ENV)"
cat <<EOF
    Next:
    - Point the web server's document root to $(pwd)/public
      (nginx: try_files \$uri /index.php\$is_args\$args;  Apache: composer require symfony/apache-pack)
    - Optional features: bin/check-env.sh --env=$APP_ENV shows what is still switched off and how to get the keys
    - Updates later: bin/update.sh --env=$APP_ENV
EOF
print_cron
