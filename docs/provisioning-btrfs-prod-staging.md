# Provisioning-owned Btrfs production pool setup (STAGING)

**Scope:** LAB-ATK and LAB-ENV Incus storage only. The Cloud Architecture team owns IAAS-MGMT QCOW2/HDD1/TRIM remediation. DO NOT touch HDD1, active QCOW2 or existing Incus pools.

## Current state (verified 2026-11-26)
- Both remotes have `default` (DIR) and `imnehnet-btrfs-test` (6 GiB loop).
- `imnehnet-btrfs-prod` does not yet exist.
- LAB-ATK `imnehnet-gi-attacker-p3` alias fingerprint begins `62c78b24fd0d`.
- LAB-ENV `imnehnet-gi-victim-v1` fingerprint begins `8b12db08acbe`.
- The physical HDD2 has been reported to contain both LAB guest disks. Btrfs loop storage **does not change the physical HDD IOPS limit**.

## Stage A: check available storage first (read only)
From IAAS-MGMT:
```sh
incus storage list lab-atk:
incus storage list lab-env:
incus storage info lab-atk:default
incus storage info lab-env:default
incus image info lab-atk:imnehnet-gi-attacker-p3
incus image info lab-env:imnehnet-gi-victim-v1
```
From each guest, check `df -h /` and `free -h`. From the physical host check `df -h /hdd2`, available memory and I/O pressure. Halt if capacity or I/O is unhealthy.

## Stage B: create production pools only after size and physical capacity check
**Example starting size is 64GiB per guest, not a final capacity guarantee for 30 students.** This is a loop-backed pilot expansion on HDD2; `size` must be sized using observed per-student usage, number of concurrent students, filesystem overhead and reserve. These commands CREATE new pools; they do not alter existing pools:
```sh
incus storage create lab-atk:imnehnet-btrfs-prod btrfs size=64GiB
incus storage create lab-env:imnehnet-btrfs-prod btrfs size=64GiB
incus storage info lab-atk:imnehnet-btrfs-prod
incus storage info lab-env:imnehnet-btrfs-prod
```
Never use `--force`, `source.wipe=true`, or a path to a live QCOW2. Btrfs loop pools can grow with `incus storage set <remote>:<pool> size=<larger_size>` after capacity approval (refer to current Incus documentation). If either create fails, do not enable rollout. Investigate, do not automatically delete any pool.

## Stage C: deploy and test (only after DEV validation)
1. Reconcile production `feat/lab-provisioning` changes; don't checkout over uncommitted `tofu/main.tf` or `tofu/variables.tf`.
2. Review additive `storage_pool` migration and back up authoritative DB. Run migration in a **staging database** first. Verify existing rows get NULL and new rows save explicit `default` or `imnehnet-btrfs-prod`.
3. Check P3 alias metadata and expiry before using it. Verify images actually clone into new pool.
4. In isolated staging portal, set flag ON and create exactly one fresh staging pair. Verify Incus root storage pool for both, exact image fingerprints, terminal and ICMP pair; deny cross-student connectivity.
5. Complete Stop/Resume/Reset/Delete and inspect OpenTofu state and workspace cleanup; test rollback of feature flag for **new** labs while existing Btrfs instance still works.
6. Record end-to-end queue latency, provisioning duration and host HDD2 IO pressure. Only after pass, enable for the next production cohort; do not automatically recreate old DIR instances.

## Production config (do not commit .env)
```dotenv
IMNEHNET_CTF_BTRFS_ROLLOUT_ENABLED=false
IMNEHNET_CTF_BTRFS_POOL=imnehnet-btrfs-prod
IMNEHNET_CTF_ATTACKER_IMAGE=imnehnet-gi-attacker-p3
IMNEHNET_CTF_VICTIM_IMAGE=imnehnet-gi-victim-v1
```
Start OFF. Only turn ON after staging acceptance, capacity check, verified backups and a controlled queue boundary. Run `php artisan config:cache` / `php artisan queue:restart` only in the intended deployed portal and after safely draining active provisioning jobs. Do not run any queued provision job on test webportal .env with production credentials.

## Rollback
Disable feature flag for **future sessions**, cache config and restart queue safely. Persisted Btrfs sessions remain on Btrfs and must remain operational. Keep both pools and their image references intact, even if feature flag is OFF.
