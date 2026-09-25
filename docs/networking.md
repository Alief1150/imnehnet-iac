# Networking and Microsegmentation

## Scope and status

This document defines the current network target and records the prototype-aligned
address plan. `docs/status.md` remains authoritative for what has been observed
live. Micro-segmentation intent is not proof that every ACL/nftables rule has been
deployed or tested.

## Network topology

| Zone/component | Address | Purpose | Status reference |
| --- | --- | --- | --- |
| Management network | `192.168.100.0/24` | Communication between physical host, `IAAS-MGMT`, and `LAB-ENV` | Prototype-aligned |
| `IAAS-MGMT` | `192.168.100.10` | Automation, database, backend/portal and operational services | Verified in `docs/status.md` |
| `LAB-ENV` | `192.168.100.20` | Incus compute/lab plane | Verified in `docs/status.md` |
| Incus bridge `incusbr0` | `10.20.0.1/24` | Internal system-container network with IPv4 NAT | Verified in `docs/status.md` |
| Student containers | `10.20.0.0/24` addresses | Isolated practical environments | Reference instances verified; full policy pending |
| Tailscale | Tailnet-assigned `100.x` | Private remote administration | Used operationally |

The former `10.10.10.0/24`, `10.10.20.0/24`, and `10.10.30.0/24`
VM-centric plan was superseded by the 2026-09-25 architecture decision.

## Trust boundaries

```text
Remote admin
    |
Tailscale
    |
Physical host / management network 192.168.100.0/24
    |
    +-- IAAS-MGMT 192.168.100.10
    |      OpenTofu / Ansible / MySQL / planned portal
    |
    +-- LAB-ENV 192.168.100.20
           Incus API and host boundary
              |
           incusbr0 10.20.0.0/24
              +-- student environment A
              +-- student environment B
```

The management network, Incus host, and student container network are distinct
trust zones even when routing/NAT makes selected flows possible.

## Default-deny policy model

| Source | Destination | Default | Exceptions |
| --- | --- | --- | --- |
| IAAS-MGMT | LAB-ENV Incus/SSH | Deny except required management | Approved Incus API, SSH/Ansible, monitoring and audit flows |
| IAAS-MGMT | Student container | Deny except orchestration | Bootstrap, validation, monitoring and explicitly required administration |
| Student container | IAAS-MGMT | Deny | Required application API, telemetry or log delivery only |
| Student container | Another student container | Deny | Only an explicitly approved exercise flow |
| Student container | Internet | Restricted/controlled | Package installation or exercise-specific access according to policy |
| Student/user device | Student environment | Deny except assigned publication | Per-user SSH/web/terminal mapping |
| Public Internet | Management services | Deny | None; use Tailscale/private management paths |

A policy is not complete until both allowed and blocked paths are tested and the
results are recorded. Do not infer enforcement from topology alone.

## Student access

Students must not receive:

- direct Incus API or Unix-socket access;
- unrestricted access to `192.168.100.0/24`;
- unrestricted access to the entire `10.20.0.0/24` subnet;
- another user's container credentials or published ports.

The portal/backend maps an authenticated user to an allowed environment and
publishes only the required SSH, web or terminal path. A future web-terminal
gateway must execute server-side authorization for every session.

## Administration and remote access

Tailscale is the private administrative overlay. MagicDNS should provide stable
names where configured. Do not expose SSH, VNC, Incus management, MySQL,
Prometheus, Grafana, or Wazuh administration directly to the public Internet.

Cloudflare WARP is independent outbound-connectivity support for the physical
host. It is not a route between management and student zones. Changes to WARP,
Tailscale, bridges, routes, NAT, ACLs, nftables, DNS or firewall rules require:

1. a captured pre-change state;
2. a reviewed diff or explicit command set;
3. a console-safe rollback path;
4. explicit operator approval;
5. verification that SSH and Tailscale still work after the change.

## Required validation

Before micro-segmentation is declared implemented, test at minimum:

- IAAS-MGMT can reach only the required Incus/SSH/automation endpoints;
- assigned student access reaches the correct environment;
- one student environment cannot reach another by default;
- student environments cannot reach management services by default;
- required telemetry reaches Wazuh/Prometheus endpoints;
- published ports do not expose unintended services;
- restart/reboot preserves policy;
- audit logs identify the tested source, destination, action and result without
  storing credentials.
