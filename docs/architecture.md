# Target Architecture

## Scope and authority

This document describes the current target architecture. Runtime facts belong in
`docs/status.md`; historical and superseding choices belong in
`docs/decisions.md`. The final reviewed proposal is an academic design reference,
not automatic proof that every component is deployed.

## High-level topology

```text
Admin / Lecturer
       |
   Tailscale / management access
       |
+----------------------------------------------------------------+
| Physical host: server-imnehnet / hostname imnehnet             |
| Linux Mint + KVM/QEMU + libvirt                                |
|                                                                |
|  +------------------------+   +------------------------------+  |
|  | IAAS-MGMT              |   | LAB-ENV                      |  |
|  | 192.168.100.10         |   | 192.168.100.20               |  |
|  |                        |   |                              |  |
|  | OpenTofu               |-->| Incus API / runtime          |  |
|  | Ansible                |-->| incusbr0 10.20.0.0/24        |  |
|  | MySQL                  |   |   +-- student container A    |  |
|  | Laravel + Filament (*) |   |   +-- student container B    |  |
|  | Prometheus/Grafana (*) |   |   +-- ...                    |  |
|  | Wazuh (*)              |   |                              |  |
|  +------------------------+   +------------------------------+  |
+----------------------------------------------------------------+

(*) target component; consult docs/status.md before treating it as deployed.
```

Cloudflare WARP is an outbound-connectivity workaround for the physical host when
required by the upstream network. It is not part of the IaaS control plane.

## Architectural intent

The project provides isolated, repeatable student cybersecurity environments on a
single constrained physical server. Two Ubuntu Server VMs provide a management/lab
boundary. Incus system containers inside `LAB-ENV` provide lower-overhead student
environments than one full VM per student.

The design follows these principles:

- default-deny communication between student environments;
- no direct student access to the Incus API/socket or management plane;
- declarative provisioning through OpenTofu;
- idempotent post-provisioning configuration through Ansible;
- asynchronous backend orchestration for long-running lifecycle operations;
- explicit distinction between desired orchestration status and actual runtime
  status;
- measured capacity limits rather than promised concurrency without evidence.

## Layer responsibilities

| Layer | Component | Responsibility |
| --- | --- | --- |
| Physical host | Linux Mint | Host OS, storage, networking, remote administration |
| VM execution | KVM/QEMU + libvirt | Execute and isolate `IAAS-MGMT` and `LAB-ENV` |
| Management plane | `IAAS-MGMT` | OpenTofu, Ansible, MySQL, planned backend/API and operational services |
| Lab plane | `LAB-ENV` | Incus daemon, images, profiles, bridge, ACLs and student system containers |
| Provisioning | OpenTofu + Incus provider | Create/change/delete Incus resources and resource limits |
| Configuration | Ansible | Configure users, packages, services and bootstrap state after provisioning |
| Portal/backend | Laravel REST API + Filament | Authentication, authorization, lifecycle requests, status and administration |
| Database | MySQL | User/environment mapping, desired status, runtime status and operation metadata |
| Security monitoring | Wazuh | Collect and analyze security events |
| Resource monitoring | Prometheus + Grafana | Collect and visualize infrastructure/resource metrics |
| Remote management | Tailscale + MagicDNS | Private administrative access |

OpenTofu must not perform guest-configuration work that belongs to Ansible. The
Laravel backend must orchestrate OpenTofu and Ansible rather than reimplementing
their declarative/configuration responsibilities.

## Physical server baseline

The physical server remains the only compute node and therefore a single point of
failure. The following baseline was verified directly on 2026-09-19:

| Component | Verified specification | Architectural implication |
| --- | --- | --- |
| CPU | Intel Core i5-4460, 1 socket, 4 cores/4 threads, 3.20 GHz base | CPU-heavy concurrent labs will contend; limits and staged testing are required |
| Virtualization | Intel VT-x/EPT/VPID, `kvm_intel`, `/dev/kvm` | Hardware-accelerated KVM VMs are supported |
| Memory | Approximately 15.52 GiB usable RAM, 2 GiB swap | RAM is the primary capacity constraint; swap is not normal capacity |
| System disk | 240 GB-class Crucial BX500 SSD | Keep active VM disks and latency-sensitive services on SSD with free-space reserve |
| Data disks | Two 1 TB-class internal HDDs at `/hdd1` and `/hdd2` | May hold data/backup staging after policy approval; neither is an off-host backup |
| Network | Realtek wired Ethernet, observed at 100 Mb/s full duplex | Image transfer and multi-user throughput can bottleneck; gigabit must be verified |
| Host OS | Linux Mint 22.2 x86-64 | Supplies KVM/libvirt and administrative tooling |

The design is a prototype, not a highly available cluster. Loss of the host,
power, system disk or management network stops all project services.

## Capacity model

Capacity must be controlled at two levels:

1. VM allocations for `IAAS-MGMT` and `LAB-ENV` must leave enough RAM and CPU for
   the Linux Mint host.
2. Every student container must have CPU, memory and storage limits, and container
   concurrency must be increased only after measured load tests.

The reference proof-of-concept uses a one-vCPU, one-GiB container. That is a test
value, not a guaranteed per-user production allocation. The project does not claim
a maximum simultaneous-user count before functional, performance and scalability
tests are complete.

## Management plane

`IAAS-MGMT` is the automation/controller boundary. Its target responsibilities are:

- run reviewed OpenTofu commands against the trusted `lab-env` Incus remote;
- run Ansible against `LAB-ENV` and selected containers through a constrained,
  non-interactive wrapper;
- store environment mappings and workflow/runtime states in MySQL;
- host the Laravel REST API and Filament administrative panel;
- queue long-running provisioning/configuration operations;
- reconcile database status against Incus runtime status;
- host or coordinate Wazuh and Prometheus/Grafana components as capacity allows.

The portal must not expose shell commands, Incus credentials, OpenTofu state, or
raw privileged sockets to browsers.

## Lab plane

`LAB-ENV` runs Incus and provides:

- `incusbr0` as the internal container bridge;
- reusable images and profiles;
- per-environment CPU, RAM and storage limits;
- ACL and nftables enforcement;
- lifecycle operations received only from the approved management path;
- system containers that students can reset or recreate without modifying the VM
  boundary itself.

Each student environment receives its own instance name, internal address and
published access mapping. East-west traffic is denied unless explicitly required
by an exercise.

## Backend lifecycle model

The intended CREATE flow is:

```text
request/auth/authorization
  -> create database operation: PROVISIONING
  -> reviewed OpenTofu operation
  -> consume non-sensitive OpenTofu outputs
  -> database status: CONFIGURING
  -> Ansible bootstrap
  -> health/identity validation
  -> database status: READY
```

Lifecycle endpoints target CREATE, START, STOP, RESET and DELETE. Operations must
be queued, logged and idempotent where possible. Failure must set a sanitized error
state without storing credentials or dumping state contents.

The multi-user OpenTofu state strategy is not yet decided. Workspace-per-environment
must not be implemented as an assumed standard until alternatives are evaluated and
the decision is appended to `docs/decisions.md`.

## Security boundaries

- Tailscale is the private administrative overlay; SSH, Incus management, database
  administration, Wazuh and Grafana must not be exposed directly to the Internet.
- Incus API trust material and socket access remain server-side.
- Student access is limited to assigned published SSH/web/terminal paths.
- Container east-west traffic and container-to-management traffic are default-deny.
- Telemetry and required application flows are explicit exceptions.
- Firewall, ACL, route and bridge changes require a reviewed diff, a rollback path,
  and confirmation that SSH/Tailscale administration remains reachable.
- OpenTofu state, `.env`, tokens, passwords, SSH private keys and raw audit data
  never enter Git.

## Readiness gates

Before the platform is described as complete:

1. Decide and document the multi-user OpenTofu state/resource model.
2. Generalize and test Ansible bootstrap inputs and machine-readable results.
3. Build Laravel/Filament with authentication, authorization, queue workers and
   sanitized operation logs.
4. Verify all lifecycle operations and status reconciliation.
5. Implement and prove default-deny policy with explicit allowed/blocked tests.
6. Verify Wazuh and Prometheus/Grafana ingestion and retention limits.
7. Test backup and restore for database, configuration and required environment
   data.
8. Run controlled functional, security, performance, scalability and QoS tests.
9. Record observed versions and deployment evidence in `docs/status.md`.
