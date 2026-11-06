#!/usr/bin/env bash
#
# Builds one SQL file that REPLACES the production database, for hosting where you cannot create a new database
# and only have phpMyAdmin. Importing the file:
#   1. drops EVERY table (and view) in the database it is imported into: the old site's tables are gone;
#   2. creates the tables of the new site and fills them with the data of the local database.
# The local database is the one DATABASE_URL in .env.local points to (verzeilberg_new).
#
# Usage: bin/build-db-export.sh [--mariadb] [--out=FILE]
#   --mariadb  the production server runs MariaDB instead of MySQL 8 (phpMyAdmin's home page shows the
#              "Server type"); converts the MySQL-8-only collation utf8mb4_0900_ai_ci to utf8mb4_unicode_ci
#   --out=…    output file without extension (default: var/backups/production-import-YYYYMMDD-HHMM);
#              writes FILE.sql and a compressed copy FILE.sql.gz (use that one when .sql is over the upload limit)
#
# Import it in phpMyAdmin: select the production database in the left column, tab Import, choose the .sql.gz
# file, Go. Export the old database first (tab Export) as a backup: dropping the tables cannot be undone.
# The file holds personal data and encrypted secrets: delete it (locally and on the server) when done.

set -euo pipefail
cd "$(dirname "$0")/.." || exit 2
# shellcheck source=lib/deploy.sh
source bin/lib/deploy.sh

APP_ENV=dev
MARIADB=0
OUT="var/backups/production-import-$(date +%Y%m%d-%H%M)"
for arg in "$@"; do
    case "$arg" in
        --mariadb) MARIADB=1 ;;
        --out=*) OUT="${arg#--out=}" ;;
        -h|--help) awk 'NR > 2 && /^#/ { sub(/^# ?/, ""); print; next } NR > 2 { exit }' "$0"; exit 0 ;;
        *) fail "Unknown option: $arg (see --help)" ;;
    esac
done
command -v mysqldump >/dev/null || fail "mysqldump is not installed (sudo apt install mysql-client)."

step "Local database"
# The connection from DATABASE_URL in .env.local; the password goes through MYSQL_PWD, not the command line
eval "$(APP_ENV="$APP_ENV" php -r '
    require "vendor/autoload.php";
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
    $u = parse_url((string) ($_SERVER["DATABASE_URL"] ?? ""));
    foreach (["host" => "DB_HOST", "port" => "DB_PORT", "user" => "DB_USER", "pass" => "DB_PASS", "path" => "DB_NAME"] as $k => $v) {
        $value = $k === "path" ? ltrim((string) ($u[$k] ?? ""), "/") : (string) ($u[$k] ?? "");
        echo $v, "=", escapeshellarg(urldecode($value)), "\n";
    }')"
[ -n "$DB_NAME" ] || fail "No database in DATABASE_URL (.env.local)."
info "Source: $DB_NAME on $DB_HOST"
console doctrine:migrations:up-to-date >/dev/null 2>&1 \
    || fail "$DB_NAME is missing migrations (php bin/console doctrine:migrations:status). Run them first, then try again."
ok "All migrations applied"

step "SQL file"
mkdir -p "$(dirname "$OUT")"
umask 077
{
    cat <<'SQL'
-- Verzeilberg.nl: replaces the WHOLE database it is imported into.
-- Select the production database in phpMyAdmin first. All existing tables are dropped.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SESSION group_concat_max_len = 1000000;

-- Drop every view and table of the current database (the old site), whatever they are called
SET @drop = (SELECT GROUP_CONCAT(CONCAT('`', REPLACE(TABLE_NAME, '`', '``'), '`')) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'VIEW');
SET @drop = IF(@drop IS NULL, 'DO 0', CONCAT('DROP VIEW IF EXISTS ', @drop));
PREPARE drop_statement FROM @drop;
EXECUTE drop_statement;
DEALLOCATE PREPARE drop_statement;

SET @drop = (SELECT GROUP_CONCAT(CONCAT('`', REPLACE(TABLE_NAME, '`', '``'), '`')) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE');
SET @drop = IF(@drop IS NULL, 'DO 0', CONCAT('DROP TABLE IF EXISTS ', @drop));
PREPARE drop_statement FROM @drop;
EXECUTE drop_statement;
DEALLOCATE PREPARE drop_statement;

-- The new site: tables and data
SQL
    # No CREATE DATABASE/USE (imports into the selected database); no GTID lines (need privileges shared hosting
    # doesn't give); INSERT statements of at most ~16 KB: phpMyAdmin cut off longer ones (a 485 KB INSERT into
    # strava_rounds arrived half, "11 waarden verwacht, maar 9 gevonden")
    MYSQL_PWD="$DB_PASS" mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USER" \
        --single-transaction --no-tablespaces --set-gtid-purged=OFF --skip-comments --hex-blob \
        --default-character-set=utf8mb4 --net-buffer-length=16384 "$DB_NAME" \
        | if [ "$MARIADB" = 1 ]; then sed 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g'; else cat; fi
    printf '\nSET FOREIGN_KEY_CHECKS = 1;\n'
} > "$OUT.sql" || { rm -f "$OUT.sql"; fail "The export failed."; }
gzip -9 -c "$OUT.sql" > "$OUT.sql.gz"

tables=$(grep -c '^CREATE TABLE' "$OUT.sql")
[ "$tables" -gt 0 ] || { rm -f "$OUT.sql" "$OUT.sql.gz"; fail "The export holds no tables."; }
longest=$(awk '{ if (length($0) > m) m = length($0) } END { print m }' "$OUT.sql")
ok "$OUT.sql ($(du -h "$OUT.sql" | cut -f1)) and $OUT.sql.gz ($(du -h "$OUT.sql.gz" | cut -f1)): $tables tables, longest statement $((longest / 1024)) KB$([ "$MARIADB" = 1 ] && echo ', MariaDB collation')"

cat <<EOF

    In phpMyAdmin on production:
      1. Back up the old site: select the database > Export > Go (keep that file).
      2. Select the database > Import > choose $(basename "$OUT").sql (or the .sql.gz when the .sql is bigger
         than the "Max" shown on the Import page) > Go.
         Every existing table in that database is dropped first.
      3. Check that the tables list shows $tables tables (including doctrine_migration_versions).
    Then delete $OUT.sql(.gz) here and on the server: it holds personal data.
EOF
