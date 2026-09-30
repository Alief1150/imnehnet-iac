#!/usr/bin/env bash
# IMNEHNET queue worker runner.
#
# Infrastructure work (OpenTofu, Ansible, Incus) must never run inside an HTTP
# request, so every lifecycle action is a queued job. Without a worker those
# jobs sit in the jobs table forever and the UI would show PROVISIONING for
# good. This script is the worker; the user crontab starts it at boot.
set -uo pipefail

APP_DIR="/home/iaas/projects/webportal-iaas"
PID_FILE="$APP_DIR/storage/framework/queue-worker.pid"
LOG_DIR="$APP_DIR/storage/logs"

mkdir -p "$LOG_DIR"
cd "$APP_DIR" || exit 1

# Refuse to start a second worker: two workers on the database queue would
# race for the same OpenTofu working directory.
if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
  echo "queue worker sudah berjalan dengan pid $(cat "$PID_FILE")" >&2
  exit 0
fi

echo $$ > "$PID_FILE"

# --tries=1: a lifecycle operation is not safe to retry blindly. The job marks
# the record as ERROR and the operator decides, which is what the spec asks
# for. --sleep=3 keeps the database quiet when the queue is empty.
exec php artisan queue:work \
  --queue=default \
  --tries=1 \
  --backoff=5 \
  --sleep=3 \
  --max-time=3600 \
  --max-jobs=500 \
  --verbose
