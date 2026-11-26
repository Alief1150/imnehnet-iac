#!/usr/bin/env bash
# IMNEHNET P3/Btrfs isolated Laravel regression runner.
# Runs test database only; never invokes OpenTofu apply/destroy or Incus operations.
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
app="$repo_root/webportal"

case "$repo_root" in
  /home/iaas/imnehnet-iac|/home/iaas/projects/webportal-iaas)
    echo "REFUSED: production paths are not valid isolated test worktrees." >&2
    exit 2
    ;;
esac

if [[ ! -f "$app/artisan" || ! -x "$app/vendor/bin/pest" ]]; then
  echo "Missing Laravel artisan or Pest. Install DEV dependencies in isolated worktree." >&2
  exit 2
fi

if [[ "$(git -C "$repo_root" branch --show-current)" != "feature/ctf-btrfs-p3-staged-rollout" ]]; then
  echo "REFUSED: expected feature/ctf-btrfs-p3-staged-rollout branch." >&2
  exit 2
fi

cd "$app"
echo "=== IMNEHNET BTRFS P3 TARGETED REGRESSION ==="
echo "repo=$repo_root"
echo "commit=$(git rev-parse --short HEAD)"
echo "APP_ENV=testing, SQLite in-memory, sync queue, array cache"
echo "This does not provision or destroy containers."

APP_ENV=testing \
DB_CONNECTION=sqlite \
DB_DATABASE=:memory: \
ENVIRONMENT_CONNECTION=sqlite \
CACHE_STORE=array \
QUEUE_CONNECTION=sync \
./vendor/bin/pest \
  tests/Feature/GuidedCtf/MyLabsLifecycleTest.php \
  tests/Feature/GuidedCtf/DeleteLabSessionE2ETest.php \
  tests/Feature/GuidedCtf/BtrfsP3RolloutTest.php \
  tests/Feature/GuidedCtf/BtrfsP3LifecycleVariablesTest.php \
  --compact

echo "PASS: isolated P3/Btrfs targeted test process exited successfully."
