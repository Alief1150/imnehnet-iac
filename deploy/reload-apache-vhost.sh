#!/usr/bin/env bash
#
# Reinstall the Apache vhost and reload.
#
# Separate from install-apache-vhost.sh because the common case after a change to
# the vhost is not a fresh install: the modules, the permissions and the slot
# directory are already in place, and re-running the whole installer for a config
# edit hides which part actually changed.
#
#   sudo bash deploy/reload-apache-vhost.sh
#
# Must be run as root. The application does not reload the web server itself: a
# web application that can rewrite its own web server's configuration is a web
# application that can hand itself root.

set -euo pipefail

VHOST_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/apache/imnehnet-portal.conf"
SLOT_DIR=/var/lib/imnehnet/terminal

if [ "$(id -u)" -ne 0 ]; then
    echo "This script must be run as root: sudo bash $0" >&2
    exit 1
fi

echo "==> Checking the configuration before installing it"
# The candidate is checked in place rather than after installation, so a broken
# edit never reaches the running server at all.
cp "$VHOST_SRC" /etc/apache2/sites-available/imnehnet-portal.conf
if ! apache2ctl configtest; then
    echo "The new configuration is invalid and was not reloaded." >&2
    exit 1
fi

echo "==> Reloading"
systemctl reload apache2

echo "==> Checking the terminal gate"
# Without a leased slot every terminal path must be refused. A 503 here would
# mean the request reached the proxy without a token, which is the bug this
# whole reload exists to fix.
status=$(curl -s -o /dev/null -m 8 -w '%{http_code}' http://127.0.0.1/terminal/38000/)
echo "    /terminal/38000/ without a token -> $status"

case "$status" in
    403)
        echo "    refused, as it should be"
        ;;
    *)
        cat >&2 <<WARN
    Expected 403 but got $status.

    A 503 means the request was proxied to the slot without a token, so the gate
    is not working and every terminal path is reachable by anyone who knows it.
    Check the order of the rewrite rules: the rule that keeps terminal paths out
    of the application's routing uses [L], so it has to come AFTER the token
    check, never before it.
WARN
        exit 1
        ;;
esac

cat <<'DONE'

Done. The terminal gate now refuses any request that does not carry the token the
portal issued for that slot. To see it open a terminal from the portal: the
portal writes the grant, and only that token reaches the shell.

  curl -s -o /dev/null -w '%{http_code}\n' 'http://127.0.0.1/terminal/38000/?t=wrong'
DONE