#!/usr/bin/env bash
# ==============================================================================
#  MRBS 1.12.2 — Uninstaller
#  Removes everything install.sh created. Run as root: sudo bash uninstall.sh
# ==============================================================================
set -euo pipefail

RED='\033[0;31m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

if [[ $EUID -ne 0 ]]; then
    echo -e "${RED}[ERR]${NC} Run as root: sudo bash $0"
    exit 1
fi

MRBS_WEBROOT="${MRBS_WEBROOT:-/var/www/html/mrbs}"
MRBS_DB_NAME="${MRBS_DB_NAME:-mrbs}"
MRBS_DB_USER="${MRBS_DB_USER:-mrbs}"
APACHE_VHOST_CONF="mrbs.conf"

echo -e "\n${RED}${BOLD}MRBS Uninstaller${NC}"
echo -e "${YELLOW}This will remove:${NC}"
echo -e "  - Web root:     ${MRBS_WEBROOT}"
echo -e "  - Database:     ${MRBS_DB_NAME} (and user ${MRBS_DB_USER})"
echo -e "  - Apache vhost: /etc/apache2/sites-available/${APACHE_VHOST_CONF}"
echo ""
read -rp "Are you sure? Type YES to continue: " CONFIRM
[[ "$CONFIRM" != "YES" ]] && { echo "Aborted."; exit 0; }

read -srp "MariaDB root password: " DB_ROOT_PASS; echo ""

# Disable and remove Apache vhost
a2dissite "$APACHE_VHOST_CONF" > /dev/null 2>&1 || true
rm -f "/etc/apache2/sites-available/${APACHE_VHOST_CONF}"
systemctl reload apache2 2>/dev/null || true
echo -e "${CYAN}[OK]${NC} Apache vhost removed"

# Remove web root
if [[ -d "$MRBS_WEBROOT" ]]; then
    rm -rf "$MRBS_WEBROOT"
    echo -e "${CYAN}[OK]${NC} Web root removed: ${MRBS_WEBROOT}"
fi

# Drop database and user
mysql -u root -p"${DB_ROOT_PASS}" <<_SQL 2>/dev/null || true
DROP DATABASE IF EXISTS \`${MRBS_DB_NAME}\`;
DROP USER IF EXISTS '${MRBS_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
_SQL
echo -e "${CYAN}[OK]${NC} Database '${MRBS_DB_NAME}' and user '${MRBS_DB_USER}' dropped"

rm -f /etc/logrotate.d/mrbs
echo -e "${CYAN}[OK]${NC} Logrotate config removed"

echo -e "\nMRBS has been removed. Apache and MariaDB services are still running."
