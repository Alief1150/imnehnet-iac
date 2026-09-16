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

## Resource constraints

The host is low-spec by server standards. Known CPU class is Intel Core i5 4th generation and the RAM plan is 16 GB. Exact CPU model, core/thread count, installed RAM, storage type/capacity, and VT-x status must be verified before final VM sizing.

Windows Server is intentionally optional/on-demand if resource pressure is high.

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
