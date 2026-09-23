# IMNEHNET Project Context

## Mission

You are operating the IMNEHNET PBL infrastructure repository. Treat this repository as the authoritative source of truth for architecture, current state, decisions, IaC, configuration management, and operational runbooks.

Project title: **Rancang Bangun Model IaaS Berbasis Micro-segmentation untuk Meningkatkan Keamanan dan Skalabilitas Praktikum Keamanan Siber.**

## Target architecture

- One physical host.
- CPU currently known only as Intel Core i5 4th generation; exact model/core count must be verified before capacity claims.
- RAM target/current plan: 16 GB; verify the actual installed amount before sizing.
- Host OS currently used for prototype: Linux Mint.
- Virtualization: KVM/QEMU.
- Management layer: Incus.
- Infrastructure as Code: OpenTofu.
- Guest configuration: Ansible.
- Infrastructure monitoring: Zabbix.
- Security monitoring/SIEM: Wazuh.
- Remote management: Tailscale + MagicDNS.
- Host outbound connectivity workaround when required: Cloudflare WARP.
- GUI remote access for the current host: x11vnc over Tailscale.

## Planned VM roles

1. Ubuntu Server VM — Zabbix + Wazuh management/monitoring.
2. Ubuntu Desktop VM — client environment for students/lecturers.
3. Windows Server VM — PWA/application workload; optional/on-demand when host resources are constrained.

No lightweight container design is part of the current plan unless the user explicitly changes that decision.

## Planned network model

Default planned segments, not yet proof of deployment:

- MGMT: `10.10.10.0/24`
- CLIENT: `10.10.20.0/24`
- APP: `10.10.30.0/24`

Microsegmentation principle: **deny by default, allow only required flows**.

Expected policy direction:

- MGMT -> CLIENT/APP: allow only required administration/monitoring.
- CLIENT -> MGMT: deny by default; allow required telemetry only.
- CLIENT -> APP: allow only application ports required by the lab.
- APP -> MGMT: deny by default; allow required telemetry/log delivery only.
- East-west lab traffic: deny by default unless the exercise explicitly requires it.

Never present planned addresses or policies as deployed facts unless `docs/status.md` confirms deployment.

## Tool responsibilities

- OpenTofu creates/changes infrastructure objects: VM definitions, networks, NIC attachment, resource allocation, profiles, ACLs where supported.
- Ansible configures operating systems and applications after the infrastructure exists.
- Incus manages VM/network/storage abstractions.
- KVM/QEMU executes the virtual machines.

Do not blur these responsibilities in documentation or automation.

## Safety policy

Read-only inspection is allowed by default.

Before any command that can change live infrastructure, require explicit approval in the current user request. This includes, but is not limited to:

- `tofu apply`, `tofu destroy`, or resource import/state surgery
- VM create/delete/rebuild when data may be affected
- storage pool or volume deletion
- firewall, ACL, bridge, route, DNS, Tailscale, or WARP changes
- package removal or major OS upgrades
- Ansible playbooks that modify production/lab hosts
- service restart when it can interrupt remote access

Always run and show a plan/diff first when the tool supports it.

Never delete a VM, volume, state, or network because it appears unused without explicit user approval.

Do not change Tailscale/WARP/firewall routing in a way that could lock out remote administration without an explicit rollback path.

## Git and synchronization rules

GitHub is the shared knowledge center.

At the start of meaningful work:

```bash
git pull --rebase --autostash
```

After a meaningful repository change:

1. Update relevant documentation.
2. Update `docs/status.md` if runtime state changed.
3. Append to `docs/decisions.md` if an architectural decision changed.
4. Run validation.
5. Commit using a clear conventional-style message.
6. Push to GitHub using `./scripts/safe-sync.sh "<message>"`.

Do not create commits solely to manufacture GitHub contribution activity.

Never commit secrets. Never commit OpenTofu state.

## Required source-of-truth files

- `AGENTS.md`: durable project rules and architecture context.
- `docs/status.md`: current verified state only.
- `docs/architecture.md`: target architecture.
- `docs/networking.md`: segmentation and connectivity design.
- `docs/decisions.md`: architectural decision log.
- `docs/runbook.md`: tested operating procedures.

If reality differs from documentation, verify the live system first, then update the repository. Do not silently rewrite history.

## Communication style for Hermes

Use concise Indonesian unless the user asks otherwise. For commands, explain what will change and what the rollback is. Distinguish clearly between observed state, planned state, and assumptions.

## IMNEHNET Distributed Audit

The physical host, `IAAS-MGMT`, and `LAB-ENV` maintain node-local audit trails.
The physical host pulls their machine-readable reports over verified SSH and
normalizes the result to `Asia/Jakarta`.

When the operator asks for login, activity, change, or audit information such
as "show audit", "siapa yang login", "siapa yang ngedit", "aktivitas server",
or "audit hari ini", run:

```bash
~/.local/bin/imnehnet-audit-report-all
```

Use `--json` for machine-readable output and `--all-returned` only for
cross-timezone diagnostics. The default report represents the current WIB
calendar date and emits `NOACTIVITY` when a reachable node has no events. Node
polls run in parallel with a bounded timeout; report an `ERROR` row instead of
waiting indefinitely when a node does not respond.

Present the result as a table containing timestamp, destination node, actor,
source IP, Tailscale machine, Tailscale user, Linux user, action, and affected
resource. Actor identity comes from the accepted SSH public-key fingerprint;
Tailscale machine/user attribution comes only from a valid `tailscale whois`
mapping of the recorded source IP. LAN, console, local, or unresolvable sources
must remain `UNKNOWN / LOCAL`; never infer a person from a Linux username or
device label.

If the aggregator itself is unavailable, use the physical-host fallback:

```bash
sudo -n /usr/local/sbin/imnehnet-audit-report --today
```

Never invent missing information. Do not modify or delete audit logs unless
explicitly authorized.
