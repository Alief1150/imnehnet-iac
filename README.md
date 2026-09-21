# IMNEHNET IaaS

Central source of truth for the IMNEHNET PBL infrastructure.

Project title: **Rancang Bangun Model IaaS Berbasis Micro-segmentation untuk Meningkatkan Keamanan dan Skalabilitas Praktikum Keamanan Siber.**

This repository is intentionally private. It is the shared knowledge base for:

- Hermes Agent running on the `imnehnet` host
- ChatGPT through the connected GitHub integration
- Human operators and project members

## Operating model

GitHub is the authoritative project memory. Hermes automatically loads `AGENTS.md` when started from this repository. ChatGPT should read the latest repository state before making architecture or operational recommendations.

The repository stores documentation, IaC, Ansible, inventory templates, runbooks, change history, and a checksum-indexed project document database under `documents/`. It must never contain credentials, API keys, Tailscale auth keys, private keys, `.env` files, or OpenTofu state.

## Quick start on the IMNEHNET host

```bash
cd ~/imnehnet-iac
./scripts/bootstrap-hermes.sh
hermes
```

For repository changes, Hermes should finish by running:

```bash
./scripts/safe-sync.sh "docs: describe the change"
```

The configured post-commit hook pushes commits automatically to the current branch.

## Important

GitHub Actions in this repository only validate changes. They must not run `tofu apply`, alter Incus, change firewall/routing, or deploy to the physical host.
