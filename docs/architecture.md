# Target Architecture

## High-level topology

```text
Admin / Lecturer
      |
   Tailscale
      |
      v
+------------------------------------------------------+
| Physical host: imnehnet                              |
| Linux host (prototype currently Linux Mint)          |
|                                                      |
| OpenTofu -> Incus -> KVM/QEMU                        |
|              |                                       |
|              +-- Ubuntu Server VM: Zabbix + Wazuh   |
|              +-- Ubuntu Desktop VM: client/lab      |
|              +-- Windows Server VM: PWA/app         |
|                                                      |
| Ansible -> guest OS/application configuration        |
| ACL/nftables/Incus networking -> microsegmentation   |
+------------------------------------------------------+
      |
   Internet
```

Cloudflare WARP is an operational connectivity workaround for the host when the campus network blocks required outbound traffic. It is not a core IaaS component.

## Design intent

The project demonstrates how one physical server can host multiple isolated laboratory environments while enforcing network-level microsegmentation and automating infrastructure lifecycle operations.

## Physical server baseline

The physical server is the only compute node in this prototype. It runs the host
operating system, hypervisor, virtual networking, storage access and the
management tools that create the VMs. Consequently, it is also a **single point
of failure**: if this host, its power supply, system disk or network connection
fails, every VM and every laboratory service on it becomes unavailable.

The following baseline was verified directly on the host on 2026-09-19. Values
shown here are the observed implementation baseline, not generic minimum
requirements:

| Component | Verified specification | Architectural implication |
| --- | --- | --- |
| Host identity | Hostname `imnehnet`; desktop chassis; Gigabyte B85M-DS3H-A mainboard; firmware F1 dated 2015-01-19 | Suitable for a prototype/lab, but no server-grade redundancy, remote BMC/IPMI or hot-swap capability has been verified. |
| CPU | Intel Core i5-4460, 1 socket, 4 cores/4 threads, 3.20 GHz base and 3.40 GHz maximum | VM CPU allocation must be conservative; avoid running every workload at peak load simultaneously. |
| CPU virtualization | Intel VT-x with EPT and VPID; `kvm_intel` loaded; `/dev/kvm` available | Hardware-accelerated KVM VMs are supported. Firmware virtualization must remain enabled. |
| Memory | 16,275,064 KiB, approximately 15.52 GiB usable RAM; 2 GiB swap | RAM is the primary capacity constraint. Reserve memory for the host and never count swap as normal VM capacity. |
| System disk | 240 GB-class Crucial BX500 SSD (`223.6G` visible), ext4 root filesystem; 186 GiB available when verified | Keep the OS and latency-sensitive active VM disks on SSD, while retaining free space for updates, logs and temporary snapshots. |
| Data disk 1 | 1 TB-class Seagate ST1000VM002 (`931.5G` visible), ext4 at `/hdd1`; 854 GiB available when verified | Candidate for VM data, ISO images or local backup staging after the storage policy is approved. |
| Data disk 2 | 1 TB-class Seagate ST1000DM003 (`931.5G` visible), ext4 at `/hdd2`; 870 GiB available when verified | Candidate for a separate data/backup function, but it is not an off-host disaster-recovery backup. |
| Network | Realtek RTL8111/8168-family wired Ethernet; link observed at 100 Mb/s full duplex | The current negotiated speed can bottleneck image transfers, backups and multi-user labs. Check the cable and switch port before assuming gigabit throughput. |
| Graphics | Intel integrated graphics from the 4th-generation Core platform | Adequate for host console/desktop administration; it is not intended as a GPU compute resource for guests. |
| Host OS | Linux Mint 22.2, x86-64, kernel 6.14.0-29-generic | The host supplies KVM and management tooling; graphical desktop services also consume part of the available resources. |

This is a low-spec, single-host design rather than a highly available production
cluster. A host failure stops all lab VMs. Windows Server is therefore optional
and should run on demand when CPU or memory pressure is high. Final per-VM sizing
must be based on measured idle and peak consumption, not only nominal capacity.

### Capacity interpretation and initial VM budget

The four physical CPU cores may be shared as virtual CPUs when workloads are
mostly idle, but allocating more vCPUs does not create more processing capacity.
Sustained CPU-heavy work in several VMs will cause contention. RAM should not be
overcommitted for the initial implementation because Wazuh, its indexer, Zabbix,
a graphical Ubuntu client and Windows can all have variable memory usage.

The following is a **starting budget for testing**, not a final production sizing:

| Workload | Initial vCPU | Initial RAM | Initial disk | Operating rule |
| --- | ---: | ---: | ---: | --- |
| Physical host reserve | N/A | 3 GiB minimum | At least 30 GiB free on `/` | Covers Linux Mint, libvirt/Incus services, filesystem cache, remote access and operational tools. |
| Management VM: Zabbix + Wazuh | 2 | 6 GiB, test 8 GiB if Wazuh indexing is unstable | 100-150 GiB, preferably SSD | Keep running for monitoring tests; control log retention and index growth. Splitting Wazuh components may require more RAM than this host can provide. |
| Ubuntu Desktop client VM | 2 | 3 GiB; increase to 4 GiB only after measurement | 40-60 GiB | Run for practical sessions and stop when not needed. |
| Windows Server application VM | 2 | 4 GiB minimum, workload-dependent | 60-100 GiB | Optional and on-demand; do not assume it can run together with all other roles. |

With the initial values, the host reserve plus the management and Ubuntu Desktop
VMs consume a planned 12 GiB, leaving approximately 3.52 GiB of the observed
15.52 GiB for cache and workload variation. Adding a 4 GiB Windows VM raises the
planned total to 16 GiB, already above usable physical RAM. Therefore, all three
VM roles must not be promised concurrently until RAM is upgraded or measurements
justify a different allocation. The preferred hardware improvement is at least
32 GB RAM, subject to mainboard compatibility, followed by verified gigabit
networking and a tested external backup target.

## Physical and operational requirements

| Requirement | Minimum for the prototype | Recommended for reliable use | Verification |
| --- | --- | --- | --- |
| Processor | x86-64 CPU with VT-x/AMD-V and nested page tables | At least 4 modern physical cores; more cores for concurrent exercises | `lscpu`, KVM module and `/dev/kvm` checks |
| Memory | 16 GB-class only when Windows is on-demand | 32 GB or more if all three VM roles must run together | `free -h`, workload metrics and controlled load tests |
| Active storage | SSD with enough capacity for the host, active VM disks, updates and temporary snapshots | Separate SSD-backed VM pool with monitored free space | `lsblk`, `df`, Incus pool inspection and disk-health checks |
| Backup storage | A distinct backup copy with a documented retention policy | Encrypted off-host/off-site destination plus restore tests | Scheduled backup logs and periodic restore exercise |
| Network | Stable wired interface | Verified 1 Gb/s or faster switch port and cabling | Link speed, packet-loss and throughput tests |
| Power | Safe shutdown procedure | UPS with tested shutdown signaling and automatic recovery policy | Controlled outage/recovery test |
| Firmware | Hardware virtualization enabled | Current stable firmware after compatibility and rollback review | Firmware inventory and boot/KVM test |

Disk placement must separate performance and recovery concerns. A snapshot on
the same physical disk is not a backup, and copying data from one internal disk
to another does not protect against chassis theft, power damage or operator error.
Before moving VM storage to `/hdd1` or `/hdd2`, define which disk is authoritative,
which is backup staging, how ownership is managed, and how restoration is tested.

## Software requirements

Software is divided by responsibility and installation location. A component is
not considered deployed merely because it appears in this target list.

### Software on the physical host

| Software/component | Requirement | Function | Important notes |
| --- | --- | --- | --- |
| Linux Mint 22.2 or another approved supported Linux host | Required | Supplies the Linux kernel, systemd, device drivers, filesystems and package management | Keep security updates current. Major upgrades require compatibility checks for KVM, Incus, networking and remote access. |
| KVM kernel modules (`kvm`, `kvm_intel`) | Required | Uses CPU virtualization extensions to execute guest code efficiently | `/dev/kvm` must exist and the virtualization service account must have controlled access. |
| QEMU | Required | Provides each VM's emulated/virtual hardware and execution process | QEMU executes VMs; it is not the desired declarative management interface. |
| Incus daemon and client | Required target component | Manages VM lifecycle, images, profiles, virtual networks, ACLs and storage pools | Incus is intended to become authoritative. Do not let Incus and existing libvirt definitions control the same VM or disk. |
| OpenTofu CLI and compatible Incus provider | Required for IaC | Converts reviewed declarations into Incus resources | Pin versions after a compatibility test. Never commit credentials, plan files containing sensitive data, `.terraform/`, or state files. `apply`, import and state surgery remain approval-gated. |
| `nftables` and Incus ACL/network features | Required for enforcement | Implements deny-by-default microsegmentation and narrowly allowed flows | Any live rule or route change needs a diff, console-safe rollback and verification that SSH/Tailscale still work. |
| OpenSSH server | Required for administration | Provides command-line administration over the private management path | Prefer key authentication; do not expose SSH directly to the public Internet. |
| Tailscale and MagicDNS | Required management plane | Provides authenticated private remote access and stable tailnet naming | Preserve this path during firewall, WARP, bridge and route changes. |
| Cloudflare WARP | Optional workaround | Supplies outbound connectivity where the upstream network blocks required destinations | Not part of the IaaS control plane. Its routes must not override or break Tailscale management. |
| x11vnc | Optional host GUI access | Exposes the existing desktop session when local GUI administration is needed | Bind/restrict it to private access; never publish VNC directly to the Internet. |
| Git and repository validation tools | Required operational tooling | Maintain the source of truth, review diffs and validate IaC/configuration | Changes should be traceable; secrets and generated state stay outside Git. |
| Time synchronization (`systemd-timesyncd` or approved equivalent) | Required | Keeps logs, monitoring data, certificates and audit correlation consistent | The host and guests must use reliable time sources and the correct timezone policy. |
| Disk-health and backup tooling | Required before operational use | Detects storage degradation and produces restorable copies | Select and document tools, retention, encryption, destination and restore procedure before relying on them. |

Existing QEMU/libvirt resources require a deliberate migration decision. The
target architecture remains Incus over KVM/QEMU; installing Incus must not
silently import, overwrite or duplicate the currently defined libvirt VM.

### Software on the automation controller

Ansible may run on an approved management VM/controller rather than on the
physical host. The controller needs:

| Software/component | Function | Requirement |
| --- | --- | --- |
| Ansible Core and required collections | Applies idempotent OS, hardening and application configuration after VM provisioning | Pin tested versions and collection requirements in the repository. |
| SSH client and Windows remoting support where needed | Connects to Linux guests through SSH and Windows through the approved management protocol | Use vault-backed secrets or another approved secret store; never plaintext credentials in Git. |
| Inventory generated from approved source data | Maps managed nodes and groups without confusing planned addresses with deployed facts | Keep non-secret inventory in Git and sensitive variables encrypted/outside Git. |
| Lint and validation tools | Runs syntax checks, lint and check mode before changes | A successful check does not replace post-deployment service tests. |

OpenTofu creates infrastructure objects. Ansible configures operating systems and
applications after those objects are reachable. Neither tool replaces Incus or
KVM/QEMU.

### Software inside virtual machines

| VM role | Required guest software | Purpose |
| --- | --- | --- |
| All Linux guests | Supported Ubuntu release, OpenSSH server, QEMU guest agent, time synchronization, system updates, logging, Zabbix agent and Wazuh agent as approved | Enables safe administration, clean shutdown/IP reporting, consistent logs, health monitoring and security telemetry. |
| Management VM | Zabbix server, web frontend and supported database; Wazuh manager, indexer and dashboard; reverse proxy/TLS where selected | Centralizes infrastructure monitoring and security-event analysis. Database and index retention must be capped for the available disk and RAM. |
| Ubuntu Desktop client | Desktop environment, browser/lab tools, Zabbix agent and Wazuh agent | Provides the student/lecturer client environment while remaining observable from MGMT. |
| Windows Server application VM | Licensed/supported Windows Server, VirtIO drivers, QEMU guest agent where compatible, Zabbix/Wazuh agents, and the selected PWA/application runtime | Hosts the application workload. Exact web server, database and runtime depend on the PWA and must be documented before opening CLIENT-to-APP ports. |

Installation media, Windows licenses, repository credentials, TLS private keys,
API tokens, SSH private keys and OpenTofu state are operational secrets/artifacts;
they must not be committed to this repository.

### Supporting services that must be designed

- DNS/name resolution for host and guest management, without exposing management
  interfaces publicly.
- NTP/time synchronization for reliable audit, Wazuh and Zabbix timestamps.
- TLS and certificate handling for web dashboards and application endpoints.
- Central logging and explicit log/index retention so Wazuh and Zabbix cannot
  exhaust disk space.
- Automated backups for configuration, databases and VM/application data, plus
  a documented restore test.
- Monitoring of CPU, RAM, swap, disk latency/capacity, network errors, VM state,
  service health and certificate expiration.
- Guest update and vulnerability-management procedures for Ubuntu and Windows.

### Software readiness gates

Before the target stack is declared deployed:

1. Inventory existing libvirt VMs, disks, bridges and ports; back up definitions
   before selecting an Incus migration approach.
2. Pin and document compatible Incus, OpenTofu, Incus provider, Ansible and
   collection versions.
3. Approve the Incus storage-pool and network design before creating resources;
   never let Incus and libvirt manage the same VM disk.
4. Validate OpenTofu with `tofu fmt`, `tofu validate` and a reviewed `tofu plan`
   before any approval-gated apply.
5. Validate Ansible with syntax/lint checks and `--check` where supported before
   changing guests.
6. Prove the deny-by-default policy with allowed-flow and blocked-flow tests while
   confirming that SSH and Tailscale management remain available.
7. Test guest shutdown/restart, monitoring ingestion, Wazuh event ingestion,
   log retention, backup and restoration.
8. Run a controlled concurrent-workload test and adjust VM CPU/RAM allocations
   from observed metrics.
9. Record installed versions and observed deployment state in `docs/status.md`;
   this architecture document states requirements and is not deployment proof.

## Separation of responsibilities

```text
OpenTofu -> infrastructure definitions
Incus     -> virtualization/network/storage management API
KVM/QEMU  -> VM execution
Ansible   -> guest configuration
Zabbix    -> infrastructure monitoring
Wazuh     -> security monitoring/SIEM
Tailscale -> remote management plane
```
