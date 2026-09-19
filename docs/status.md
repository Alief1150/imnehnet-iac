# Current Verified Status

Last update: 2026-09-18

This file records only facts that have been observed or explicitly confirmed. Planned architecture belongs in `architecture.md` or `networking.md`.

## Physical host

- Hostname/user environment observed: `imnehnet`.
- CPU family confirmed only as Intel Core i5 4th generation; exact model/cores/threads still need verification.
- RAM target discussed: 16 GB; installed amount should be verified with `free -h` before capacity claims.
- Prototype host OS: Linux Mint.

## Connectivity

- Cloudflare WARP has successfully reported `warp=on`.
- Host was able to reach previously blocked Internet destinations after WARP was enabled.
- Tailscale is used for remote connectivity.
- Tailscale MagicDNS is planned/being configured so the host can be addressed as `imnehnet` from the tailnet.

## Remote desktop

- `x11vnc` observed running against display `:0`.
- x11vnc observed listening on TCP 5901 over IPv4.
- TCP 5900 on loopback was already occupied by a QEMU process at the time of observation.
- UFW was observed inactive at that time; therefore UFW rules were not enforcing access restrictions.

## Virtualization target

Target stack is Incus management over KVM/QEMU, with OpenTofu and Ansible automation. Treat deployment status as unknown until verified on the host.

## Hermes integration

Verified on 2026-09-19:

- User plugin `imnehnet-welcome` version `1.1.0` is installed and enabled in the active default Hermes profile.
- Plugin doctor passed runtime discovery, manifest parsing, import, and registration with zero tools and two observer hooks: `pre_llm_call` and `post_llm_call`.
- The Telegram welcome policy is once per authorized user for each daily period beginning at 05:00 WIB.
- Every greeting includes up to five latest Git commits; shared repository updates are visible to every authorized user without exposing private session content.
- Update content is bounded to Git commit metadata; it is not presented as live infrastructure status.
- Cron job `IMNEHNET daily welcome 05:00 WIB` is scheduled at `0 5 * * *` for the explicitly registered Telegram DM target; a live manual delivery completed successfully on 2026-09-19.
- The gateway remained active after a non-disruptive systemd reload.
