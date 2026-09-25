# IMNEHNET Project Context

## Mission

You are operating the IMNEHNET PBL infrastructure repository. Treat this repository as the authoritative source of truth for architecture, current state, decisions, IaC, configuration management, and operational runbooks.

Project title: **Rancang Bangun Model IaaS Berbasis Micro-segmentation untuk Meningkatkan Keamanan dan Skalabilitas Praktikum Keamanan Siber.**

## Target architecture

- One physical host.
- CPU verified as Intel Core i5-4460, 4 cores/4 threads.
- Installed RAM is approximately 15.52 GiB usable; sizing must remain conservative.
- Host OS currently used for prototype: Linux Mint.
- VM boundary: KVM/QEMU + libvirt.
- IAAS-MGMT: OpenTofu, Ansible, MySQL, and the planned Laravel/Filament backend.
- LAB-ENV: Incus host for isolated per-student system containers.
- Infrastructure as Code: OpenTofu with the Incus provider.
- Container/guest configuration: Ansible after provisioning.
- Infrastructure monitoring: Prometheus + Grafana.
- Security monitoring/SIEM: Wazuh.
- Remote management: Tailscale + MagicDNS.
- Host outbound connectivity workaround when required: Cloudflare WARP.
- GUI remote access for the current host: x11vnc over Tailscale.

## VM and workload roles

1. `IAAS-MGMT` Ubuntu Server VM — management/controller plane, automation, database, and planned portal/backend.
2. `LAB-ENV` Ubuntu Server VM — compute/lab plane running Incus system containers for student environments.

The 2026-09-25 architecture decision supersedes the earlier VM-per-workload/no-container design. KVM/QEMU still provides the two primary security boundaries; Incus system containers provide resource-efficient student environments inside `LAB-ENV`.

## Network model

Target and prototype-aligned networks:

- Management network: `192.168.100.0/24`.
- `IAAS-MGMT`: `192.168.100.10`.
- `LAB-ENV`: `192.168.100.20`.
- Incus internal network: `incusbr0`, `10.20.0.0/24`; bridge address `10.20.0.1`.

Microsegmentation principle: **deny by default, allow only required flows**.

Expected policy direction:

- IAAS-MGMT -> LAB-ENV/containers: allow only provisioning, configuration, lifecycle, monitoring, and administration flows.
- Student container -> IAAS-MGMT: deny by default; allow only explicitly required application/telemetry flows.
- Container east-west traffic: deny by default unless an exercise explicitly requires it.
- External student access: publish only assigned SSH/web/terminal paths; do not expose the Incus API/socket or the entire internal subnet.

Never present planned addresses or policies as deployed facts unless `docs/status.md` confirms deployment.

## Tool responsibilities

- OpenTofu creates/changes Incus objects: system containers, profiles, limits, NIC attachment, and lifecycle state.
- Ansible configures operating systems, users, packages, and applications after a container exists.
- Incus on `LAB-ENV` manages container, network, profile, ACL, image, and storage abstractions.
- KVM/QEMU + libvirt execute and isolate the `IAAS-MGMT` and `LAB-ENV` virtual machines.

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
