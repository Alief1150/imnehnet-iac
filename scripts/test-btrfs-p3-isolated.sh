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

# A linked worktree may legitimately use detached HEAD.
# Require the exact local feature tip, not merely an ancestor or unrelated branch.
feature_ref="refs/remotes/origin/feature/ctf-btrfs-p3-staged-rollout"
if ! git -C "$repo_root" show-ref --verify --quiet "$feature_ref"; then
  echo "REFUSED: missing locally fetched origin feature ref." >&2
  exit 2
fi
head_sha="$(git -C "$repo_root" rev-parse HEAD)"
feature_sha="$(git -C "$repo_root" rev-parse "$feature_ref")"
if [[ "$head_sha" != "$feature_sha" ]]; then
  echo "REFUSED: HEAD differs from local feature tip; fetch and fast-forward DEV first." >&2
  exit 2
fi
if [[ -n "$(git -C "$repo_root" status --porcelain --untracked-files=no)" ]]; then
  echo "REFUSED: tracked modifications in DEV worktree." >&2
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
