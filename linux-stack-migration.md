# Linux Stack Migration

This repo currently runs on Docker with:

1. `mariadb:10.11`
2. `wordpress:php8.2-apache`
3. `wordpress:cli`
4. `phpmyadmin`
5. `mailpit`

The target host stack keeps the application shape the same:

1. Apache 2.4
2. distro PHP via generic `php` packages
3. MariaDB 10.11 or distro-equivalent
4. WP-CLI on host
5. Mailpit optional

## Current Repo Boundaries

Current Docker mounts:

1. `./wp -> /var/www/html`
2. `./plugins -> /var/www/html/wp-content/plugins`
3. `./themes -> /var/www/html/wp-content/themes`
4. `./db -> /var/lib/mysql`

Recommended host layout:

1. repo checkout stays at `/home/vladimir/wp`
2. runtime site root becomes `/srv/www/industriesalon`
3. WordPress core lives at `/srv/www/industriesalon/wp`
4. plugins sync from repo `plugins/`
5. themes sync from repo `themes/`
6. uploads stay writable under `/srv/www/industriesalon/wp/wp-content/uploads`

Normal workflow after cutover:

1. edit and commit in `/home/vladimir/wp`
2. deploy repo-owned files with `/home/vladimir/wp/scripts/deploy-host.sh`
3. verify with `/home/vladimir/wp/scripts/host-wp.sh`
4. keep uploads and DB as host runtime state, not git content

## Host Packages

Install:

```bash
sudo apt update
sudo apt install -y \
  apache2 \
  mariadb-server \
  php \
  libapache2-mod-php \
  php-cli \
  php-mysql \
  php-curl \
  php-xml \
  php-mbstring \
  php-zip \
  php-gd \
  php-intl \
  imagemagick \
  php-imagick \
  rsync \
  curl
```

Install WP-CLI if missing:

```bash
curl -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php /tmp/wp-cli.phar --info
sudo install -m 0755 /tmp/wp-cli.phar /usr/local/bin/wp
```

## Filesystem Setup

```bash
sudo mkdir -p /srv/www/industriesalon/wp
sudo chown -R vladimir:www-data /srv/www/industriesalon
sudo chmod -R g+w /srv/www/industriesalon
```

Initial sync:

```bash
rsync -a /home/vladimir/wp/wp/ /srv/www/industriesalon/wp/
rsync -a /home/vladimir/wp/plugins/ /srv/www/industriesalon/wp/wp-content/plugins/
rsync -a /home/vladimir/wp/themes/ /srv/www/industriesalon/wp/wp-content/themes/
```

## Database Setup

Create DB and user:

```bash
sudo mariadb <<'SQL'
CREATE DATABASE IF NOT EXISTS wordpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'wpuser'@'localhost' IDENTIFIED BY 'wp_pass';
GRANT ALL PRIVILEGES ON wordpress.* TO 'wpuser'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Import from a Docker-era dump:

```bash
/home/vladimir/wp/scripts/host-restore-db.sh /home/vladimir/wp/backups/db_YYYY-MM-DD_HH-MM.sql
```

## Apache Setup

Install the provided configs:

```bash
sudo cp /home/vladimir/wp/ops/linux/apache-vhost.conf /etc/apache2/sites-available/industriesalon.conf
sudo cp /home/vladimir/wp/ops/linux/apache-port-8084.conf /etc/apache2/ports.conf.d-industriesalon-8084.conf
PHP_APACHE_CONF_DIR="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
sudo cp /home/vladimir/wp/ops/linux/php-overrides.ini /etc/php/${PHP_APACHE_CONF_DIR}/apache2/conf.d/99-industriesalon.ini
sudo a2enmod rewrite env
sudo a2dissite 000-default
sudo a2ensite industriesalon.conf
sudo sed -i '/^Listen 80$/d' /etc/apache2/ports.conf
sudo sed -i 's/^\s*Listen 443$/# Listen 443/' /etc/apache2/ports.conf
if ! grep -q 'IncludeOptional ports.conf.d-\*.conf' /etc/apache2/ports.conf; then echo 'IncludeOptional ports.conf.d-*.conf' | sudo tee -a /etc/apache2/ports.conf; fi
sudo systemctl restart apache2
```

The prepared host config runs Apache on internal port `8084`, because this machine already uses `nginx` on `80`.
Public cutover is handled by an nginx vhost that proxies the main host/IP to Apache while leaving other nginx-hosted apps intact.

## Application Config

`wp/wp-config.php` is already patched for dual Docker/host use:

1. DB constants are env-backed with Docker fallbacks
2. `WORDPRESS_HOME` and `WORDPRESS_SITEURL` are supported
3. `WORDPRESS_TABLE_PREFIX` can override `$table_prefix`

Reference snippet:

1. [ops/linux/wp-config.host-snippet.php](/home/vladimir/wp/ops/linux/wp-config.host-snippet.php:1)

## Validation

Run host WP-CLI:

```bash
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh core is-installed
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh plugin list
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh theme list
```

Then run repo verifiers against the host stack once host PHP/WP-CLI is live.

Suggested order:

```bash
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh iss-archive collections-verify
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh iss-archive objects-verify
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh iss-archive media-verify
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh iss-archive relations-verify
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh iss-archive browser-verify
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh iss-archive assertions-verify
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh iss-register place-state-check
SITE_ROOT=/srv/www/industriesalon /home/vladimir/wp/scripts/host-wp.sh iss-register contract-check
```

## Daily Deploy

Deploy repo-owned code and config from the git checkout into the runtime copy:

```bash
/home/vladimir/wp/scripts/deploy-host.sh
```

This syncs:

1. `wp/` except `wp-content/uploads/`
2. `plugins/`
3. `themes/`

It does not treat `/srv/www/industriesalon` as a git working copy.

If you want a local mirror of host uploads for backup or migration work:

```bash
/home/vladimir/wp/scripts/pull-host-uploads.sh
```

## Permission Cleanup

Before cutover, normalize ownership drift from Docker:

```bash
sudo chown -R vladimir:www-data /srv/www/industriesalon
sudo find /srv/www/industriesalon -type d -exec chmod 2775 {} \;
sudo find /srv/www/industriesalon -type f -exec chmod 0664 {} \;
```

Keep `wp-content/uploads` writable by Apache.

On this host, the minimal writable runtime paths are:

```bash
sudo install -d -m 2775 -o vladimir -g www-data \
  /srv/www/industriesalon/wp/wp-content/uploads \
  /srv/www/industriesalon/wp/wp-content/upgrade
sudo chown -R vladimir:www-data \
  /srv/www/industriesalon/wp/wp-content/uploads \
  /srv/www/industriesalon/wp/wp-content/upgrade
sudo chmod -R g+rwX \
  /srv/www/industriesalon/wp/wp-content/uploads \
  /srv/www/industriesalon/wp/wp-content/upgrade
```

## Docker Retirement

Do not remove Docker first.

Retire it only after:

1. host Apache serves the site
2. host WP-CLI works
3. archive and register verifiers pass
4. uploads and admin login work

Then archive or disable:

1. `docker-compose.yml`
2. container-only scripts
3. container-only operational habits

## Public Cutover On This Host

Install the nginx front-door config:

```bash
sudo cp /home/vladimir/wp/ops/linux/nginx-vhost.conf /etc/nginx/sites-available/industriesalon
sudo ln -sf /etc/nginx/sites-available/industriesalon /etc/nginx/sites-enabled/industriesalon
sudo nginx -t
sudo systemctl reload nginx
```

This leaves `inventory.lan` on its own nginx server block and moves the main host/IP to WordPress at:

```text
http://192.168.2.31
```
