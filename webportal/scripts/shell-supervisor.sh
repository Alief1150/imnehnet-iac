#!/usr/bin/env bash
#
# Keep the shell runner alive.
#
# ── What this is for ──────────────────────────────────────────────────────────────────
#
# The administrative console opens a real shell on IAAS-MGMT, LAB-ENV or LAB-ATK. Those shells
# have to be started by the account that owns the host: it is the one that can sudo, and the
# only one that can read the key the lab nodes are reached with. The web runs as the web
# server's user, so it asks this runner to start them over a unix socket, and the runner decides
# for itself which nodes and which ports are allowed.
#
# So the portal has one dependency it cannot satisfy itself. That makes this the same shape of
# problem as the queue worker below, and it is supervised for the same reason: a terminal the
# portal can open but cannot start is an invented capability, and the only symptom is a page
# that says "a dedicated shell runner is required" on a system that has one installed.
#
# ── Deliberately not a systemd service ────────────────────────────────────────────────
#
# As with the queue worker: this runs as the portal user without root, which is the constraint
# the rest of the deployment works under. A unit file would need root to install and would
# outlive any decision to take the portal down.
#
#   bash scripts/shell-supervisor.sh          run it in the foreground
#   @reboot ... bash scripts/shell-supervisor.sh >> storage/logs/shell-supervisor.log 2>&1
#
set -uo pipefail

APP_DIR="/home/iaas/projects/webportal-iaas"
RUNNER="$APP_DIR/bin/shell-runner.php"
LOG_DIR="$APP_DIR/storage/logs"
SOCKET="${TERMINAL_RUNNER_SOCKET:-/var/lib/imnehnet/terminal/runner.sock}"

mkdir -p "$LOG_DIR"

if ! command -v php >/dev/null 2>&1; then
  echo "php is not on PATH" >&2
  exit 1
fi

if [ ! -f "$RUNNER" ]; then
  echo "the runner is missing: $RUNNER" >&2
  exit 1
fi

# The socket directory has to exist before the runner is asked to listen, and it has to be
# writable by this account and readable by the web server's group. Created with the setgid bit
# so a file the runner creates inside it inherits that group — without it, every socket would
# belong to the portal user alone and the web could not connect.
SOCKET_DIR="$(dirname "$SOCKET")"
if [ ! -d "$SOCKET_DIR" ]; then
  mkdir -p "$SOCKET_DIR" || { echo "cannot create $SOCKET_DIR" >&2; exit 1; }
  chmod 2770 "$SOCKET_DIR" 2>/dev/null
  chgrp www-data "$SOCKET_DIR" 2>/dev/null
fi

if [ ! -w "$SOCKET_DIR" ]; then
  echo "$SOCKET_DIR is not writable by $(id -un); the runner cannot create its socket there" >&2
  exit 1
fi

echo "supervisor: starting shell runner"

while true; do
  php "$RUNNER" >> "$LOG_DIR/shell-runner.out" 2>&1
  STATUS=$?

  # 0 is a clean stop, which means somebody asked it to stop. Restarting then would fight the
  # operator, so this waits and only reports.
  if [ "$STATUS" -eq 0 ]; then
    echo "$(date -Is) supervisor: the runner exited cleanly, not restarting" >&2
    break
  fi

  echo "$(date -Is) supervisor: the runner exited with $STATUS, restarting in 5s" >&2
  sleep 5
done