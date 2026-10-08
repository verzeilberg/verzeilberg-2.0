#!/usr/bin/env bash
#
# Shows which settings (environment variables) the site needs, whether they are filled in,
# and how to get the missing ones. Values are read the way Symfony reads them: .env, .env.local,
# .env.<env>, .env.<env>.local, real environment variables, and .env.local.php (composer dump-env).
#
# Usage: bin/check-env.sh [--env=prod] [--all] [--no-color]
#   --env=…     environment to check (default: APP_ENV from the .env files, usually dev)
#   --all       also explain the settings that are already filled in
#   --no-color  plain output (e.g. for a log file)
#
# Exit code 1 when a required setting is missing or still a placeholder.

set -uo pipefail
cd "$(dirname "$0")/.." || exit 2

SHOW_ALL=0
COLOR=1
CHECK_ENV=""
for arg in "$@"; do
    case "$arg" in
        --env=*) CHECK_ENV="${arg#--env=}" ;;
        --all) SHOW_ALL=1 ;;
        --no-color) COLOR=0 ;;
        -h|--help) sed -n '3,12p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg (see --help)" >&2; exit 2 ;;
    esac
done
[ -t 1 ] || COLOR=0

if [ "$COLOR" = 1 ]; then
    BOLD=$'\e[1m'; DIM=$'\e[2m'; RED=$'\e[31m'; GREEN=$'\e[32m'; YELLOW=$'\e[33m'; BLUE=$'\e[34m'; RESET=$'\e[0m'
else
    BOLD=''; DIM=''; RED=''; GREEN=''; YELLOW=''; BLUE=''; RESET=''
fi

# ---------------------------------------------------------------------------------------------
# The settings. Per setting: group | required (yes/no) | secret (yes/no) | what it is for | how to get it
# "required: no" = the feature is switched off while it's empty.
# ---------------------------------------------------------------------------------------------
VARS=(
    APP_SECRET DATABASE_URL DEFAULT_URI MAILER_DSN
    GOOGLE_MAPS_API_KEY
    GOOGLE_CLIENT_ID GOOGLE_CLIENT_SECRET
    STRAVA_CLIENT_ID STRAVA_CLIENT_SECRET STRAVA_REDIRECT_URI STRAVA_ATHLETE_ID STRAVA_GOOGLE_MAPS_KEY
    STEAM_API_KEY STEAM_ID
    HANDBAL_PUBLIC_PASSWORD
    SYMFONY_TRUSTED_PROXIES
)

declare -A GROUP REQUIRED SECRET PURPOSE HOWTO

def() { GROUP[$1]="$2"; REQUIRED[$1]="$3"; SECRET[$1]="$4"; PURPOSE[$1]="$5"; HOWTO[$1]="$6"; }

def APP_SECRET "Core" yes yes \
    "Secret for CSRF tokens, login cookies and the encryption of stored Google tokens." \
    "Generate a random value:  php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
Use a different one per server. Changing it later logs everyone out and makes connected Google
accounts unreadable (connect them again in Admin > Agenda > Manage agendas)."

def DATABASE_URL "Core" yes yes \
    "The database connection." \
    "Format: mysql://USER:PASSWORD@HOST:3306/DATABASE?serverVersion=8.0.32&charset=utf8mb4
(for MariaDB use serverVersion=10.11.2-MariaDB). Create the database and user at your hosting
provider, then run: php bin/console doctrine:migrations:migrate"

def DEFAULT_URI "Core" yes no \
    "The site's address, used for links built outside a web request (cronjobs, e-mails sent by commands)." \
    "The public URL of the site, e.g. https://verzeilberg.nl (no trailing slash)."

def MAILER_DSN "Core" yes yes \
    "Sending e-mail: contact form notifications and replies, password reset mails." \
    "Ask your mail/hosting provider for the SMTP details, then use:
  smtp://USER:PASSWORD@smtp.example.com:587
(URL-encode special characters in the password, e.g. @ = %40). Gmail (development only):
  gmail://you@gmail.com:APP_PASSWORD@default  - create an app password at
  https://myaccount.google.com/apppasswords (needs 2-step verification)."

def GOOGLE_MAPS_API_KEY "Google Maps" no yes \
    "Maps and place search for agenda items, and the event maps." \
    "1. https://console.cloud.google.com > create/select a project (billing must be enabled;
   normal use stays within the free monthly credit).
2. APIs & Services > Library: enable 'Maps JavaScript API', 'Places API (New)' and 'Maps Embed API'.
3. APIs & Services > Credentials > Create credentials > API key.
4. Restrict the key: Application restrictions = Websites, add https://YOUR-DOMAIN/*;
   API restrictions = the three APIs above."

def GOOGLE_CLIENT_ID "Google Calendar" no no \
    "Showing Google Calendar events in the agenda (read-only)." \
    "1. https://console.cloud.google.com > APIs & Services > Library: enable 'Google Calendar API'.
2. OAuth consent screen: type External, add yourself as test user (or publish the app),
   scope .../auth/calendar.readonly.
3. Credentials > Create credentials > OAuth client ID > Web application.
   Authorized redirect URI: https://YOUR-DOMAIN/admin/agenda/google/callback
4. Copy the Client ID here and the Client secret into GOOGLE_CLIENT_SECRET.
Then connect the account in Admin > Agenda > Manage agendas, and add the cronjob:
  */15 * * * * php /path/to/bin/console app:agenda:sync-google --quiet"

def GOOGLE_CLIENT_SECRET "Google Calendar" no yes \
    "Secret of the Google OAuth client (see GOOGLE_CLIENT_ID)." \
    "Shown next to the Client ID in Google Cloud > APIs & Services > Credentials."

def STRAVA_CLIENT_ID "Strava" no no \
    "The Strava activities, statistics and import." \
    "1. https://www.strava.com/settings/api > create an application.
2. Authorization Callback Domain: your domain without https:// (e.g. verzeilberg.nl).
3. Copy the Client ID here and the Client Secret into STRAVA_CLIENT_SECRET.
Then authorize once via Admin > Strava > Authorize."

def STRAVA_CLIENT_SECRET "Strava" no yes \
    "Secret of the Strava application." \
    "https://www.strava.com/settings/api (click 'show' next to Client Secret)."

def STRAVA_REDIRECT_URI "Strava" no no \
    "Where Strava sends you back after authorizing." \
    "https://YOUR-DOMAIN/strava/callback - the domain must match the Authorization Callback Domain."

def STRAVA_ATHLETE_ID "Strava" no no \
    "The athlete whose activities are shown." \
    "Open your Strava profile; the number in the URL https://www.strava.com/athletes/12345678."

def STRAVA_GOOGLE_MAPS_KEY "Strava" no yes \
    "Google Maps key for the Strava pages." \
    "A Google Maps API key (see GOOGLE_MAPS_API_KEY); the same key can be used for both."

def STEAM_API_KEY "Steam" no yes \
    "The Steam game library (/games, the home page block, Admin > Steam)." \
    "1. Log in at https://steamcommunity.com/dev/apikey (the account needs a purchase of at least \$5).
2. Domain name: your site's domain; accept the terms; copy the key.
3. In Steam > Settings > Privacy, set 'Game details' to Public.
Then run once: php bin/console app:steam:sync - and add the cronjob:
  0 * * * * php /path/to/bin/console app:steam:sync --quiet"

def STEAM_ID "Steam" no no \
    "The Steam profile whose games are shown." \
    "Your 17-digit Steam ID (Steam > your name > Account details, 'Steam ID: 7656...'),
or the name from your profile URL (https://steamcommunity.com/id/NAME/), or the profile URL itself."

def HANDBAL_PUBLIC_PASSWORD "Handbal" no yes \
    "Password of the public handbal standings page (/handbal); while it's empty the page can't be opened." \
    "Choose a password and share it with the team. Changing it doesn't log out people who already entered the old one
until their session ends."

def SYMFONY_TRUSTED_PROXIES "Server" no no \
    "Only needed behind a reverse proxy/load balancer (e.g. Cloudflare, a Docker proxy), so HTTPS and
visitor IPs are detected correctly (rate limiting uses the IP)." \
    "The proxy's IP address(es), e.g. 127.0.0.1,REMOTE_ADDR or a range like 10.0.0.0/8.
Leave empty when the web server receives the visitors directly."

# Settings that only make sense together: when some are filled in, all must be
declare -A TOGETHER=(
    ["Google Calendar"]="GOOGLE_CLIENT_ID GOOGLE_CLIENT_SECRET"
    ["Strava"]="STRAVA_CLIENT_ID STRAVA_CLIENT_SECRET STRAVA_REDIRECT_URI STRAVA_ATHLETE_ID"
    ["Steam"]="STEAM_API_KEY STEAM_ID"
)

# ---------------------------------------------------------------------------------------------
# Read the values through Symfony's Dotenv (same precedence as the application)
# ---------------------------------------------------------------------------------------------
if ! command -v php >/dev/null; then
    echo "php is needed to read the settings." >&2; exit 2
fi
if [ ! -f vendor/autoload.php ]; then
    echo "Run 'composer install' first (vendor/ is missing)." >&2; exit 2
fi

declare -A VALUE
declare -A DEFINED
EXTRA_NAMES=$(grep -hoE '^[A-Z][A-Z0-9_]*=' .env .env.local .env.dev .env.prod .env.test .env.*.local 2>/dev/null | tr -d '=' | sort -u)

while IFS=$'\t' read -r name defined encoded; do
    [ -z "$name" ] && continue
    if [ "$name" = "__ENV__" ]; then CHECK_ENV="$encoded"; continue; fi
    DEFINED[$name]="$defined"
    VALUE[$name]="$(printf '%s' "$encoded" | base64 -d 2>/dev/null)"
done < <(APP_ENV_OVERRIDE="$CHECK_ENV" php -r '
    require "vendor/autoload.php";
    if (getenv("APP_ENV_OVERRIDE") !== "") { $_SERVER["APP_ENV"] = $_ENV["APP_ENV"] = getenv("APP_ENV_OVERRIDE"); putenv("APP_ENV=" . getenv("APP_ENV_OVERRIDE")); }
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
    echo "__ENV__\t1\t", $_SERVER["APP_ENV"] ?? "dev", "\n";
    foreach (array_slice($argv, 1) as $name) {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        echo $name, "\t", $value === false || $value === null ? 0 : 1, "\t", base64_encode((string) $value), "\n";
    }
' "${VARS[@]}" $EXTRA_NAMES)

# ---------------------------------------------------------------------------------------------
# Checks
# ---------------------------------------------------------------------------------------------

# Why a filled-in value is still wrong (placeholder, development value on production), or empty
problem() {
    local name="$1" value="$2"
    case "$name" in
        APP_SECRET)
            [ ${#value} -lt 16 ] && echo "too short: use at least 32 random characters" ;;
        DATABASE_URL)
            [[ "$value" == *'!ChangeMe!'* ]] && echo "still the example password"
            [[ "$CHECK_ENV" == prod && "$value" == sqlite* ]] && echo "SQLite is the skeleton default; use the MySQL/MariaDB database" ;;
        DEFAULT_URI)
            [[ "$CHECK_ENV" == prod && "$value" == *localhost* ]] && echo "still localhost; use the public URL of the site"
            [[ "$CHECK_ENV" == prod && "$value" == *-dev* ]] && echo "points to the development site" ;;
        MAILER_DSN)
            [[ "$value" == *USERNAME:PASSWORD* ]] && echo "still the example USERNAME:PASSWORD"
            [[ "$value" == null://* ]] && echo "null:// sends no mail at all"
            [[ "$CHECK_ENV" == prod && "$value" == gmail://* ]] && echo "Gmail should not be used on production" ;;
        STRAVA_REDIRECT_URI)
            [[ "$CHECK_ENV" == prod && "$value" == *-dev* ]] && echo "points to the development site" ;;
        STRAVA_ATHLETE_ID)
            [[ -n "$value" && ! "$value" =~ ^[0-9]+$ ]] && echo "must be a number" ;;
        STEAM_ID)
            [[ -n "$value" && ! "$value" =~ ^[0-9]{17}$ && ! "$value" =~ ^[A-Za-z0-9_-]+$ && "$value" != *steamcommunity.com/* ]] && echo "not a Steam ID, profile name or profile URL" ;;
    esac
}

mask() {
    local value="$1"
    if [ "${#value}" -lt 16 ]; then printf '%s' '********'; else printf '%s…%s' "${value:0:4}" "${value: -2}"; fi
}

indent() { sed 's/^/      /'; }

FAILED=0
WARNINGS=0
current_group=""

printf '%sSettings check%s  environment: %s%s%s\n' "$BOLD" "$RESET" "$BOLD" "$CHECK_ENV" "$RESET"
printf '%sPut values in .env.local (development) or the server environment / .env.%s.local (never in .env, it is in git).%s\n' "$DIM" "$CHECK_ENV" "$RESET"

for name in "${VARS[@]}"; do
    group="${GROUP[$name]}"
    if [ "$group" != "$current_group" ]; then
        current_group="$group"
        label="$group"
        [ "${REQUIRED[$name]}" = no ] && [ "$group" != Server ] && label="$group (optional: switched off while empty)"
        [ "$group" = Server ] && label="$group (optional)"
        printf '\n%s%s%s\n' "$BLUE$BOLD" "$label" "$RESET"
    fi

    value="${VALUE[$name]:-}"
    issue="$(problem "$name" "$value" | head -1)"
    shown="$value"
    [ "${SECRET[$name]}" = yes ] && [ -n "$value" ] && shown="$(mask "$value")"

    if [ -z "$value" ]; then
        if [ "${REQUIRED[$name]}" = yes ]; then
            printf '  %s✘ %-24s%s missing (required)\n' "$RED" "$name" "$RESET"; FAILED=1
        else
            printf '  %s○ %-24s%s not set\n' "$YELLOW" "$name" "$RESET"
        fi
        explain=1
    elif [ -n "$issue" ]; then
        if [ "${REQUIRED[$name]}" = yes ]; then
            printf '  %s✘ %-24s%s %s  %s(%s)%s\n' "$RED" "$name" "$RESET" "$shown" "$RED" "$issue" "$RESET"; FAILED=1
        else
            printf '  %s! %-24s%s %s  %s(%s)%s\n' "$YELLOW" "$name" "$RESET" "$shown" "$YELLOW" "$issue" "$RESET"; WARNINGS=1
        fi
        explain=1
    else
        printf '  %s✔ %-24s%s %s%s%s\n' "$GREEN" "$name" "$RESET" "$DIM" "$shown" "$RESET"
        explain=$SHOW_ALL
    fi

    if [ "$explain" = 1 ]; then
        printf '%s\n' "${PURPOSE[$name]}" | indent
        printf '%sHow to get it:%s\n' "$BOLD" "$RESET" | indent
        printf '%s\n' "${HOWTO[$name]}" | sed 's/^/  /' | indent
    fi
done

# Half-configured features don't work at all
printf '\n'
for group in "${!TOGETHER[@]}"; do
    set_count=0; total=0; missing=()
    for name in ${TOGETHER[$group]}; do
        total=$((total + 1))
        if [ -n "${VALUE[$name]:-}" ]; then set_count=$((set_count + 1)); else missing+=("$name"); fi
    done
    if [ "$set_count" -gt 0 ] && [ "$set_count" -lt "$total" ]; then
        printf '%s! %s is only partly configured: also set %s%s\n' "$YELLOW" "$group" "${missing[*]}" "$RESET"
        WARNINGS=1
    fi
done

# Variables in the .env files that the application doesn't read
unused=()
for name in $EXTRA_NAMES; do
    case " ${VARS[*]} APP_ENV APP_DEBUG APP_SHARE_DIR " in *" $name "*) continue ;; esac
    unused+=("$name")
done
if [ "${#unused[@]}" -gt 0 ]; then
    printf '%sIn the .env files but not used by the application: %s%s\n' "$DIM" "${unused[*]}" "$RESET"
fi

if [ "$FAILED" = 1 ]; then
    printf '\n%s%sRequired settings are missing or wrong.%s\n' "$RED" "$BOLD" "$RESET"
    exit 1
fi
if [ "$WARNINGS" = 1 ]; then
    printf '\n%sRequired settings are fine; see the warnings above.%s\n' "$YELLOW" "$RESET"
else
    printf '\n%sAll required settings are filled in.%s\n' "$GREEN" "$RESET"
fi
exit 0
