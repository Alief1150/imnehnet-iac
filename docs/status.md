# Current Verified Status

Last update: 2026-09-21

This file records only facts that have been observed or explicitly confirmed. Planned architecture belongs in `architecture.md` or `networking.md`.

## Physical host

- Hostname observed: `imnehnet`; chassis is reported as desktop.
- Mainboard/platform reported as Gigabyte Technology Co., Ltd. B85M-DS3H-A, firmware F1.
- CPU verified as Intel Core i5-4460 at 3.20 GHz: 1 socket, 4 cores, 4 threads, with a reported maximum of 3.40 GHz.
- Intel VT-x is exposed. The `kvm` and `kvm_intel` modules are loaded, and `/dev/kvm` exists.
- Installed memory is reported as 15 GiB usable RAM with 2 GiB swap.
- System storage is a 240 GB-class Crucial BX500 SSD (`223.6G` visible) with the ext4 root filesystem. At verification time, `/` had 186 GiB available.
- Two 1 TB-class HDDs (`931.5G` visible each) are mounted as ext4 at `/hdd1` and `/hdd2`; they had 854 GiB and 870 GiB available respectively at verification time.
- The wired interface uses a Realtek RTL8111/8168-family controller. Its current link was observed at 100 Mb/s full duplex, so gigabit operation has not yet been verified.
- Host OS verified as Linux Mint 22.2 x86-64 with kernel 6.14.0-29-generic.

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

Target stack is Incus management over KVM/QEMU, with OpenTofu and Ansible automation.

Observed on the physical host on 2026-09-19:

- QEMU 8.2.2 and libvirt/`virsh` 10.0.0 are installed.
- `libvirtd.service` is enabled and active.
- The existing libvirt VM `IAAS-MGMT` was observed running. It is not evidence of an Incus deployment and must not be placed under two management systems.
- Incus, OpenTofu and Ansible commands were not found on the physical host.
- `nftables`, OpenSSH Server, Tailscale 1.102.4, Cloudflare WARP 2026.7.1377.0 and x11vnc 0.9.16 are installed on the host. At the latest check, `tailscaled` and `warp-svc` were active while the system-level `x11vnc` unit was inactive; this does not invalidate the earlier process-level x11vnc observation above.
- `smartmontools`, `qemu-guest-agent` and `virt-manager` packages were not installed on the physical host at the latest check.
- The absence of Ansible on the physical host does not determine the state of a separate approved Ansible controller.
- Zabbix Server and Wazuh control commands were not found on the physical host; these workloads belong in the management VM in the target architecture.

Incus/OpenTofu deployment remains unverified and must not be inferred from the existing QEMU/libvirt installation.

## Hermes integration

Verified on 2026-09-19:

- User plugin `imnehnet-welcome` version `1.1.0` is installed and enabled in the active default Hermes profile.
- Plugin doctor passed runtime discovery, manifest parsing, import, and registration with zero tools and two observer hooks: `pre_llm_call` and `post_llm_call`.
- The Telegram welcome policy is once per authorized user for each daily period beginning at 05:00 WIB.
- Every greeting includes up to five latest Git commits; shared repository updates are visible to every authorized user without exposing private session content.
- Update content is bounded to Git commit metadata; it is not presented as live infrastructure status.
- Cron job `IMNEHNET daily welcome 05:00 WIB` is scheduled at `0 5 * * *` for all registered Telegram DM routes; four existing DM routes were synchronized on 2026-09-20.
- No-agent job `Sync IMNEHNET Telegram DM targets` runs at `55 4 * * *` to refresh the target list from the Hermes routing database before each briefing. Groups and topics are excluded.
- The gateway remained active after a non-disruptive systemd reload.

Verified on 2026-09-21:

- Third-party plugin `obra/superpowers` version `6.4.1` is installed and enabled in the active default Hermes profile, pinned to upstream commit `5bf4e78011075bcfc0dc295f0724994cd123ee71`.
- Upstream Hermes tests passed `19/19`; Hermes manifest validation and plugin doctor passed with zero tools and one `pre_llm_call` hook. A fresh one-shot Hermes session successfully loaded `superpowers:verification-before-completion` through `skill_view`.
- Local hardening prevents the systematic-debugging example from printing the value of `IDENTITY` and disables optional visual-companion telemetry. These local changes must be reviewed and reapplied after any upstream update.
- The plugin was not granted built-in tool override capability. Project `AGENTS.md`, explicit operator approval gates, credential rules, and live-infrastructure safety policy remain authoritative over plugin workflows.

Also verified on 2026-09-21:

- Nineteen core security-review skills from `google/mantis` are installed under `~/.hermes/skills/security/` in the active default Hermes profile. The audited source is upstream commit `6f523a39ccb535b4f5411310f31e872fab954e07`; the repository had no release or tag at verification time.
- The installed set excludes `mantis-configure`, `mantis-launch`, and the Python ADK/reference harness. No Mantis installer, cloud setup, container, reproducer, patcher, or generated code was executed during installation.
- Hash comparison against the audited checkout found 19/19 expected skill directories with zero missing, mismatched, or extra files. Hermes security scanning returned `SAFE` for all 19 installed skills.
- A fresh one-shot Hermes session successfully loaded `mantis-threat-model` through `skill_view` and returned `MANTIS_RUNTIME_OK`.
- Mantis is a defensive source-code security-review workflow. Its reproduction and patch stages can execute untrusted code or modify a repository when explicitly invoked; they require isolation, review, and the normal IMNEHNET approval and credential safeguards.

## Project document database

Verified on 2026-09-21:

- Proposal PDF `documents/proposals/2026-kelompok-5-proposal-pbl-imnehnet.pdf` is stored locally and catalogued in `documents/index.json`.
- The PDF has 20 pages, size 825395 bytes, and SHA-256 `8922074ca418d818b0493d60ad33f11a8d5283961884fb0974d6be37ca9d6e27`.
- The proposal is classified as a reference document, not as proof of deployment or an automatic replacement for the authoritative architecture and decision records.
- Final source archive `documents/proposals/2026-09-21-kelompok-5-proposal-final.zip` is stored intact and catalogued; it contains one PDF and one DOCX, with no encrypted entries, path traversal, or symlinks detected.
- Sanitized audit setup reference `documents/audit/2026-imnehnet-hermes-audit-log-final-setup.pdf` is stored locally and catalogued. It contains placeholders rather than actual identity mappings and is not a raw audit log.
