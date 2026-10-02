#!/usr/bin/env bash
#
# Build the front-end assets.
#
# Use this instead of calling "npm run build" directly. The system Node on this
# host is 18, and Vite needs 24; the right Node is installed through nvm, which
# is only loaded by interactive and login shells. A build started from a cron
# job, a deploy script, or any other non-interactive context therefore silently
# used the wrong Node and failed with an ESM loader error that says nothing
# about the real cause.
#
# Loading nvm here rather than relying on the caller's shell is the whole point:
# it makes the build produce the same result regardless of who started it.
#
#   bash scripts/build-assets.sh

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"

if [ -s "$NVM_DIR/nvm.sh" ]; then
    # shellcheck disable=SC1091
    . "$NVM_DIR/nvm.sh"
    nvm use default >/dev/null 2>&1 || true
fi

REQUIRED_MAJOR=24
CURRENT_MAJOR="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"

if [ "$CURRENT_MAJOR" != "$REQUIRED_MAJOR" ]; then
    cat >&2 <<EOF
This build needs Node ${REQUIRED_MAJOR}. The node on PATH is ${CURRENT_MAJOR:-none}.

The correct version is installed through nvm, which this script loads. If it
still cannot be found, check that nvm is present at $NVM_DIR and that a version
is set with:

    nvm alias default ${REQUIRED_MAJOR}
EOF
    exit 1
fi

echo "Node $(node -v), npm $(npm -v)"
npm run build