#!/usr/bin/env bash
#
# Updates an installed site to the current code: new code (git pull), packages, database migrations,
# CSS/JS, cache and permissions. Run it after every deploy; running it twice does no harm.
#
# Usage: bin/update.sh [--env=prod|dev] [--no-pull] [--backup] [--reload-fpm] [--web-user=www-data]
#   --env=…       prod (default) or dev
#   --no-pull     don't run git pull (e.g. when the code was copied with rsync)
#   --backup      dump the database to var/backups/ before migrating (needs mysqldump)
#   --reload-fpm  reload PHP-FPM afterwards (sudo), so its opcache has the new code
#   --web-user=…  the user PHP-FPM runs as (default: detected, usually www-data)

set -euo pipefail
cd "$(dirname "$0")/.." || exit 2
# shellcheck source=lib/deploy.sh
source bin/lib/deploy.sh

APP_ENV=prod
PULL=1
BACKUP=0
RELOAD_FPM=0
WEB_USER=''
for arg in "$@"; do
    case "$arg" in
        --env=prod|--env=dev) APP_ENV="${arg#--env=}" ;;
        --env=*) fail "Use --env=prod or --env=dev." ;;
        --no-pull) PULL=0 ;;
        --backup) BACKUP=1 ;;
        --reload-fpm) RELOAD_FPM=1 ;;
        --web-user=*) WEB_USER="${arg#--web-user=}" ;;
        -h|--help) awk 'NR > 2 && /^#/ { sub(/^# ?/, ""); print; next } NR > 2 { exit }' "$0"; exit 0 ;;
        *) fail "Unknown option: $arg (see --help)" ;;
    esac
done
export APP_ENV
detect_web_user
mkdir -p var

# One update at a time
exec 9>var/update.lock
flock -n 9 || fail "Another update is running."

[ -f .env.local ] || [ -f .env.local.php ] || fail "No .env.local: this site isn't set up yet. Run bin/setup.sh --env=$APP_ENV first."
START=$(date +%s)
printf '%sUpdating Verzeilberg.nl%s in %s (%s)\n' "$BOLD" "$RESET" "$(pwd)" "$APP_ENV"

step "Code"
if [ "$PULL" = 0 ]; then
    info "Not pulled (--no-pull)"
elif [ ! -d .git ] || [ -z "$(git remote 2>/dev/null)" ]; then
    info "No git remote: update the code yourself (e.g. rsync), or add one with git remote add origin <url>"
else
    # Local changes to tracked files would be mixed with the new code; new untracked files are fine
    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
        git status --short --untracked-files=no | sed 's/^/    /'
        fail "There are local changes to the code (above). Commit or undo them (git stash), or use --no-pull."
    fi
    before="$(git rev-parse HEAD)"
    git pull --ff-only || fail "git pull failed (the branch has diverged?). Fix it by hand, then run this again."
    after="$(git rev-parse HEAD)"
    if [ "$before" = "$after" ]; then
        ok "Already the newest code ($(git rev-parse --short HEAD))"
    else
        git log --oneline "$before..$after" | sed 's/^/    /'
        ok "Updated to $(git rev-parse --short HEAD)"
    fi
fi

step "Packages"
find_composer
composer_install

step "Settings check"
if ! bin/check-env.sh --env="$APP_ENV" --no-color > var/check-env.txt 2>&1; then
    grep -E '✘' var/check-env.txt | sed 's/^/    /'
    fail "Required settings are missing or wrong (above); bin/check-env.sh --env=$APP_ENV explains how to fix them. The site's code is updated, but the database and cache are not yet."
fi
grep -E '^\s*! ' var/check-env.txt | sed 's/^ */    /' || true
ok "Required settings are filled in"

step "Database"
if console doctrine:migrations:up-to-date >/dev/null 2>&1; then
    ok "Database is up to date"
else
    console doctrine:migrations:list 2>/dev/null | grep -i 'not migrated' | sed 's/^/    /' || true
    if [ "$BACKUP" = 1 ]; then
        command -v mysqldump >/dev/null || fail "--backup needs mysqldump (sudo apt install mysql-client or mariadb-client)."
        mkdir -p var/backups
        file="var/backups/before-update-$(date +%Y%m%d-%H%M%S).sql.gz"
        # The connection from DATABASE_URL, as the site sees it; the password goes through MYSQL_PWD, not the command line
        eval "$(APP_ENV="$APP_ENV" php -r '
            require "vendor/autoload.php";
            (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
            $u = parse_url((string) ($_SERVER["DATABASE_URL"] ?? ""));
            foreach (["host" => "DB_HOST", "port" => "DB_PORT", "user" => "DB_USER", "pass" => "DB_PASS", "path" => "DB_NAME"] as $k => $v) {
                $value = $k === "path" ? ltrim((string) ($u[$k] ?? ""), "/") : (string) ($u[$k] ?? "");
                echo $v, "=", escapeshellarg(urldecode($value)), "\n";
            }')"
        MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --no-tablespaces -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USER" "$DB_NAME" | gzip > "$file" \
            || fail "The backup failed; nothing was migrated."
        chmod 600 "$file"
        ok "Backup: $file"
    fi
    run_migrations
fi

step "CSS and JavaScript"
build_assets

step "Cache"
clear_cache

step "Permissions"
fix_permissions

if [ "$RELOAD_FPM" = 1 ]; then
    step "PHP-FPM"
    service="$(systemctl list-units --type=service --no-legend 2>/dev/null | awk '/php.*fpm/ { print $1; exit }')"
    if [ -n "$service" ] && sudo -n systemctl reload "$service" 2>/dev/null; then
        ok "Reloaded $service"
    else
        warn "Could not reload PHP-FPM; run: sudo systemctl reload ${service:-php8.4-fpm}"
    fi
fi

printf '\n%s✔ Updated in %ss%s (%s)\n' "$GREEN$BOLD" "$(( $(date +%s) - START ))" "$RESET" "$APP_ENV"
