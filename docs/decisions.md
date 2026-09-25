# Architecture Decision Log

Keep entries append-only. If a decision changes, add a new dated entry rather than rewriting an old one.

## 2026-09-16 — Virtualization remains required

The PBL must retain virtualization because the educational goal is to use one physical host for multiple laboratory environments. The implementation is not required to use Proxmox specifically.

## 2026-09-16 — Preferred virtualization stack

Preferred low-overhead stack: Linux host + Incus management + KVM/QEMU VMs. OpenTofu provisions infrastructure through the Incus layer; Ansible configures guests.

## 2026-09-16 — No lightweight containers in the current design

The current design uses VMs for the relevant lab environments. Do not reintroduce lightweight containers unless the project owner explicitly changes this decision.

## 2026-09-16 — GitHub as shared knowledge center

A private GitHub repository is the shared source of truth for Hermes, ChatGPT, and human operators. Hermes may maintain local memory, but project architecture and operational facts must be synchronized back to this repository.

## 2026-09-16 — GitHub Actions are validation-only

GitHub-hosted Actions may lint and validate repository content, but they must not apply infrastructure changes or connect to the private management plane. Live changes remain local and approval-gated.

## 2026-09-25 — Two VM boundaries with per-student Incus system containers

This decision supersedes **2026-09-16 — No lightweight containers in the current design** without deleting that historical entry.

The target architecture uses two Ubuntu Server VMs on the Linux Mint KVM/QEMU host:

- `IAAS-MGMT` is the management/controller plane for OpenTofu, Ansible, MySQL, and the planned Laravel/Filament backend.
- `LAB-ENV` is the compute/lab plane and runs Incus system containers as isolated student environments.

The change aligns the repository with the final reviewed proposal and with the verified prototype (`incusbr0`, `lab-test`, and `lab-tofu-test`). Containers reduce per-student resource overhead on the 16 GB-class single host, while the two VMs retain a boundary between management and lab workloads.

Micro-segmentation remains default-deny. Student containers must not receive direct access to the Incus API/socket or unrestricted access to the management network. The strategy for multi-user OpenTofu state is still undecided and requires a separate decision before mass provisioning.

## 2026-09-25 — Portal stack is Laravel, Filament, and MySQL

The planned portal/backend stack is Laravel REST API with Filament and MySQL on `IAAS-MGMT`. OpenTofu remains responsible for Incus resource lifecycle and Ansible remains responsible for post-provisioning configuration. The backend will orchestrate those tools asynchronously rather than duplicating their responsibilities.
