#!/usr/bin/env bash
# ==============================================================================
#  MRBS — Ubuntu Server Updater
#  Updates an existing install.sh-deployed MRBS instance with new code from
#  this directory, while preserving config.inc.php and uploaded content.
#  Run as root: sudo bash update.sh
# ==============================================================================
set -euo pipefail
IFS=$'\n\t'

UPDATER_VERSION="1.0.0"

# ── Defaults (override via env vars before running) ───────────────────────────
MRBS_WEBROOT="${MRBS_WEBROOT:-/var/www/html/mrbs}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/mrbs}"
APACHE_VHOST_CONF="mrbs.conf"

# ── Colours ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[ OK ]${NC}  $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERR ]${NC}  $*" >&2; }
header()  { echo -e "\n${BOLD}${BLUE}──────────────────────────────────────────${NC}"; \
             echo -e "${BOLD}${BLUE}  $*${NC}"; \
             echo -e "${BOLD}${BLUE}──────────────────────────────────────────${NC}"; }

# ── Locate the new source files (this package) ────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ -d "${SCRIPT_DIR}/web" ]]; then
    WEB_SRC="${SCRIPT_DIR}/web"
elif [[ -d "${SCRIPT_DIR}/../web" ]]; then
    WEB_SRC="$(cd "${SCRIPT_DIR}/../web" && pwd)"
else
    error "Cannot find a web/ directory next to update.sh. Run this from the new MRBS package root."
    exit 1
fi

# ── Preflight checks ──────────────────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
    error "This script must be run as root."
    echo -e "  Try: ${BOLD}sudo bash $0${NC}"
    exit 1
fi

if [[ ! -f "${MRBS_WEBROOT}/config.inc.php" ]]; then
    error "No existing MRBS install found at: ${MRBS_WEBROOT}/config.inc.php"
    error "Set MRBS_WEBROOT=/path/to/install sudo bash update.sh  if it's installed elsewhere."
    exit 1
fi

if [[ "$(cd "$MRBS_WEBROOT" && pwd)" == "$WEB_SRC" ]]; then
    error "MRBS_WEBROOT and the source web/ directory are the same path — nothing to update from."
    exit 1
fi

# Reads a simple scalar PHP assignment (eg \$db_database = "mrbs";) out of a file.
_read_php_var() {
    local file="$1" var="$2" default="$3" value
    value="$(grep -oP "^\s*\\\$${var}\s*=\s*['\"][^'\"]*['\"]" "$file" 2>/dev/null \
              | tail -1 \
              | grep -oP "['\"][^'\"]*['\"]" \
              | tail -1 \
              | tr -d "'\"")" || true
    if [[ -n "$value" ]]; then echo "$value"; else echo "$default"; fi
}

CURRENT_DB_NAME="$(_read_php_var "${MRBS_WEBROOT}/config.inc.php" db_database mrbs)"
CURRENT_DB_USER="$(_read_php_var "${MRBS_WEBROOT}/config.inc.php" db_login mrbs)"
CURRENT_DB_HOST="$(_read_php_var "${MRBS_WEBROOT}/config.inc.php" db_host localhost)"

INSTALLED_PHP_VER="$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"

header "MRBS Updater ${UPDATER_VERSION}"
echo ""
printf "  %-22s %s\n" "Existing install:" "$MRBS_WEBROOT"
printf "  %-22s %s\n" "New source:"       "$WEB_SRC"
printf "  %-22s %s\n" "Database:"         "$CURRENT_DB_NAME (user: $CURRENT_DB_USER @ $CURRENT_DB_HOST)"
printf "  %-22s %s\n" "PHP version:"      "$INSTALLED_PHP_VER"
echo ""

if php -r 'exit(version_compare(PHP_VERSION, "8.4.0", "<") ? 1 : 0);' 2>/dev/null; then
    :  # PHP >= 8.4, fine
else
    warn "PHP ${INSTALLED_PHP_VER} is older than 8.4. MRBS 1.12.2's MySQL layer uses the"
    warn "Pdo\\Mysql class introduced in PHP 8.4 and will fail with a fatal error otherwise."
    warn "See install.sh's PHP 8.4 setup (ppa:ondrej/php) before continuing."
fi

read -rp "  Proceed with update? [Y/n]: " _CONFIRM
[[ "${_CONFIRM,,}" == "n" ]] && { info "Aborted — no changes made."; exit 0; }

# ── Step 1 — Backup ────────────────────────────────────────────────────────────
header "Step 1/5 — Backup current install"

TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
BACKUP_DIR="${BACKUP_ROOT}/${TIMESTAMP}"
mkdir -p "$BACKUP_DIR"

# Config + uploaded content (the only things this update will not overwrite anyway,
# but a backup costs nothing and protects against an accidental bad rsync exclude).
cp -a "${MRBS_WEBROOT}/config.inc.php" "${BACKUP_DIR}/config.inc.php"
if [[ -d "${MRBS_WEBROOT}/uploaded" ]]; then
    cp -a "${MRBS_WEBROOT}/uploaded" "${BACKUP_DIR}/uploaded"
fi
success "config.inc.php and uploaded/ backed up to ${BACKUP_DIR}"

# Database dump
DUMP_BIN="$(command -v mariadb-dump || command -v mysqldump || true)"
if [[ -z "$DUMP_BIN" ]]; then
    warn "No mysqldump/mariadb-dump binary found — skipping database backup."
    warn "Install one with: apt-get install mariadb-client"
else
    read -srp "  MariaDB root password (for backup + optional schema upgrade): " DB_ROOT_PASS
    echo ""
    DB_DUMP_FILE="${BACKUP_DIR}/${CURRENT_DB_NAME}.sql"
    if "$DUMP_BIN" -u root -p"${DB_ROOT_PASS}" --single-transaction --routines --events \
            "$CURRENT_DB_NAME" > "$DB_DUMP_FILE" 2>/dev/null; then
        gzip "$DB_DUMP_FILE"
        success "Database dumped to ${DB_DUMP_FILE}.gz"
    else
        rm -f "$DB_DUMP_FILE"
        error "Database dump failed — check the root password and that '${CURRENT_DB_NAME}' exists."
        read -rp "  Continue update WITHOUT a database backup? [y/N]: " _SKIP_BACKUP
        [[ "${_SKIP_BACKUP,,}" != "y" ]] && { info "Aborted — no changes made."; exit 1; }
    fi
fi

# Optional full code backup (skipped by default — the new release replaces all
# code anyway, and lib/ can be large; the dump + config + uploads above are
# what's actually irreplaceable).
read -rp "  Also tar up the full current web root as a rollback safety net? [y/N]: " _FULL_BACKUP
if [[ "${_FULL_BACKUP,,}" == "y" ]]; then
    info "Archiving ${MRBS_WEBROOT} ..."
    tar -czf "${BACKUP_DIR}/webroot.tar.gz" -C "$(dirname "$MRBS_WEBROOT")" "$(basename "$MRBS_WEBROOT")"
    success "Full web root archived to ${BACKUP_DIR}/webroot.tar.gz"
fi

# ── Step 2 — Deploy new code ───────────────────────────────────────────────────
header "Step 2/5 — Deploy new code"

rsync -a --delete \
    --exclude='config.inc.php' \
    --exclude='uploaded/' \
    --exclude='.git/' \
    "${WEB_SRC}/" "${MRBS_WEBROOT}/"

# Make sure the uploaded/ dir and its execution-blocking .htaccess exist even if
# this is the first update since the logo-upload feature was introduced.
mkdir -p "${MRBS_WEBROOT}/uploaded"
if [[ ! -f "${MRBS_WEBROOT}/uploaded/.htaccess" ]]; then
    cat > "${MRBS_WEBROOT}/uploaded/.htaccess" <<'HTACCESS'
<IfModule mod_php.c>
  php_flag engine off
</IfModule>

<FilesMatch "\.(php|php\d|phtml|pl|py|cgi|sh)$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order Deny,Allow
    Deny from all
  </IfModule>
</FilesMatch>
HTACCESS
fi

chown -R www-data:www-data "${MRBS_WEBROOT}"
find "${MRBS_WEBROOT}" -type d -exec chmod 755 {} \;
find "${MRBS_WEBROOT}" -type f -exec chmod 644 {} \;
chmod 640 "${MRBS_WEBROOT}/config.inc.php"

success "Code deployed to ${MRBS_WEBROOT} (config.inc.php and uploaded/ preserved)"

# ── Step 3 — Database schema upgrade ──────────────────────────────────────────
header "Step 3/5 — Database schema upgrade"

SITE_URL="http://localhost/"
read -rp "  Site URL to use for the automatic schema-upgrade check [${SITE_URL}]: " _INPUT
SITE_URL="${_INPUT:-$SITE_URL}"

ATTEMPT_AUTO_UPGRADE=false
if command -v curl &>/dev/null && [[ -n "${DB_ROOT_PASS:-}" ]]; then
    read -rp "  Attempt automatic schema upgrade now (needs DB admin rights)? [Y/n]: " _INPUT
    [[ "${_INPUT,,}" != "n" ]] && ATTEMPT_AUTO_UPGRADE=true
fi

if [[ "$ATTEMPT_AUTO_UPGRADE" == "true" ]]; then
    COOKIE_JAR="$(mktemp)"
    PAGE1="$(curl -s -c "$COOKIE_JAR" "$SITE_URL")"

    if echo "$PAGE1" | grep -q 'id="db_logon"'; then
        info "Upgrade required — submitting database admin credentials..."
        TOKEN="$(echo "$PAGE1" | grep -oP 'name="csrf_token"\s+value="\K[^"]+' | head -1)"

        if [[ -z "$TOKEN" ]]; then
            warn "Could not find a CSRF token on the upgrade page — falling back to manual upgrade."
        else
            RESULT="$(curl -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
                --data-urlencode "form_username=root" \
                --data-urlencode "form_password=${DB_ROOT_PASS}" \
                --data-urlencode "csrf_token=${TOKEN}" \
                "$SITE_URL")"

            # Match the rendered vocab text, not the vocab key — MRBS shows the
            # human-readable string (eg "Database upgrade successfully completed.")
            # in the response, never the internal key name.
            if echo "$RESULT" | grep -qi 'successfully completed\|already at version'; then
                success "Database schema upgrade completed."
            else
                warn "Could not confirm the schema upgrade succeeded from the response."
                warn "Open ${SITE_URL} in a browser to check and complete it manually if needed."
            fi
        fi
    else
        success "No schema upgrade was needed (or the site is already current)."
    fi

    rm -f "$COOKIE_JAR"
else
    info "Skipping automatic upgrade."
    info "Open ${SITE_URL} in a browser — if a schema upgrade is needed, MRBS will"
    info "prompt for a database username/password with CREATE/ALTER rights (eg 'root')."
fi

# ── Step 4 — Restart Apache ────────────────────────────────────────────────────
header "Step 4/5 — Restart Apache"

if systemctl is-active --quiet apache2; then
    systemctl restart apache2
    success "Apache restarted"
else
    warn "Apache2 service is not active — start it manually: systemctl start apache2"
fi

# ── Step 5 — Done ──────────────────────────────────────────────────────────────
header "Step 5/5 — Update complete"
echo ""
echo -e "  ${BOLD}Backup location :${NC}  ${BACKUP_DIR}"
echo -e "  ${BOLD}Web root        :${NC}  ${MRBS_WEBROOT}"
echo -e "  ${BOLD}Config preserved:${NC}  ${MRBS_WEBROOT}/config.inc.php (untouched)"
echo ""
echo -e "  ${YELLOW}${BOLD}Next steps:${NC}"
echo -e "  1. Open ${SITE_URL} and confirm the site loads correctly"
echo -e "  2. If you skipped the automatic schema upgrade, complete it via the browser prompt"
echo -e "  3. Check Apache error log if anything looks wrong: /var/log/apache2/mrbs_error.log"
echo ""
echo -e "  ${CYAN}To roll back:${NC}"
echo -e "    mysql -u root -p ${CURRENT_DB_NAME} < ${BACKUP_DIR}/${CURRENT_DB_NAME}.sql.gz  (gunzip first)"
echo -e "    cp ${BACKUP_DIR}/config.inc.php ${MRBS_WEBROOT}/config.inc.php"
[[ -f "${BACKUP_DIR}/webroot.tar.gz" ]] && \
echo -e "    tar -xzf ${BACKUP_DIR}/webroot.tar.gz -C $(dirname "$MRBS_WEBROOT")"
echo ""
