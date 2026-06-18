#!/usr/bin/env bash
# ==============================================================================
#  MRBS 1.12.2 — Ubuntu Server Installer
#  Supports Ubuntu 20.04 LTS, 22.04 LTS, 24.04 LTS
#  Run as root: sudo bash install.sh
# ==============================================================================
set -euo pipefail
IFS=$'\n\t'

INSTALLER_VERSION="1.0.0"
MRBS_VERSION="1.12.2"

# ── Defaults (override via env vars before running) ───────────────────────────
MRBS_WEBROOT="${MRBS_WEBROOT:-/var/www/html/mrbs}"
MRBS_DB_NAME="${MRBS_DB_NAME:-mrbs}"
MRBS_DB_USER="${MRBS_DB_USER:-mrbs}"
MRBS_DB_TBL_PREFIX="${MRBS_DB_TBL_PREFIX:-mrbs_}"
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

# ── Locate project files ──────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Support running from a deploy/ subdirectory too
if [[ -d "${SCRIPT_DIR}/web" ]]; then
    WEB_SRC="${SCRIPT_DIR}/web"
    SQL_FILE="${SCRIPT_DIR}/tables.my.sql"
elif [[ -d "${SCRIPT_DIR}/../web" ]]; then
    WEB_SRC="$(cd "${SCRIPT_DIR}/../web" && pwd)"
    SQL_FILE="$(cd "${SCRIPT_DIR}/.." && pwd)/tables.my.sql"
else
    error "Cannot find MRBS web/ directory. Run install.sh from the project root."
    exit 1
fi

# ── Preflight checks ──────────────────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
    error "This script must be run as root."
    echo -e "  Try: ${BOLD}sudo bash $0${NC}"
    exit 1
fi

if ! grep -qi ubuntu /etc/os-release 2>/dev/null; then
    error "This installer supports Ubuntu only."
    exit 1
fi

UBUNTU_VERSION=$(. /etc/os-release && echo "$VERSION_ID")
info "Detected Ubuntu ${UBUNTU_VERSION}"

# MRBS 1.12.2 requires PHP 8.4+ (uses Pdo\Mysql class introduced in PHP 8.4).
# Ubuntu versions < 25.04 do not ship PHP 8.4 in their default repos — use ondrej/php.
PHP_VER="8.4"

case "$UBUNTU_VERSION" in
    "20.04"|"22.04"|"24.04"|"24.10") NEED_ONDREJ=true  ;;
    "25.04"|"25.10"|"26.04"|"26.10") NEED_ONDREJ=false ;;
    *)
        warn "Ubuntu ${UBUNTU_VERSION} is not in the tested version list."
        # Check if PHP 8.4 is already available without the PPA
        if apt-cache show "php8.4-cli" &>/dev/null 2>&1; then
            NEED_ONDREJ=false
            info "PHP 8.4 found in default repos"
        else
            NEED_ONDREJ=true
            info "PHP 8.4 not in default repos — will add ppa:ondrej/php"
        fi ;;
esac
info "PHP version: ${PHP_VER} (required — Pdo\\Mysql class introduced in PHP 8.4)"

# ── Interactive configuration ─────────────────────────────────────────────────
header "MRBS ${MRBS_VERSION} — Interactive Setup"
echo ""
echo "  Press Enter to accept each default shown in [brackets]."
echo ""

# Site hostname / IP
DEFAULT_HOST="$(hostname -I 2>/dev/null | awk '{print $1}')"
DEFAULT_HOST="${DEFAULT_HOST:-localhost}"
read -rp "  $(echo -e "${BOLD}Site hostname or IP${NC}") [${DEFAULT_HOST}]: " _INPUT
SITE_HOST="${_INPUT:-${DEFAULT_HOST}}"

# Web root
read -rp "  $(echo -e "${BOLD}Web root path${NC}") [${MRBS_WEBROOT}]: " _INPUT
MRBS_WEBROOT="${_INPUT:-${MRBS_WEBROOT}}"

# Timezone
read -rp "  $(echo -e "${BOLD}Timezone${NC}") (e.g. Europe/London, America/New_York) [UTC]: " _INPUT
SITE_TIMEZONE="${_INPUT:-UTC}"

# MariaDB root password
echo ""
while true; do
    read -srp "  $(echo -e "${BOLD}MariaDB root password${NC}") (set a NEW strong password): " DB_ROOT_PASS; echo ""
    read -srp "  Confirm root password: " _CONFIRM; echo ""
    [[ "$DB_ROOT_PASS" == "$_CONFIRM" ]] && break
    warn "Passwords do not match — try again."
done

# MRBS database password
echo ""
while true; do
    read -srp "  $(echo -e "${BOLD}MRBS database password${NC}") (for DB user '${MRBS_DB_USER}'): " MRBS_DB_PASS; echo ""
    read -srp "  Confirm MRBS DB password: " _CONFIRM; echo ""
    [[ "$MRBS_DB_PASS" == "$_CONFIRM" ]] && break
    warn "Passwords do not match — try again."
done

# Admin e-mail
echo ""
read -rp "  $(echo -e "${BOLD}Admin e-mail${NC}") [admin@${SITE_HOST}]: " _INPUT
ADMIN_EMAIL="${_INPUT:-admin@${SITE_HOST}}"

# Modern theme
read -rp "  $(echo -e "${BOLD}Enable modern indigo theme?${NC}") [Y/n]: " _INPUT
ENABLE_MODERN_THEME=true
[[ "${_INPUT,,}" == "n" ]] && ENABLE_MODERN_THEME=false

# SSL via Certbot
read -rp "  $(echo -e "${BOLD}Install Let's Encrypt SSL via Certbot?${NC}") (needs real domain) [y/N]: " _INPUT
ENABLE_SSL=false
[[ "${_INPUT,,}" == "y" ]] && ENABLE_SSL=true

# Confirm
header "Installation Plan"
echo ""
printf "  %-22s %s\n" "Ubuntu version:"     "$UBUNTU_VERSION"
printf "  %-22s %s\n" "PHP version:"        "${PHP_VER} (required by MRBS 1.12.2)"
printf "  %-22s %s\n" "Web root:"           "$MRBS_WEBROOT"
printf "  %-22s %s\n" "Site host:"          "$SITE_HOST"
printf "  %-22s %s\n" "Timezone:"           "$SITE_TIMEZONE"
printf "  %-22s %s\n" "DB name:"            "$MRBS_DB_NAME"
printf "  %-22s %s\n" "DB user:"            "$MRBS_DB_USER"
printf "  %-22s %s\n" "Modern theme:"       "$ENABLE_MODERN_THEME"
printf "  %-22s %s\n" "SSL (Certbot):"      "$ENABLE_SSL"
echo ""
read -rp "  Proceed with installation? [Y/n]: " _CONFIRM
[[ "${_CONFIRM,,}" == "n" ]] && { info "Aborted — no changes made."; exit 0; }

# ── Step 1 — Update package lists ─────────────────────────────────────────────
header "Step 1/8 — Update package lists"
apt-get update -qq
success "Package lists updated"

# ── Step 2 — Ensure PHP >= 8.4 is available ───────────────────────────────────

# Finds the lowest available PHP version >= 8.4 in the current apt cache.
# Outputs the version string (e.g. "8.4" or "8.5"), or empty string if none found.
_find_php84plus() {
    apt-cache search '^php[0-9]+\.[0-9]+-cli$' 2>/dev/null \
        | grep -oP 'php\K[0-9]+\.[0-9]+(?=-cli)' \
        | sort -V \
        | awk -F. '($1 > 8) || ($1 == 8 && $2 >= 4) {print; exit}'
}

# Adds the ondrej/php PPA, falling back to pinning the 'noble' (24.04) repo
# if this Ubuntu release is not yet supported by the PPA.
_add_ondrej_ppa() {
    apt-get install -y -qq software-properties-common curl gnupg

    # Attempt normal PPA add (suppresses the 404 noise to stderr)
    LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
    apt-get update -qq 2>/dev/null || true

    if [[ -n "$(_find_php84plus)" ]]; then
        success "ppa:ondrej/php added successfully"
        return 0
    fi

    # PPA has no release for this Ubuntu version yet (common for development releases).
    # Pin the 'noble' (24.04 LTS) packages — they are ABI-compatible.
    local CODENAME
    CODENAME="$(. /etc/os-release && echo "${UBUNTU_CODENAME:-${VERSION_CODENAME}}")"
    warn "ppa:ondrej/php has no release for Ubuntu '${CODENAME}'."
    warn "Pinning ondrej/php noble (24.04) packages as a fallback."

    # Import signing key
    curl -fsSL \
        "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C" \
        2>/dev/null \
        | gpg --dearmor -o /etc/apt/trusted.gpg.d/ondrej-php.gpg 2>/dev/null \
        || apt-key adv --keyserver keyserver.ubuntu.com \
               --recv-keys 14AA40EC0831756756D7F66C4F4EA0AAE5267A6C 2>/dev/null \
        || true

    # Add noble source (overwrites any bad entry from the failed add-apt-repository)
    cat > /etc/apt/sources.list.d/ondrej-php-noble.list <<'SRCLIST'
deb https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main
deb-src https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main
SRCLIST

    apt-get update -qq 2>/dev/null || true

    if [[ -n "$(_find_php84plus)" ]]; then
        success "PHP 8.4 available via ondrej/php noble packages"
    else
        error "Could not find PHP 8.4+ after all attempts."
        error "Manual fix:  echo 'deb https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main'"
        error "             >> /etc/apt/sources.list.d/ondrej-php-noble.list"
        error "             && apt-get update && apt-get install php8.4 php8.4-mysql ..."
        exit 1
    fi
}

_ensure_php84() {
    # 1. Check if PHP >= 8.4 already exists in current apt cache (covers PHP 8.5, 8.6, etc.)
    local found_ver
    found_ver="$(_find_php84plus)"
    if [[ -n "$found_ver" ]]; then
        PHP_VER="$found_ver"
        success "PHP ${PHP_VER} (>= 8.4) found in current repos — no PPA needed"
        return 0
    fi

    # 2. Not found — add ondrej/php (with noble fallback if this distro isn't in PPA yet)
    _add_ondrej_ppa

    found_ver="$(_find_php84plus)"
    if [[ -n "$found_ver" ]]; then
        PHP_VER="$found_ver"
        info "Will install PHP ${PHP_VER}"
    fi
}

if [[ "$NEED_ONDREJ" == "true" ]]; then
    header "Step 2/8 — Ensure PHP >= 8.4 (via ppa:ondrej/php)"
    _ensure_php84
else
    header "Step 2/8 — PHP >= 8.4 (Ubuntu ${UBUNTU_VERSION} repos)"
    # Check for PHP >= 8.4 (Ubuntu 26.04 may ship PHP 8.5, not 8.4)
    found_ver="$(_find_php84plus)"
    if [[ -n "$found_ver" ]]; then
        PHP_VER="$found_ver"
        success "PHP ${PHP_VER} confirmed in Ubuntu ${UBUNTU_VERSION} repos"
    else
        warn "PHP 8.4+ not found in default repos — trying ppa:ondrej/php"
        _ensure_php84
    fi
fi

# ── Step 3 — Install packages ─────────────────────────────────────────────────
header "Step 3/8 — Install Apache, MariaDB, PHP ${PHP_VER}"

PHP_PKGS=(
    "php${PHP_VER}"
    "php${PHP_VER}-cli"
    "php${PHP_VER}-mysql"     # PDO + MySQLi
    "php${PHP_VER}-intl"      # intl (recommended by MRBS)
    "php${PHP_VER}-mbstring"  # mbstring (recommended by MRBS)
    "php${PHP_VER}-curl"      # curl (external tz updates)
    "php${PHP_VER}-gd"        # gd  (QR code image generation)
    "php${PHP_VER}-xml"       # xml (iCalendar / PHPMailer)
    "libapache2-mod-php${PHP_VER}"
)

EXTRA_PKGS=(
    apache2
    mariadb-server
    rsync
    ufw
)

apt-get install -y -qq "${EXTRA_PKGS[@]}" "${PHP_PKGS[@]}"
success "Apache2, MariaDB, PHP ${PHP_VER} + extensions installed"

# Verify required PHP extensions
info "Verifying PHP extensions..."
MISSING_EXT=()
for EXT in pdo pdo_mysql intl mbstring; do
    if ! php -m 2>/dev/null | grep -qi "^${EXT}$"; then
        MISSING_EXT+=("$EXT")
    fi
done
if [[ ${#MISSING_EXT[@]} -gt 0 ]]; then
    warn "Missing PHP extensions: ${MISSING_EXT[*]}"
    warn "The application may not work correctly."
else
    success "All required PHP extensions present"
fi

# ── Step 4 — Configure MariaDB ────────────────────────────────────────────────
header "Step 4/8 — Configure MariaDB"

systemctl enable mariadb --quiet
systemctl start mariadb
success "MariaDB service started"

# Secure MariaDB: set root password, remove anonymous users and test DB
mysql -u root <<_SQL
ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY '${DB_ROOT_PASS}';
DELETE FROM mysql.user WHERE User='' OR (User='root' AND Host NOT IN ('localhost','127.0.0.1','::1'));
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
_SQL
success "MariaDB root secured"

# Create MRBS database + user
mysql -u root -p"${DB_ROOT_PASS}" <<_SQL
CREATE DATABASE IF NOT EXISTS \`${MRBS_DB_NAME}\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MRBS_DB_USER}'@'localhost'
    IDENTIFIED BY '${MRBS_DB_PASS}';
GRANT ALL PRIVILEGES ON \`${MRBS_DB_NAME}\`.* TO '${MRBS_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
_SQL
success "Database '${MRBS_DB_NAME}' and user '${MRBS_DB_USER}' created"

# Import schema
if [[ -f "$SQL_FILE" ]]; then
    mysql -u "${MRBS_DB_USER}" -p"${MRBS_DB_PASS}" "${MRBS_DB_NAME}" < "$SQL_FILE"
    success "Schema imported from $(basename "$SQL_FILE")"
else
    warn "tables.my.sql not found at: $SQL_FILE"
    warn "Import the schema manually after installation:"
    warn "  mysql -u ${MRBS_DB_USER} -p ${MRBS_DB_NAME} < tables.my.sql"
fi

# ── Step 5 — Deploy MRBS files ────────────────────────────────────────────────
header "Step 5/8 — Deploy MRBS files → ${MRBS_WEBROOT}"

mkdir -p "${MRBS_WEBROOT}"
rsync -a --delete --exclude='config.inc.php' "${WEB_SRC}/" "${MRBS_WEBROOT}/"
chown -R www-data:www-data "${MRBS_WEBROOT}"
find "${MRBS_WEBROOT}" -type d -exec chmod 755 {} \;
find "${MRBS_WEBROOT}" -type f -exec chmod 644 {} \;
success "Files deployed ($(find "${MRBS_WEBROOT}" -type f | wc -l) files)"

# ── Step 6 — Write config.inc.php ─────────────────────────────────────────────
header "Step 6/8 — Generate config.inc.php"

CONFIG_FILE="${MRBS_WEBROOT}/config.inc.php"

# Only write if it doesn't already exist (rsync excluded it above)
if [[ -f "$CONFIG_FILE" ]]; then
    warn "config.inc.php already exists — leaving it untouched."
    warn "Verify these settings are correct:"
    warn "  \$db_database = \"${MRBS_DB_NAME}\";"
    warn "  \$db_login    = \"${MRBS_DB_USER}\";"
else
    # Escape single quotes in password for PHP single-quoted string
    ESCAPED_DB_PASS="${MRBS_DB_PASS//\'/\'}"

    cat > "$CONFIG_FILE" <<PHP
<?php // -*-mode: PHP; coding:utf-8;-*-
declare(strict_types=1);
namespace MRBS;

use IntlDateFormatter;

require_once 'lib/autoload.inc';

/******************************************************************************
 * MRBS Configuration
 * Generated by install.sh ${INSTALLER_VERSION} on $(date '+%Y-%m-%d %H:%M:%S %Z')
 *
 * To customise further, copy lines from systemdefaults.inc.php or
 * areadefaults.inc.php into this file — do NOT edit those files directly.
 ******************************************************************************/

/**********
 * Timezone
 **********/
\$timezone = '${SITE_TIMEZONE}';

/*******************
 * Database settings
 ******************/
\$dbsys        = 'mysql';
\$db_host       = 'localhost';
\$db_database   = '${MRBS_DB_NAME}';
\$db_login      = '${MRBS_DB_USER}';
\$db_password   = '${ESCAPED_DB_PASS}';
\$db_tbl_prefix = '${MRBS_DB_TBL_PREFIX}';
\$db_persist    = false;

PHP

    if [[ "$ENABLE_MODERN_THEME" == "true" ]]; then
        cat >> "$CONFIG_FILE" <<'PHP'
/**********
 * Theme
 **********/
$theme = 'modern';

PHP
    fi

    chown www-data:www-data "$CONFIG_FILE"
    chmod 640 "$CONFIG_FILE"   # www-data readable, not world-readable (has DB password)
    success "config.inc.php written (mode 640)"
fi

# ── Step 7 — Configure Apache ─────────────────────────────────────────────────
header "Step 7/8 — Configure Apache"

# Enable required modules
a2enmod rewrite         > /dev/null 2>&1
a2enmod headers         > /dev/null 2>&1
a2dissite 000-default   > /dev/null 2>&1 || true

# Write .htaccess — deny directory listing and direct access to config files
cat > "${MRBS_WEBROOT}/.htaccess" <<'HTACCESS'
Options -Indexes
DirectoryIndex index.php

# Block direct access to sensitive PHP include files
<FilesMatch "\.(inc|sql)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Allow pretty URLs
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
</IfModule>
HTACCESS
chown www-data:www-data "${MRBS_WEBROOT}/.htaccess"

# Write virtual host
cat > "/etc/apache2/sites-available/${APACHE_VHOST_CONF}" <<VHOST
<VirtualHost *:80>
    ServerName   ${SITE_HOST}
    ServerAdmin  ${ADMIN_EMAIL}
    DocumentRoot ${MRBS_WEBROOT}

    <Directory ${MRBS_WEBROOT}>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    ErrorLog  \${APACHE_LOG_DIR}/mrbs_error.log
    CustomLog \${APACHE_LOG_DIR}/mrbs_access.log combined
</VirtualHost>
VHOST

a2ensite "$APACHE_VHOST_CONF" > /dev/null 2>&1
systemctl enable apache2 --quiet
systemctl restart apache2
success "Apache configured — vhost: /etc/apache2/sites-available/${APACHE_VHOST_CONF}"

# ── Step 8 — Firewall and optional SSL ────────────────────────────────────────
header "Step 8/8 — Firewall & SSL"

# UFW firewall
if command -v ufw &>/dev/null; then
    ufw allow 'Apache Full' > /dev/null 2>&1

    # Enable ufw only if it's not already active to avoid disrupting SSH
    UFW_STATUS="$(ufw status 2>/dev/null | head -1)"
    if [[ "$UFW_STATUS" == "Status: inactive" ]]; then
        # Allow SSH before enabling so we don't lock ourselves out
        ufw allow OpenSSH > /dev/null 2>&1
        ufw --force enable > /dev/null 2>&1
        success "UFW enabled (OpenSSH + Apache Full allowed)"
    else
        success "UFW already active — added Apache Full rule"
    fi
else
    warn "ufw not found — skipping firewall configuration"
fi

# Optional SSL via Certbot
if [[ "$ENABLE_SSL" == "true" ]]; then
    info "Installing Certbot..."
    apt-get install -y -qq certbot python3-certbot-apache

    if certbot --apache \
            -d "$SITE_HOST" \
            --non-interactive \
            --agree-tos \
            --email "$ADMIN_EMAIL" \
            --redirect; then
        success "SSL certificate installed for ${SITE_HOST}"
        HTTPS_URL="https://${SITE_HOST}/"
    else
        warn "Certbot failed (DNS may not be pointing here yet)."
        warn "Run manually when ready:  certbot --apache -d ${SITE_HOST}"
        HTTPS_URL=""
    fi
else
    HTTPS_URL=""
fi

# Logrotate entry for MRBS logs
cat > /etc/logrotate.d/mrbs <<LOGROTATE
${MRBS_WEBROOT}/logs/*.log {
    weekly
    missingok
    rotate 8
    compress
    delaycompress
    notifempty
    create 640 www-data adm
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
LOGROTATE

# ── Done — Print summary ──────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}${BOLD}║   MRBS ${MRBS_VERSION} installed successfully!          ║${NC}"
echo -e "${GREEN}${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${BOLD}Access URL     :${NC}  http://${SITE_HOST}/"
[[ -n "$HTTPS_URL" ]] && echo -e "  ${BOLD}Secure URL     :${NC}  ${HTTPS_URL}"
echo -e "  ${BOLD}Web root       :${NC}  ${MRBS_WEBROOT}"
echo -e "  ${BOLD}Config file    :${NC}  ${MRBS_WEBROOT}/config.inc.php"
echo -e "  ${BOLD}Apache vhost   :${NC}  /etc/apache2/sites-available/${APACHE_VHOST_CONF}"
echo -e "  ${BOLD}PHP version    :${NC}  $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "${PHP_VER}")"
echo -e "  ${BOLD}Database       :${NC}  ${MRBS_DB_NAME} @ localhost"
echo ""
echo -e "  ${YELLOW}${BOLD}Next steps:${NC}"
echo -e "  1. Open http://${SITE_HOST}/ to confirm the installation"
echo -e "  2. Log in and go to ${BOLD}Admin → Rooms${NC} to create areas and rooms"
echo -e "  3. For e-mail notifications, add mail settings to config.inc.php"
echo -e "     (reference: ${MRBS_WEBROOT}/systemdefaults.inc.php, mail section)"
echo ""
echo -e "  ${CYAN}To reconfigure, edit:${NC}  ${MRBS_WEBROOT}/config.inc.php"
echo -e "  ${CYAN}To uninstall, run:${NC}    sudo bash ${SCRIPT_DIR}/uninstall.sh"
echo ""
