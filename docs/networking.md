# Networking and Microsegmentation

## Planned segments

These are planned defaults and must not be described as deployed until verified in `docs/status.md`.

| Segment | Planned subnet | Purpose |
| --- | --- | --- |
| MGMT | 10.10.10.0/24 | Management, Zabbix, Wazuh |
| CLIENT | 10.10.20.0/24 | Ubuntu Desktop / student and lecturer client |
| APP | 10.10.30.0/24 | Windows/PWA/application workload |

## Policy model

Default policy between segments is deny. Add narrow allow rules only for required services.

Examples:

- MGMT -> CLIENT/APP: administration and monitoring as required.
- CLIENT -> MGMT: blocked except approved telemetry.
- CLIENT -> APP: only application ports required by the practical exercise.
- APP -> MGMT: blocked except approved telemetry/log delivery.
- Lab/client east-west traffic: blocked unless explicitly required.

## Remote management

Tailscale is the private management overlay. MagicDNS should resolve the host as `imnehnet` inside the tailnet.

Do not expose SSH, VNC, Incus management, Zabbix administration, or Wazuh administration directly to the public Internet.

## Current host connectivity notes

Cloudflare WARP has been used successfully to provide outbound Internet reachability from the current host. Tailscale is used independently for remote access. Changes to either VPN/routing stack require a rollback plan because both can affect routes.
