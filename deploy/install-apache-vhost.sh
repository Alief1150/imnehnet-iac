#!/usr/bin/env bash
#
# Install the IMNEHNET portal vhost into Apache.
#
# MUST be run as root. The portal deliberately does not do this itself:
# /etc/apache2 is root-owned, and a web application that can rewrite the web
# server's configuration is a web application that can hand itself root.
#
#   sudo bash deploy/install-apache-vhost.sh
#
# What it changes, in order:
#
#   1. Enables the proxy modules. proxy_wstunnel is the one that matters: it is
#      what forwards the WebSocket upgrade the terminal needs. Without it the
#      terminal page loads and then hangs forever, which looks like a bug in
#      the portal rather than a missing module.
#
#   2. Lets Apache reach the application. /home/iaas is mode 0750, so the
#      www-data user cannot traverse into it at all and the vhost would fail
#      with a bare 403 that gives no hint about the cause. The fix grants
#      traverse only, never read: the home directory keeps its contents private
#      while the specific path below it becomes reachable.
#
#   3. Installs the vhost and reloads.
#
# It does NOT enable TLS. See deploy/README.md: a shell served over plain HTTP
# sends every keystroke, and the basic-auth password, in the clear across the
# LAN, so turning this on without HTTPS would be worse than leaving the
# terminal unusable.

set -euo pipefail

VHOST_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/apache/imnehnet-portal.conf"
APP_DIR=/home/iaas/projects/webportal-iaas
SLOT_DIR=/var/lib/imnehnet/terminal

if [ "$(id -u)" -ne 0 ]; then
    echo "This script must be run as root: sudo bash $0" >&2
    exit 1
fi

echo "==> Enabling proxy modules"
# proxy_wstunnel carries the WebSocket upgrade; proxy_http and proxy balance
# out the others. headers is needed so the upgrade and connection headers can
# be cleared, which is required before setting them again or Apache sends them
# twice and the client refuses the connection.
a2enmod proxy proxy_http proxy_wstunnel headers

echo "==> Making the application reachable by www-data"
# Traverse only. Without the x bit on the home directory Apache cannot get to
# the application at all, and the symptom is a 403 with no explanation.
chmod o+x /home/iaas
chmod o+x /home/iaas/projects

echo "==> Preparing the terminal slot directory"
install -d -o www-data -g iaas -m 0770 "$SLOT_DIR"
# The portal runs as iaas and rewrites a slot's password file when a terminal
# session starts. Apache reads them as www-data. The group is shared so one
# directory serves both without either side being able to read the other's
# private files elsewhere in the home directory.

echo "==> Checking storage is writable by php-fpm"
chgrp -R www-data "$APP_DIR/storage" "$APP_DIR/bootstrap"
find "$APP_DIR/storage" "$APP_DIR/bootstrap" -type d -exec chmod 2775 {} +

echo "==> Installing the vhost"
install -m 0644 "$VHOST_SRC" /etc/apache2/sites-available/imnehnet-portal.conf
a2ensite imnehnet-portal
a2dissite 000-default

echo "==> Validating and reloading"
apache2ctl configtest
systemctl reload apache2

cat <<'DONE'

Done. Check it:

  sudo apache2ctl configtest
  curl -I http://127.0.0.1/up

Before the terminal works, the portal must be able to write a slot's password
file, which is a feature that still has to be built. Until then /terminal/38000
will answer 401 with no file present, which is the correct answer for a slot
nobody has leased.

Note that the portal is still being served by "php artisan serve" on port 8000
until that process is stopped. Both can run at once; port 80 is the one to use.
DONE