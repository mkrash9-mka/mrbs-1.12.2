# MRBS 1.12.2 — Ubuntu Server Deployment

Quick reference for deploying MRBS on a fresh Ubuntu 20.04 / 22.04 / 24.04 server.

## Quick start

```bash
# Copy this whole directory to the server, then:
sudo bash install.sh
```

The installer will prompt for passwords and configuration interactively.

---

## What `install.sh` does

| Step | Action |
|------|--------|
| 1 | `apt-get update` |
| 2 | Ensure PHP 8.4+ is available, adding `ppa:ondrej/php` if the distro repos don't have it (required — MRBS uses the `Pdo\Mysql` class introduced in PHP 8.4) |
| 3 | Install Apache2, MariaDB, PHP 8.4 + extensions |
| 4 | Secure MariaDB, create DB + user, import `tables.my.sql` |
| 5 | Deploy `web/` → web root (default `/var/www/html/mrbs`) |
| 6 | Generate `config.inc.php` with DB credentials & timezone |
| 7 | Write Apache virtual host, enable `mod_rewrite` + `mod_headers` |
| 8 | Configure UFW firewall, optionally install Let's Encrypt SSL |

PHP extensions installed: `pdo`, `pdo_mysql`, `intl`, `mbstring`, `curl`, `gd`, `xml`

---

## Environment variable overrides

Set these before running to skip the interactive prompts (useful for automated provisioning):

```bash
export MRBS_WEBROOT="/var/www/html/mrbs"
export MRBS_DB_NAME="mrbs"
export MRBS_DB_USER="mrbs"
export MRBS_DB_TBL_PREFIX="mrbs_"
sudo bash install.sh
```

Passwords and hostname are always prompted interactively (they are not safe to pass as env vars).

---

## Post-install configuration

Edit `/var/www/html/mrbs/config.inc.php` to customise MRBS.  
Copy any setting from `web/systemdefaults.inc.php` or `web/areadefaults.inc.php` into `config.inc.php` and change the value there — never edit the defaults files directly.

Common settings to configure after install:

```php
// Site name shown in the header
$mrbs_title = "My Company Meeting Rooms";

// Admin authentication (see AUTHENTICATION file for options)
$auth["type"] = "db";

// E-mail notifications (PHPMailer)
$mail_settings['admin_email']  = 'mrbs@example.com';
$mail_settings['from']         = 'mrbs@example.com';
$mail_settings['use_sendmail'] = false;
$mail_settings['smtp_host']    = 'smtp.example.com';
$mail_settings['smtp_port']    = 587;
$mail_settings['smtp_username'] = 'user';
$mail_settings['smtp_password'] = 'password';
```

---

## Uninstall

```bash
sudo bash uninstall.sh
```

Removes the web root, Apache vhost, and drops the database.  
MariaDB and Apache services themselves are left running.

---

## Manual SSL (after DNS propagates)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com --email admin@yourdomain.com
```

---

## Updating an existing installation

Use `update.sh` instead of re-running `install.sh`. Copy the new MRBS package
(with the updated `web/` directory) onto the server next to `update.sh`, then:

```bash
sudo bash update.sh
```

What it does:

| Step | Action |
|------|--------|
| 1 | Back up `config.inc.php`, `web/uploaded/`, and dump the database (optionally tar the full web root too) |
| 2 | Deploy the new `web/` files with `rsync`, preserving `config.inc.php` and `uploaded/` |
| 3 | Attempt the MRBS database schema upgrade automatically (or print manual instructions) |
| 4 | Restart Apache |

Environment variable overrides (same idea as `install.sh`):

```bash
export MRBS_WEBROOT="/var/www/html/mrbs"   # path to the existing install
export BACKUP_ROOT="/var/backups/mrbs"     # where backups are written
sudo bash update.sh
```

Rollback commands are printed at the end of the run, using the timestamped
backup directory created in step 1.
