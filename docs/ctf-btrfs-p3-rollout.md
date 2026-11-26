# IMNEHNET CTF Btrfs + Golden Image P3 rollout

## Scope and ownership
**Provisioning:** feature flag, image selection, OpenTofu pool selection, lifecycle verification.
**Cloud architecture:** physical disk health, VM sizing, backups, Incus production pool creation, capacity approval.
This change does **not** migrate, recreate, or delete existing student containers.

## DEV (Git branch only)
- Rollout remains OFF unless IMNEHNET_CTF_BTRFS_ROLLOUT_ENABLED=true is explicitly configured.
- Legacy Kaneki UID pilot remains functional while the flag is OFF.
- Confirm deployed `tofu/main.tf` and `tofu/variables.tf` match the active server; server has local modifications not yet mirrored in GitHub.
- Validate PHP syntax, OpenTofu syntax, and available Incus remote image aliases before staging.
- Run `php -l app/Jobs/ProvisionLabSession.php`, `php -l config/infrastructure.php`, and `tofu fmt -check && tofu validate` in a safe working copy.
- **Do not** apply OpenTofu against the active default workspace.

## STAGING preconditions
- Physical-host disk I/O pressure and IAAS-MGMT full HDD1 issue reviewed by cloud architecture.
- `imnehnet-btrfs-prod` exists on **both** `lab-atk` and `lab-env`; verify via `incus storage info lab-atk:imnehnet-btrfs-prod` and `incus storage info lab-env:imnehnet-btrfs-prod`.
- Confirm `lab-atk:imnehnet-gi-attacker-p3` and `lab-env:imnehnet-gi-victim-v1` resolve to expected fingerprints in each Incus remote, and verify image expiry and root-disk image availability on production pool.
- Back up the *deployed* PHP, OpenTofu HCL, `.env`, and workspace state securely; do not paste secrets into chat.
- Ensure no active provisioning worker before deployment/config cache refresh; leave only ONE worker during staging.
- Validate initial database lifecycle status and quota for an **unused staging user**, never re-provision an existing session.

## STAGING environment values (not committed to Git)
```dotenv
IMNEHNET_CTF_BTRFS_ROLLOUT_ENABLED=true
IMNEHNET_CTF_BTRFS_POOL=imnehnet-btrfs-prod
IMNEHNET_CTF_ATTACKER_IMAGE=imnehnet-gi-attacker-p3
IMNEHNET_CTF_VICTIM_IMAGE=imnehnet-gi-victim-v1
```
These values are global to *new* CTF provisioning jobs. Use an isolated staging deployment or a controlled maintenance window; do not enable against live students until staging approval.

## Acceptance tests
1. Create a new lab pair for a staging account; verify one attacker on LAB-ATK and one victim on LAB-ENV.
2. Both instances must use the production Btrfs root disk and declared image aliases/fingerprints.
3. Confirm Ready and log timeline; verify `student` and `ctfstudent` can run `ping` to their paired victim. Test cross-student isolation is still DENIED.
4. Verify Start/Stop/Resume/Delete; inspect leftovers and OpenTofu workspace cleanup, never manually delete state.
5. Observe `/proc/pressure/io`, RAM/swap, web health, Laravel queue lag and failure logs. Stop rollout if latency/timeout or disk pressure regresses.
6. Run smoke test using at least two fresh student accounts and record durations separately from queue waiting time.

## PRODUCTION / rollback
- Cloud-architecture approval on disk capacity and operational monitoring is mandatory before production enabling.
- Reconcile local server OpenTofu modifications with this branch *before* merging or copying files. Do not `git reset --hard` or overwrite local state.
- With provisioning paused, deploy tested code, set env values, execute `php artisan config:cache`, then `php artisan queue:restart`.
- Enable for **new sessions only**; do not mutate existing workspaces/containers.
- To rollback new provisioning: set `IMNEHNET_CTF_BTRFS_ROLLOUT_ENABLED=false`, rerun config cache and queue restart after active jobs complete. Existing Btrfs instances remain on Btrfs and must continue their ordinary lifecycle; they are not converted to DIR.
- If necessary revert code to reviewed backup. Do **not** delete or recreate production pools while any instance or image still references them.
