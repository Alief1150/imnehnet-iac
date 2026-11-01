#!/usr/bin/env bash
#
# Put code changes into effect.
#
#   bash scripts/reload-app.sh
#
# The queue worker is a long-running process. It loads application classes when
# it starts and keeps them for its whole life, so a change to a service, a job or
# a model is not live until the worker is restarted. That is not a subtlety: a
# workspace deletion fix was committed, the integration test for it passed, and
# the delete still failed against the live server for forty minutes because the
# worker had been running since before the fix and never saw it.
#
# queue:restart is the graceful form. The worker finishes the job it is on, then
# exits, and the supervisor starts a replacement with the new code. Killing it
# outright would abandon a lifecycle operation mid-flight, and an OpenTofu apply
# abandoned half way through is exactly the state the lock and the ERROR status
# exist to make visible.

set -uo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1

echo "==> Clearing caches"
php artisan optimize:clear

echo "==> Asking the queue worker to restart"
php artisan queue:restart

echo "==> Waiting for the replacement worker"
OLD=""
for pid in $(pgrep -f 'queue:work' || true); do
    OLD="$OLD $pid"
done

for _ in $(seq 1 30); do
    running=""
    for pid in $(pgrep -f 'queue:work' || true); do
        case " $OLD " in
            *" $pid "*) ;;
            *) running="$running $pid" ;;
        esac
    done

    if [ -n "$(echo "$running" | tr -d ' ')" ]; then
        echo "    new worker:$running"
        break
    fi

    sleep 1
done

if ! pgrep -f 'queue:work' >/dev/null; then
    echo "No worker is running." >&2
    echo "The supervisor should start one; if it did not, check storage/logs/queue-supervisor.log." >&2
    exit 1
fi

echo
echo "Reload complete. PIDs now running:"
pgrep -af 'queue:work|queue-supervisor' | sed 's/^/    /'