# OpenTofu

OpenTofu definitions for Incus-managed infrastructure belong here.

Do not add provider credentials or state files to Git. Before introducing real resources, verify the installed Incus/OpenTofu versions and provider behavior, then document the chosen provider version and backend strategy.

Expected scope:

- virtual networks / ACLs
- VM definitions
- CPU/RAM/storage allocations
- NIC attachment
- profiles

`tofu apply` is approval-gated and must not run from GitHub Actions.
