#!/usr/bin/env bash
#
# Keep the queue worker running.
#
# The worker used to be started once from a @reboot crontab entry and then left
# alone. It stopped at 13:37 with "Lost connection" and nothing restarted it, so
# every lifecycle action after that point sat in the database queue forever while
# the interface showed PROVISIONING. An environment record claiming work is in
# progress, with no worker and no container behind it, is exactly the invented
# state the portal is supposed to refuse to show.
#
# So the worker is supervised instead of started once. This loop restarts it
# whenever it exits, for any reason: a dropped database connection, a memory
# limit, an operator stopping it deliberately.
#
# It is deliberately not a systemd service. This runs as the portal user without
# root, which is the whole constraint the rest of the deployment works under.
#
#   bash scripts/queue-supervisor.sh

set -uo pipefail

APP_DIR="/home/iaas/projects/webportal-iaas"
WORKER="$APP_DIR/scripts/queue-worker.sh"
LOG_DIR="$APP_DIR/storage/logs"
PID_FILE="$APP_DIR/storage/framework/queue-supervisor.pid"
HEARTBEAT="$APP_DIR/storage/framework/queue-worker.heartbeat"

mkdir -p "$LOG_DIR" "$APP_DIR/storage/framework"

# Refuse to run twice: two supervisors would fight over one worker, and the whole
# point of the flock in the lifecycle service is that one operation at a time.
exec 9>"/tmp/imnehnet-queue-supervisor.lock"
if ! flock -n 9; then
    echo "supervisor sudah berjalan (lock di /tmp/imnehnet-queue-supervisor.lock)" >&2
    exit 0
fi

echo $$ > "$PID_FILE"
trap 'rm -f "$PID_FILE"' EXIT

cd "$APP_DIR" || exit 1

echo "supervisor mulai $(date -Is)"

while true; do
    # The timestamp is written before the worker starts rather than by the worker
    # itself, so a worker that dies immediately still leaves a readable trail of
    # when it was last attempted.
    date -Is > "$HEARTBEAT"

    bash "$WORKER"
    status=$?

    echo "worker berhenti dengan status $status pada $(date -Is); menyalakan ulang" >&2
    rm -f "$HEARTBEAT"

    # A short pause, so a failure that is not a crash does not become a tight loop
    # consuming the machine. Long enough not to hammer the database, short enough
    # that an operator does not notice the gap.
    sleep 5
done