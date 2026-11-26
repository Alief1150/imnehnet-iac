#!/usr/bin/env bash
# IMNEHNET Btrfs P3 production preflight (read-only; does NOT deploy).
set -euo pipefail

DEV_ROOT="/tmp/imnehnet-btrfs-p3-dev"
PROD_ROOT="/home/iaas/projects/webportal-iaas"
IAC_ROOT="/home/iaas/imnehnet-iac"

for d in "$DEV_ROOT" "$PROD_ROOT" "$IAC_ROOT"; do
  if [[ ! -d "$d" ]]; then
    echo "STOP: missing expected directory: $d" >&2
    exit 2
  fi
done
if [[ ! -f "$PROD_ROOT/artisan" ]]; then
  echo "STOP: production Laravel artisan not found." >&2
  exit 2
fi

echo "=== IMNEHNET BTRFS P3 — PRODUCTION PRE-FLIGHT (READ ONLY) ==="
date -Is
echo "=== Git revisions and local changes (NO destructive git operations) ==="
for d in "$DEV_ROOT" "$PROD_ROOT" "$IAC_ROOT"; do
  echo "LOCATION: $d"
  git -C "$d" rev-parse --short HEAD 2>/dev/null || true
  git -C "$d" branch --show-current 2>/dev/null || true
  echo "tracked dirty file count:"
  git -C "$d" status --porcelain --untracked-files=no 2>/dev/null | wc -l
done

echo "=== App integration file fingerprints ==="
paths=(
  app/Drivers/LiveLifecycleDriver.php
  app/Jobs/ProvisionLabSession.php
  app/Services/GuidedCtf/LabSessionManager.php
  app/Services/OpenTofuService.php
  config/infrastructure.php
  database/migrations/2026_11_26_000001_add_storage_pool_to_lab_environments.php
)
for p in "${paths[@]}"; do
  printf '%s\n' "$p"
  for base in "$DEV_ROOT/webportal" "$PROD_ROOT"; do
    if [[ -f "$base/$p" ]]; then
      sha256sum "$base/$p" | awk '{ print $1 }'
    else
      echo MISSING
    fi
  done
  if [[ -f "$DEV_ROOT/webportal/$p" && -f "$PROD_ROOT/$p" ]]; then
    if cmp -s "$DEV_ROOT/webportal/$p" "$PROD_ROOT/$p"; then
      echo SAME
    else
      echo DIFFERENT — manual selective review required
    fi
  fi
done

echo "=== IaC source fingerprints (feature vs actual local IaC) ==="
for p in tofu/main.tf tofu/variables.tf; do
  echo "$p"
  for f in "$DEV_ROOT/$p" "$IAC_ROOT/$p"; do
    if [[ -f "$f" ]]; then sha256sum "$f" | awk '{ print $1 }'; else echo MISSING; fi
  done
done

echo "=== Verify required migration availability/status (no migrate executed) ==="
if command -v php >/dev/null 2>&1; then
  (cd "$PROD_ROOT" && php artisan migrate:status --no-ansi 2>&1 | grep -E '2026_11_26_000001_add_storage_pool|Migration name|^  [0-9]{4}_[0-9]{2}_[0-9]{2}' | tail -12) || true
fi
echo "=== Runtime feature flag presence only (never print secret or flag values) ==="
if [[ -f "$PROD_ROOT/.env" ]]; then
  for key in IMNEHNET_CTF_BTRFS_ROLLOUT_ENABLED IMNEHNET_CTF_BTRFS_POOL IMNEHNET_CTF_ATTACKER_IMAGE IMNEHNET_CTF_VICTIM_IMAGE; do
    if grep -Eq "^[[:space:]]*${key}=" "$PROD_ROOT/.env"; then echo "$key: PRESENT"; else echo "$key: ABSENT"; fi
  done
else
  echo ".env missing/unreadable"
fi

echo "=== Production live resources (metadata only) ==="
if command -v incus >/dev/null 2>&1; then
  for remote in lab-atk lab-env; do
    echo "$remote Btrfs pool"
    timeout 15s incus storage info "${remote}:imnehnet-btrfs-prod" 2>&1 | grep -E '^(name:|driver:|  total:|  used:|used_by:|error:)' || true
  done
fi
echo "=== Capacity (read-only) ==="
df -h "$PROD_ROOT" "$IAC_ROOT" | tail -n +2
free -h | head -n 3
echo "=== PREFLIGHT COMPLETE: NO FILE, DB, TOFU OR INCUS MUTATIONS ==="
