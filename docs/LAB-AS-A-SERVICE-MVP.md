# Lab as a Service (LaaS) MVP — Guided CTF

Last verified: 2026-09-30 (autonomous implementation sprint, live validation).
This document records the deployed MVP state. Facts below were observed live
on the hosts; see the validation matrix for PASS/FAIL evidence.

## 1. Final topology

```text
                        server-imnehnet (192.168.100.1)
                     QEMU/KVM + libvirt, Linux Mint 22.2
                            │
                   MGMT 192.168.100.0/24
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
    IAAS-MGMT            LAB-ENV             LAB-ATK
  192.168.100.10      192.168.100.20      192.168.100.30
   OpenTofu/Ansible   Incus 6.0.0         Incus 7.0.1
   Laravel/MySQL      incusbr0            incusbr0 (+Tailscale)
   monitoring stack   10.20.0.1/24        10.30.0.1/24
        │                   │                   │
   (mgmt only)      victim containers   attacker containers
                    lab-test  10.20.0.31    atk-test    10.30.0.89
                    lab-tofu-test 10.20.0.202 (PROTECTED, untouched)
                    victim-demo-a 10.20.0.100  atk-demo-a 10.30.0.100
                    victim-demo-b 10.20.0.101  atk-demo-b 10.30.0.101
```

Management access is via Tailscale (MagicDNS names `iaas-mgmt`, `lab-env`,
`lab-atk`, `server-imnehnet`). Tailscale carries management traffic only;
the attacker/defender lab data plane runs over the MGMT LAN and Incus
bridges.

## 2. Lab pair model

One student lab = one disposable pair:

```text
LabInstance (lab_id)
├── attacker  atk-<lab_id>    on LAB-ATK, Kali baseline, 10.30.0.<100+n>
└── victim    victim-<lab_id> on LAB-ENV, ubuntu/24.04, 10.20.0.<100+n>
```

Convention: attacker and victim of one lab share the same last octet
(`.100` = first lab, `.101` = second lab, …). Static IPs are assigned at
launch via the nic device (`ipv4.address`), so the policy sets are
deterministic.

## 3. Routing design (P1)

Incus NAT behavior (discovered live):

- LAB-ATK `table inet incus`, chain `pstrt.incusbr0`:
  `ip saddr 10.30.0.0/24 ip daddr != 10.30.0.0/24 masquerade`
- LAB-ENV has the equivalent rule for 10.20.0.0/24.

Consequence: attacker→victim traffic is masqueraded to `192.168.100.30`
(LAB-ATK) before reaching LAB-ENV; victims see the attacker host IP, not
the container IP. Per-student policy is therefore enforced on LAB-ATK
BEFORE the masquerade (pre-NAT FORWARD sees the real container IP).

Controlled routing = two static routes (persisted):

| Host    | Route                                |
| ------- | ------------------------------------ |
| LAB-ATK | `10.20.0.0/24 via 192.168.100.20 dev eth0`   |
| LAB-ENV | `10.30.0.0/24 via 192.168.100.30 dev enp1s0` |

Internet/NAT for all containers is untouched (default route via
192.168.100.1 + Incus masquerade unchanged).

## 4. Micro-segmentation design (P2/P3)

Principles: default deny for lab-relevant traffic, explicit allow per
paired lab, least privilege, rate-limited logging of drops.

### Enforcement points and why

1. **LAB-ATK FORWARD chain (primary).** All attacker→victim traffic is
   routed by LAB-ATK and is masqueraded there, so this is the single
   point where the real attacker container IP is visible. Enforcement:
   `table inet laa-ctf` (priority 10, after Incus's own chain).
2. **LAB-ENV FORWARD chain (defense in depth).** Blocks victim-initiated
   connections toward the attacker subnet and victim↔victim east-west.
3. **Incus network ACL `ctf-attacker` (LAB-ATK only).** LAB-ENV runs
   Incus 6.0.0 which rejects `security.acls` on nic devices, so east-west
   isolation on LAB-ENV is done with nftables + `br_netfilter`
   (`bridge-nf-call-iptables=1`, already required by Docker on that host).

### Scaling model

New lab pair `n` uses last octet `100+n` on both subnets. Policy update is
one element per host:

- LAB-ATK `allowed_pairs` set: `10.30.0.<n> . 10.20.0.<n>`
- LAB-ENV `victim_demo` set: `10.20.0.<n>`
- New attacker containers get `security.acls=ctf-attacker`.

This is generated per student (Ansible-ready, see playbook) rather than
wide-open subnet rules.

### Rules (LAB-ATK `/etc/laa-ctf/laa-ctf.nft`)

```text
established,related                                        accept
paired saddr.daddr + icmp                                  accept
paired saddr.daddr + tcp 22,80                             accept
attacker(10.30.0.0/24) -> defender(10.20.0.0/24)           drop+log(5/s)
victim-initiated (saddr 192.168.100.20) -> 10.30.0.0/24 new drop+log(5/s)
attacker -> 192.168.100.0/24, 100.64.0.0/10 (new)          drop
```

### Rules (LAB-ENV `/etc/laa-ctf/laa-ctf.nft`)

```text
established,related                                        accept
victim_demo <-> victim_demo                                drop+log(5/s)
10.20.0.0/24 -> 10.30.0.0/24 new                           drop+log(5/s)
```

The victim-victim set is scoped to student victim IPs only; the existing
infra containers (lab-test, lab-tofu-test) are unaffected.

### Persistence

Both hosts: `laa-ctf-config.service` (systemd oneshot, enabled,
`After=network-online.target incus.service`) reapplies the static route
(`ip route replace`, idempotent) and reloads the nft table. LAB-ENV also
persists `br_netfilter` via `/etc/modules-load.d/` and
`/etc/sysctl.d/99-laa-ctf.conf`. Persistence was tested by deleting the
route + table and re-applying via the unit.

Rollback per host:

```bash
sudo systemctl disable --now laa-ctf-config.service
sudo nft delete table inet laa-ctf
sudo ip route del 10.20.0.0/24 via 192.168.100.20   # on lab-atk
sudo ip route del 10.30.0.0/24 via 192.168.100.30   # on lab-env
# pre-sprint state backups: ~/laa-sprint-backups-20260930-*/ on each host
```

## 5. Attacker baseline (P5)

- Base image: `images:kali` (Kali current amd64 container, simplestreams).
- Packages (no-install-recommends): `nmap curl wget netcat-openbsd
  dnsutils traceroute iputils-ping` (iproute2/iputils included in base).
- Published local image: `ctf-attacker-kali-base`
  (fingerprint `01c19a8a0f19…`, 182 MiB) on LAB-ATK.
- New attacker: `incus launch local:ctf-attacker-kali-base atk-<lab_id>
  -d eth0,ipv4.address=10.30.0.<n>`, then attach
  `security.acls=ctf-attacker`.
- Reset a lab: `incus delete atk-<lab_id> --force` and relaunch from the
  image; the victim reset is the same from `images:ubuntu/24.04` + the
  playbook.
- No snowflake container: `atk-demo-a/b` are disposable proofs; rebuilds
  come from the published image.

## 6. Guided CTF scenario (P4)

Flow per student pair (example lab `demo-a`):

```text
1. Recon        nmap -sn 10.20.0.0/24        (only victim-demo-a answers)
2. Enumerate    nmap -sV 10.20.0.100         (tcp/80 http)
3. Web recon    curl http://10.20.0.100/robots.txt   -> /n0te-4-admin/
4. Flag         curl http://10.20.0.100/n0te-4-admin/flag.html
                IMNEHNET{lab-ctf-a-5cdcc25b}
5. Submit       (future) Laravel portal, format IMNEHNET{...}
```

Victim service: `python3 -m http.server 80 --directory /opt/ctf` managed
by `ctf-http.service` (systemd) inside the victim container. Harmless,
lab-internal only, never exposed publicly. Flag values are deployed per
lab by the playbook (`ctf_flag` var, `no_log`); they are not stored in
this repository.

Demo pair B uses the same layout with its own flag, proving isolation
between simultaneously running labs.

## 7. Validation matrix (P8, all live 2026-09-30)

| Test                                        | Expected   | Actual      | Result |
| ------------------------------------------- | ---------- | ----------- | ------ |
| LAB-ATK healthy (Incus 7.0.1, Tailscale up) | healthy    | healthy     | PASS   |
| LAB-ENV healthy (Incus 6.0.0)               | healthy    | healthy     | PASS   |
| Existing container Internet/NAT preserved   | works      | atk/lab-test HTTP 200 | PASS |
| attacker-A → victim-A tcp/80                | allowed    | robots.txt served     | PASS |
| attacker-A → victim-B                       | blocked    | blocked      | PASS   |
| attacker-B → victim-B tcp/80                | allowed    | robots.txt served     | PASS |
| attacker-B → victim-A                       | blocked    | blocked      | PASS   |
| attacker-A ↔ attacker-B east-west           | blocked    | blocked      | PASS   |
| victim-A ↔ victim-B east-west               | blocked    | blocked      | PASS   |
| victim-initiated → attacker                 | blocked    | blocked      | PASS   |
| attacker → MGMT subnet                      | blocked    | blocked      | PASS   |
| paired ICMP                                 | allowed    | reply ~1 ms  | PASS   |
| End-to-end flag A / flag B                  | retrievable| both retrieved | PASS |
| Management SSH reachable during all changes | reachable  | reachable    | PASS   |
| lab-tofu-test intact                        | running    | RUNNING, internet OK | PASS |
| Tofu workspace `default` plan               | no changes | "No changes. Your infrastructure matches" | PASS |
| Persistence reapplies after wipe            | re-applied | route+7 rules / route+4 rules | PASS |

## 8. Files and configuration changed

```text
LAB-ATK (192.168.100.30)
  /etc/laa-ctf/laa-ctf.nft                     new (nft table inet laa-ctf)
  /etc/systemd/system/laa-ctf-config.service   new, enabled
  static route 10.20.0.0/24 via 192.168.100.20 (via unit)
  containers: atk-demo-a, atk-demo-b, atk-template(deleted after publish)
  local image: ctf-attacker-kali-base
  Incus ACL: ctf-attacker (attached to atk-demo-a/b)

LAB-ENV (192.168.100.20)
  /etc/laa-ctf/laa-ctf.nft                     new (nft table inet laa-ctf)
  /etc/systemd/system/laa-ctf-config.service   new, enabled
  /etc/modules-load.d/br_netfilter.conf        new
  /etc/sysctl.d/99-laa-ctf.conf                new
  static route 10.30.0.0/24 via 192.168.100.30 (via unit)
  containers: victim-demo-a, victim-demo-b (+ ctf-http.service inside)

Pre-change backups: ~/laa-sprint-backups-20260930-*/ on both hosts
(nft ruleset, routes, addresses, incus list/network/profile/storage).
```

Protected resources confirmed intact: `lab-tofu-test` container, OpenTofu
`default` workspace state, `incusbr0` on both hosts, Tailscale on LAB-ATK,
existing containers (lab-test, atk-test), Docker monitoring stack on
LAB-ENV.

## 9. Known limitations

- Victims see attacker traffic as masqueraded `192.168.100.30`
  (no per-attacker source on the victim side). Per-student distinction is
  enforced pre-NAT on LAB-ATK. Removing the masquerade for the
  attacker→defender subnet would expose real container IPs to victims and
  is a candidate follow-up.
- LAB-ENV Incus 6.0.0 does not accept `security.acls` on nic devices, so
  victim east-west isolation relies on `br_netfilter` + nftables instead
  of Incus ACLs. LAB-ATK (Incus 7.0.1) uses a real Incus ACL.
- Policy sets are enumerated per lab (one element per pair); generation
  is automated via the Ansible playbook but applying an updated set is
  currently manual (`systemctl restart laa-ctf-config.service` after
  editing the set).
- Admin/lecturer management path into student labs is not yet built
  (management access today = operator SSH to hosts + `incus exec`).
- Server-imnehnet SSH over the tailnet did not accept key auth during the
  sprint; physical-host virsh discovery was not re-verified live
  (topology confirmed from the VMs and the repo docs instead).

## 10. Monitoring readiness (P7)

- Existing stack untouched: Docker monitoring (Prometheus/Grafana/Node
  Exporter) on LAB-ENV, Wazuh agent package on LAB-ENV hosts.
- Target model (not built this sprint, by design):
  - STUDENT: attacker/victim online-offline → `incus list` state per lab.
  - LECTURER: per-lab status + later CTF progress from flag submissions.
  - ADMIN: host health (existing node exporter) + per-container
    CPU/RAM/net via Incus `/1.0/metrics` (central scrape on IAAS-MGMT,
    one Grafana, not per-container).
  - Victim-side telemetry (Wazuh) is the MVP priority once Wazuh is
    deployed; attacker-side agents are optional.
- Isolation health: drops are logged (`laa-ctf-*-drop` prefixes) and can
  be scraped/alerted on later.

## 11. Portal integration contract (P8)

```text
lab_id             -> container suffix + policy element (last octet 100+n)
student_id         -> mapped 1:1 to lab_id by the portal
attacker_container -> "atk-<lab_id>"      (host LAB-ATK)
attacker_address   -> 10.30.0.<100+n>
victim_container   -> "victim-<lab_id>"   (host LAB-ENV)
victim_address     -> 10.20.0.<100+n>
runtime_status     -> incus list state per container (RUNNING/STOPPED)
isolation_status   -> derived: paired allow + cross-pair drop counters
ctf_flag_validation: portal stores SHA-256(flag) per lab; student submits
  the flag, portal hashes and compares — no plaintext flag storage needed.
```

Provisioning path: portal → Ansible
`ansible/playbooks/ctf-lab-pair.yml` (requires `lab_atk` inventory group)
or OpenTofu module (future; existing `default` workspace must not be
refactored destructively).

## 12. Git

Branch `feat/lab-provisioning`. Sprint commits:

- `feat(lab): add guided CTF lab pair playbook` (automation readiness)
- `docs(lab): document LaaS MVP validation`

No secrets are stored in the repository.

## 13. Addendum (2026-10-01): host-IP hardening and Student A/B test labs

During the Student A/B policy test provisioning, a gap was found and fixed:
container-to-own-host traffic terminates in the INPUT chain, so the FORWARD
enforcement never saw it. Both hosts now carry an additional `chain input`
(priority 10) in `table inet laa-ctf`:

- LAB-ATK: drops `10.30.0.0/24 -> 192.168.100.30` new connections
  (attacker containers can no longer reach the host SSH on its mgmt IP;
  DNS/DHCP to the bridge IP 10.30.0.1 are unaffected).
- LAB-ENV: drops `@victim_demo -> 192.168.100.20` new connections
  (scoped to student/demo victim IPs only; lab-test and lab-tofu-test
  are unaffected).

Both changes were validated with open-port TCP tests (host sshd :22)
before/after, and policy-set updates for two additional lab pairs
(Student A = last octet .102, Student B = .103) were applied the same way:
extend `allowed_pairs` / `victim_demo`, then `systemctl restart
laa-ctf-config.service`. Backups of the previous nft files are kept
alongside (`*.bak-*`).
