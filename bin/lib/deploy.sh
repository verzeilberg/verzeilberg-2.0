# Shared functions of bin/setup.sh and bin/update.sh (sourced, not run on its own).
# shellcheck shell=bash

if [ -t 1 ]; then
    BOLD=$'\e[1m'; DIM=$'\e[2m'; RED=$'\e[31m'; GREEN=$'\e[32m'; YELLOW=$'\e[33m'; BLUE=$'\e[34m'; RESET=$'\e[0m'
else
    BOLD=''; DIM=''; RED=''; GREEN=''; YELLOW=''; BLUE=''; RESET=''
fi

STEP=0
step() { STEP=$((STEP + 1)); printf '\n%s%s[%d] %s%s\n' "$BLUE" "$BOLD" "$STEP" "$*" "$RESET"; }
info() { printf '    %s\n' "$*"; }
ok() { printf '    %s✔ %s%s\n' "$GREEN" "$*" "$RESET"; }
warn() { printf '    %s! %s%s\n' "$YELLOW" "$*" "$RESET"; }
fail() { printf '\n%s✘ %s%s\n' "$RED$BOLD" "$*" "$RESET" >&2; exit 1; }

# Asks a yes/no question; --yes answers the default
confirm() {
    local question="$1" default="${2:-y}" answer
    if [ "${ASSUME_YES:-0}" = 1 ]; then [ "$default" = y ]; return; fi
    read -r -p "    $question [$([ "$default" = y ] && echo 'Y/n' || echo 'y/N')] " answer
    answer="${answer:-$default}"
    [[ "$answer" =~ ^[Yy] ]]
}

# The Symfony console in the chosen environment
console() { APP_ENV="$APP_ENV" php bin/console "$@"; }

# The user PHP-FPM runs as (www-data on Debian/Ubuntu), unless --web-user was given
detect_web_user() {
    if [ -n "${WEB_USER:-}" ]; then return; fi
    WEB_USER="$(ps -eo user=,comm= 2>/dev/null | awk '$2 ~ /php-fpm|apache2|httpd/ && $1 != "root" { print $1; exit }')"
    WEB_USER="${WEB_USER:-www-data}"
}

check_php() {
    command -v php >/dev/null || fail "PHP is not installed."
    php -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' || fail "PHP 8.4 or newer is needed (found $(php -r 'echo PHP_VERSION;'))."
    ok "PHP $(php -r 'echo PHP_VERSION;')"
}

find_composer() {
    if command -v composer >/dev/null; then COMPOSER=(composer)
    elif [ -f composer.phar ]; then COMPOSER=(php composer.phar)
    else fail "Composer is not installed: https://getcomposer.org/download/"; fi
    ok "Composer $("${COMPOSER[@]}" --version 2>/dev/null | awk '{print $3}')"
}

# Production: no dev packages and an optimized autoloader. The composer scripts (cache:clear, importmap:install)
# run in the chosen environment.
composer_install() {
    local flags=(install --no-interaction --no-progress)
    [ "$APP_ENV" = prod ] && flags+=(--no-dev --optimize-autoloader --classmap-authoritative)
    info "Checking the PHP extensions the packages need..."
    local check=(check-platform-reqs)
    [ "$APP_ENV" = prod ] && check+=(--no-dev)
    if ! "${COMPOSER[@]}" "${check[@]}" >/dev/null 2>&1; then
        "${COMPOSER[@]}" "${check[@]}" 2>/dev/null | grep -v ' success' | sed 's/^/    /' || true
        fail "Install the missing PHP extensions above (e.g. sudo apt install php8.4-<name>)."
    fi
    # The output goes to a file first, so the exit status is Composer's own. An old Composer prints PHP
    # deprecation notices about itself; those are left out, they say nothing about the site.
    local log; log="$(mktemp)"
    if ! APP_ENV="$APP_ENV" "${COMPOSER[@]}" "${flags[@]}" > "$log" 2>&1; then
        grep -v '^Deprecation Notice:' "$log" | sed 's/^/    /'
        rm -f "$log"
        fail "composer install failed (above)."
    fi
    grep -E '^\s*- (Installing|Upgrading|Downgrading|Removing)|Nothing to install' "$log" | sed 's/^ */    /' || true
    rm -f "$log"
    ok "Packages installed"
}

# Production serves compiled, versioned files from public/assets. In development that folder must not exist:
# its old files would be served instead of the current ones.
build_assets() {
    console importmap:install >/dev/null || fail "importmap:install failed."
    if [ "$APP_ENV" = prod ]; then
        rm -rf public/assets
        console asset-map:compile >/dev/null || fail "asset-map:compile failed."
        ok "CSS/JS compiled into public/assets"
    else
        if [ -d public/assets ]; then rm -rf public/assets; ok "Removed public/assets (development serves the files directly)"; else ok "Assets are served directly (development)"; fi
    fi
}

clear_cache() {
    console cache:clear --no-warmup >/dev/null || fail "cache:clear failed."
    console cache:warmup >/dev/null || fail "cache:warmup failed."
    ok "Cache cleared and warmed up"
}

# The folders PHP writes to: var/ (cache, logs, checklist uploads in var/storage) and public/uploads.
# Both this user (for console commands and cronjobs) and the web server user must be able to write.
WRITABLE=(var var/cache var/log var/storage public/uploads public/uploads/blog/images public/uploads/blog/files public/uploads/events/events public/uploads/events/images)
fix_permissions() {
    mkdir -p "${WRITABLE[@]}"
    local me group
    me="$(id -un)"
    group="$(id -gn "$WEB_USER" 2>/dev/null || echo "$WEB_USER")"
    # Files the web server created itself (cache, uploads) belong to it and can't be changed by this user.
    # That's fine, so errors on those are ignored; real problems are reported below.
    if [ "$(id -u)" = 0 ]; then
        chown -R "$WEB_USER": var public/uploads && chmod -R ug+rwX var public/uploads
        ok "var/ and public/uploads belong to $WEB_USER"
    elif command -v setfacl >/dev/null; then
        find var public/uploads -path var/backups -prune -o -user "$me" -exec setfacl -m "u:$WEB_USER:rwX" -m "u:$me:rwX" {} + 2>/dev/null || true
        find var public/uploads -path var/backups -prune -o -type d -user "$me" -exec setfacl -d -m "u:$WEB_USER:rwX" -m "u:$me:rwX" {} + 2>/dev/null || true
        ok "var/ and public/uploads writable for $me and $WEB_USER (ACL)"
    elif id -nG "$me" | tr ' ' '\n' | grep -qx "$group"; then
        find var public/uploads -path var/backups -prune -o -user "$me" -exec chgrp "$group" {} + 2>/dev/null || true
        find var public/uploads -path var/backups -prune -o -user "$me" -exec chmod g+rwX {} + 2>/dev/null || true
        find var public/uploads -path var/backups -prune -o -type d -user "$me" -exec chmod g+s {} + 2>/dev/null || true
        ok "var/ and public/uploads writable for group $group"
    else
        warn "Can't give $WEB_USER write access to var/ and public/uploads (install 'acl', or add $me to the group $group)."
    fi
    # Database dumps (bin/update.sh --backup) stay private to this user
    if [ -d var/backups ]; then
        setfacl -R -b var/backups 2>/dev/null || true
        chmod 700 var/backups && find var/backups -type f -exec chmod 600 {} + 2>/dev/null || true
    fi

    local stuck
    stuck="$(find var public/uploads ! -writable 2>/dev/null | head -3 || true)"
    if [ -n "$stuck" ]; then
        warn "Some files there aren't writable for $me (made by $WEB_USER); console commands may fail on them, e.g.:"
        printf '      %s\n' $stuck
    fi

    # The settings files hold passwords: readable for the web server, not for everybody
    local file
    for file in .env.local ".env.$APP_ENV.local"; do
        { [ -f "$file" ] && [ -O "$file" ]; } || continue
        if [ "$(id -u)" = 0 ] || id -nG "$me" | tr ' ' '\n' | grep -qx "$group"; then
            chgrp "$group" "$file" && chmod 640 "$file"
        else
            chmod 644 "$file"
        fi
    done
    return 0
}

run_migrations() {
    if console doctrine:migrations:up-to-date >/dev/null 2>&1; then ok "Database is up to date"; return; fi
    console doctrine:migrations:migrate --no-interaction --allow-no-migration || fail "The migration failed; the site may need the previous version of the database. Check the error above."
    ok "Database updated"
}

print_cron() {
    local dir; dir="$(pwd)"
    cat <<EOF
    Add these cronjobs (crontab -e, as $(id -un) or $WEB_USER):
      ${DIM}# Google Calendar agendas, every 15 minutes${RESET}
      */15 * * * * APP_ENV=$APP_ENV php $dir/bin/console app:agenda:sync-google --quiet
      ${DIM}# Steam game library, every hour${RESET}
      0 * * * * APP_ENV=$APP_ENV php $dir/bin/console app:steam:sync --quiet
EOF
}
