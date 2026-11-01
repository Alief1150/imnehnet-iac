#!/usr/bin/env bash
# IMNEHNET queue worker runner.
#
# Infrastructure work must never run inside an HTTP request, so every lifecycle
# action is a queued job. Without a worker those jobs sit in the jobs table and
# the interface would show PROVISIONING for good.
#
# The worker runs until it is stopped deliberately. An earlier version set a
# maximum run time, which meant the worker quietly exited every hour and left
# the same failure in place as a job nobody was watching.
set -uo pipefail

APP_DIR="/home/iaas/projects/webportal-iaas"
PID_FILE="$APP_DIR/storage/framework/queue-worker.pid"

mkdir -p "$APP_DIR/storage/logs"
cd "$APP_DIR" || exit 1

# Refuse to start a second worker. Two workers on the database queue would race
# for the same OpenTofu working directory.
if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE" 2>/dev/null)" 2>/dev/null; then
  echo "queue worker sudah berjalan dengan pid $(cat "$PID_FILE")" >&2
  exit 0
fi

echo $$ > "$PID_FILE"
trap 'rm -f "$PID_FILE"' EXIT

# --tries=1: a lifecycle operation is not safe to retry blindly. The job marks
# the record as ERROR and an operator decides, which is what the specification
# asks for.
exec php artisan queue:work \
  --queue=default \
  --tries=1 \
  --backoff=5 \
  --sleep=3 \
  --max-jobs=0 \
  --max-time=0 \
  --verbose
