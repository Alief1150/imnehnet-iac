#!/usr/bin/env bash
# IMNEHNET P3 selective integration review bundle — read-only.
# Produces a PRIVATE report on IAAS-MGMT for review before applying changes.
set -euo pipefail
DEV=/tmp/imnehnet-btrfs-p3-dev
PROD=/home/iaas/projects/webportal-iaas
IAC=/home/iaas/imnehnet-iac
OUT="${1:-/tmp/imnehnet-p3-selective-diff-$(date -u +%Y%m%dT%H%M%SZ).txt}"
[[ -f "$DEV/webportal/artisan" && -f "$PROD/artisan" && -f "$IAC/tofu/main.tf" ]] || {
  echo "Required DEV/production source tree missing" >&2; exit 2;
}
[[ "$OUT" == /tmp/* ]] || { echo "Refusing report path outside /tmp" >&2; exit 2; }
umask 077
: > "$OUT"
{
  echo "# IMNEHNET BTRFS P3 SELECTIVE INTEGRATION DIFF"
  date -u -Is
  echo "# READ-ONLY. NO PRODUCTION MODIFICATIONS."
  echo "# WARNING: source code diffs may contain internal configuration. Review and redact secrets before sharing."
  echo "# NEVER COMMIT THIS REPORT TO THE PUBLIC REPO."
  echo
  echo "== REVISIONS =="
  git -C "$DEV" rev-parse HEAD
  git -C "$PROD" rev-parse HEAD
  git -C "$IAC" rev-parse HEAD
  echo
  echo "== GIT STATUS (tracked paths only, names) =="
  git -C "$PROD" diff --name-only | head -n 110
  echo
  for p in \
    app/Drivers/LiveLifecycleDriver.php \
    app/Jobs/ProvisionLabSession.php \
    app/Services/GuidedCtf/LabSessionManager.php \
    app/Services/OpenTofuService.php \
    config/infrastructure.php; do
    echo
    echo "===== WEBPORTAL $p ====="
    diff -u --label "PRODUCTION/$p" --label "DEV/$p" "$PROD/$p" "$DEV/webportal/$p" || [[ $? == 1 ]]
  done
  echo
  echo "===== MIGRATION (DEV VERSION; NOT APPLIED) ====="
  sed -n '1,140p' "$DEV/webportal/database/migrations/2026_11_26_000001_add_storage_pool_to_lab_environments.php"
  for p in tofu/main.tf tofu/variables.tf; do
    echo
    echo "===== IAC $p ====="
    diff -u --label "ACTIVE_IAC/$p" --label "DEV_IAC/$p" "$IAC/$p" "$DEV/$p" || [[ $? == 1 ]]
  done
  echo
  echo "===== INCUS POOL INVENTORY ====="
  for remote in lab-atk lab-env; do
    echo "-- $remote --"
    timeout 15s incus storage list "${remote}:" --format csv 2>&1 || true
    timeout 15s incus storage info "${remote}:imnehnet-btrfs-prod" 2>&1 | head -n 35 || true
  done
  echo "===== END REPORT ====="
} >> "$OUT" 2>&1
echo "REPORT=$OUT"
du -h "$OUT"
echo "Do not publish this report; inspect for secrets and upload it privately in the chat."
