# Current Verified Status

Last update: 2026-09-26

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

The statement above reflects the 2026-09-19 host-only check. The following prototype deployment was verified directly on 2026-09-22 and supersedes the earlier unverified Incus/OpenTofu note without changing the separate target architecture:

- The libvirt VMs `IAAS-MGMT` and `LAB-ENV` were both running.
- `IAAS-MGMT` was reachable at `192.168.100.10/24`; its Ansible inventory defines `iaas-mgmt` and `lab-env`, and `ansible.builtin.ping` returned `SUCCESS` with `changed: false` for both hosts.
- `IAAS-MGMT` has Ansible Core 2.16.3, OpenTofu 1.12.6 and the Incus 6.0.0 client.
- The trusted Incus remote `lab-env` points to `https://192.168.100.20:8443`; the authenticated API reported server name `lab-env` and Incus server version 6.0.0.
- The managed bridge `incusbr0` exists on `LAB-ENV` with `10.20.0.1/24`, IPv4 NAT enabled and IPv6 disabled.
- Containers `lab-test` (`10.20.0.31`) and `lab-tofu-test` (`10.20.0.202`) were both `RUNNING` on `incusbr0`.
- The OpenTofu configuration and state both require `registry.opentofu.org/lxc/incus`; the configuration pins the provider to `~> 1.2`. State contents and credentials were not inspected or recorded.
- The observed running `lab-tofu-test` instance and provider binding corroborate the operator-confirmed OpenTofu provisioning test. Microsegmentation enforcement, portal/MySQL integration, monitoring deployment and end-to-end lifecycle behavior remain pending verification.

## Backend automation breakpoint

Verified read-only on 2026-09-25:

- On `IAAS-MGMT`, OpenTofu workspace `default` contains `incus_instance.student_lab`; non-sensitive outputs report `container_name=lab-tofu-test`, `container_ipv4=10.20.0.202`, and `container_status=Running`.
- Ansible from `IAAS-MGMT` reached `lab-env` with `SUCCESS`, `changed: false`, and `ping: pong` after the noninteractive locale was normalized to `C.UTF-8`.
- The constrained `/usr/local/sbin/imnehnet-incus` wrapper ran through `sudo -n` on `LAB-ENV` without a password prompt and listed `lab-test` (`10.20.0.31`) and `lab-tofu-test` (`10.20.0.202`) as `RUNNING`.
- `/home/iaas/projects/webportal-iaas` exists but is empty. There is no `artisan`, `composer.json`, `package.json`, `.env`, Laravel/Filament source, or deployed `/admin` application.
- PHP 8.3, PHP-FPM, Apache, MySQL and Git are available on `IAAS-MGMT`. Composer and Node.js/npm were not available in `PATH`. Apache port 80 still served the Ubuntu default page; port 3000 was Grafana.
- MySQL correctly refused an unauthenticated query as user `iaas`. No credential or MySQL option file was read to force documentation verification.

The operator-provided breakpoint evidence for 2026-09-25 records the most recent successful `student-bootstrap.yml` run as `ok=7 changed=0 unreachable=0 failed=0`, and records `imnehnet.lab_environments` row `lab-tofu-test` as `status=READY`, `runtime_status=Running`, workspace `default`, and internal IP `10.20.0.202`. Those two facts were not recreated with a mutating playbook or an unauthorized database query during this documentation pass.

The 2026-09-25 application-directory observation above was superseded by direct verification on 2026-09-26:

- `/home/iaas/projects/webportal-iaas` now contains a Laravel 13.33.0 / Filament 5.8.4 Milestone 1 control-plane implementation with role-aware pages, ownership-scoped lab reads, append-only activity behavior, versioned same-origin API routes, read-only infrastructure health providers, and a mutation adapter that returns `not_configured` without invoking OpenTofu, Incus, Ansible, or network changes.
- The remote test suite passed `39` tests with `135` assertions after Pint formatting. The Users table has a regression test for enum-cast roles after its Filament callbacks were corrected to consume `UserRole` rather than `string`. Vite 8.3.1 produced the production assets under a verified user-local Node.js 22.23.3 runtime; the system Node.js remains 18.19.1 and is incompatible with Vite 8.
- The development listener was restored on `0.0.0.0:8000`; `/` and `/admin/login` returned HTTP 200 from the physical host path. This is still a development server, not a production web-server deployment.
- Before live schema activation, a mode-`0600` compressed MySQL backup was created at `/home/iaas/projects/webportal-iaas/storage/app/backups/iaas_portal-pre-migrate-20260926-051515.sql.gz`; gzip validation passed and its SHA-256 is `1e2ff3f0be2f230dbadf0beee87e116364ffee4679f650b7f2d7b094ab50a2fa`.
- The three pending control-plane migrations completed in batch 2. A post-migration check reported zero pending migrations, 2 users, 0 labs, 0 lab operations, 0 activity logs, 0 queued jobs, and 0 failed jobs. Unique database constraints now enforce one lab per user plus unique container and external identifiers.
- The legacy `imnehnet.lab_environments` row for `lab-tofu-test` has `user_id=NULL`; it was not copied into the ownership-scoped portal `labs` table because no owner can be proven. Both `lab-test` and `lab-tofu-test` remain observed as running through the read-only Incus provider.
- An unprivileged database-queue worker was started alongside the existing development server. This is not a persistent production service, and the mutation provider remains intentionally fail-closed with `not_configured`, so queued lifecycle requests cannot mutate Incus, OpenTofu, Ansible, or networking.
- The Prometheus health validator was corrected under a RED-GREEN regression test to accept the observed standard response `Prometheus Server is Healthy.`. Fresh portal health results were Incus, MySQL, Prometheus, Grafana, and Blackbox `healthy`; automation `degraded` because its tools are available while live mutation remains disabled; and Wazuh `not_connected`.

This remains a single-environment proof of concept. Multi-user OpenTofu state strategy, Wazuh integration, web terminal, persistent production web/queue serving, live lifecycle mutation, and end-to-end micro-segmentation enforcement remain pending.

## Distributed infrastructure audit

Verified on 2026-09-23/24 WIB:

- `auditd`, a node-local SSH session hook, JSON-capable reporter, audit rules, and a narrow reporter-only sudoers rule are deployed on `IAAS-MGMT` and `LAB-ENV`; SSH remained active and was reloaded rather than restarted.
- Fresh physical-host logins were attributed by key fingerprint as `Hermes / server-imnehnet` on both VMs. A fresh controller login to `LAB-ENV` was attributed as `Ansible / iaas-mgmt`.
- The observed management paths use LAN sources `192.168.100.1` and `192.168.100.10`; therefore their Tailscale machine and tailnet-user fields correctly remain unknown rather than being inferred.
- The physical-host reporter retains its original compact output and now also supports `--json` with node, actor, source IP, fingerprint, Tailscale identity, Linux user, action, and target fields.
- User-level aggregator `~/.local/bin/imnehnet-audit-report-all` pulls all three reporters in parallel using a dedicated verified `known_hosts` file, applies a default 12-second end-to-end timeout per node, normalizes timestamps to `Asia/Jakarta`, emits `NOACTIVITY` for a reachable node with no event, and emits `ERROR` rather than waiting indefinitely for an unresponsive node.
- End-to-end aggregation returned all three nodes with zero transport/report errors and zero missing required fields. The actor map and private collector identity remain outside version control; only the public-key fingerprints are used on target nodes.
- `LAB-ENV` had an empty Tailscale APT keyring that blocked package installation. It was backed up and replaced with the official 2288-byte keyring, SHA-256 `3e03dacf222698c60b8e2f990b809ca1b3e104de127767864284e6c228f1fb39`, fingerprint `2596A99EAAB33821893C0A79458CA832957F5868`; signed APT metadata refresh then succeeded.
- Tailscale on `LAB-ENV` is installed through Snap and remained online at `100.111.217.109`; absence of a `tailscaled.service` system unit is expected for this installation method.

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

Also verified on 2026-09-21:

- Third-party static skill `Agents365-ai/drawio-skill` version `3.4.0` is installed and enabled under `~/.hermes/skills/creative/drawio-skill/`, based on release commit `7aa92f73819766eb914fffac66762cf2adb5d828`.
- The upstream test suite passed `218` tests with `7` optional tests skipped. The installed package's 79 tracked files match the audited release checkout.
- Hermes scanning reports `CAUTION`, not `SAFE`, because the skill contains reviewed subprocess wrappers, optional dependency guidance, live-infrastructure import recipes, a large compressed shape index, and an optional MCP server. The operator explicitly approved installation after disclosure.
- No MCP server was registered, no system dependency was installed, no live infrastructure was queried, and no icon CDN was contacted during installation. Python-only Diagram IR generation is available; native export and automatic Graphviz layout remain unavailable because `drawio` and `dot` are not installed.
- A smoke test built a two-node `.drawio` artifact and validation returned zero errors and zero warnings. A fresh Hermes session loaded `drawio-skill` and returned `DRAWIO_SKILL_RUNTIME_OK`.

Also verified on 2026-09-21:

- Static skill `delegate-setup` version `0.5.0` from `amElnagdy/delegate-skills` is installed and enabled under `~/.hermes/skills/autonomous-ai-agents/delegate-setup/`, based on upstream commit `6826b363085dcc80875372315fe7d208c4bf733f`.
- Hermes scanning returned `SAFE`; all four upstream CI-equivalent gates completed successfully, including the full relay smoke suite. The installed package's 7 tracked files match the audited checkout with zero missing, mismatched, or extra files.
- Discovery found zero installed implementer CLIs and 17 missing CLIs. Therefore none of the 17 `*-delegate` relay skills were installed; this avoids inactive triggers and write-capable or unsandboxed relay modes that currently cannot execute.
- No global or project delegation config exists, no lane was written, and no coding task was dispatched. A fresh Hermes session loaded the skill and returned `DELEGATE_SETUP_RUNTIME_OK`.
- Hermes's native `delegate_task` and the existing dedicated coding-agent skills remain the active delegation mechanisms. Installing a future relay requires its implementer CLI, separate source/runtime review, authentication outside chat, and explicit approval for its write/sandbox posture.

Also verified on 2026-09-21:

- Third-party static skill `conorbronsdon/avoid-ai-writing` version `3.35.0` is installed and enabled under `~/.hermes/skills/creative/avoid-ai-writing/`, pinned to release commit `fc979c6489ec0ac81a77236782ba496da81b24cb` under the MIT license.
- Only the 12-file standalone skill package was installed. No MCP server, npm package, Git hook, external model, credential, telemetry, or network-enabled corpus/evaluation utility was installed or configured.
- All 20 upstream test groups passed. Recursive SHA-256 comparison found all 12 installed files identical to the reviewed release package, with zero missing, extra, or mismatched files.
- A detector smoke test returned score `36`, label `Moderate AI signals`, and 9 findings across five categories. The preservation validator correctly rejected removal of protected inline code, and a fresh Hermes session completed detect mode with `AVOID_AI_WRITING_RUNTIME_OK`.
- Hermes's deep-audit command cannot assign a scanner verdict to manually installed local skills because they are not hub-managed. Manual static review found no credential access, network call, subprocess invocation, installer, telemetry, symlink, or executable file in the installed package.
- The skill is a writing-quality aid, not proof of AI authorship. It overlaps with the built-in `humanizer`; prefer `avoid-ai-writing` when source fidelity, detect-only operation, protected-content handling, or deterministic preservation checks are required.

## Project document database

Verified on 2026-09-21:

- Proposal PDF `documents/proposals/2026-kelompok-5-proposal-pbl-imnehnet.pdf` is stored locally and catalogued in `documents/index.json`.
- The PDF has 20 pages, size 825395 bytes, and SHA-256 `8922074ca418d818b0493d60ad33f11a8d5283961884fb0974d6be37ca9d6e27`.
- The proposal is classified as a reference document, not as proof of deployment or an automatic replacement for the authoritative architecture and decision records.
- Final source archive `documents/proposals/2026-09-21-kelompok-5-proposal-final.zip` is stored intact and catalogued; it contains one PDF and one DOCX, with no encrypted entries, path traversal, or symlinks detected.
- Sanitized audit setup reference `documents/audit/2026-imnehnet-hermes-audit-log-final-setup.pdf` is stored locally and catalogued. It contains placeholders rather than actual identity mappings and is not a raw audit log.

Verified on 2026-09-22:

- Editable presentation `documents/presentations/2026-kelompok-5-pbl-imnehnet-implementasi-terkini.pptx` is stored and catalogued as the current PBL deliverable. It contains 14 slides, has SHA-256 `7413b73e4ce0be856695fd27d4baf9035214eb3ea382d2baf5a84d4c99eb345e`, and distinguishes the verified prototype status from pending implementation work.

Verified on 2026-09-25:

- The backend automation breakpoint is archived in Markdown and PDF under `documents/handoffs/`, with a separate compact Hermes context note.
- The handoff is a sanitized implementation snapshot. It does not contain passwords, private keys, `.env`, OpenTofu state contents, raw audit logs, or runtime identity mappings.
