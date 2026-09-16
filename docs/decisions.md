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
