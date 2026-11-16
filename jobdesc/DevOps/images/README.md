# Bukti Visual DevOps — Audit V2

Screenshot berasal dari unggahan `Audit File DevOps_V2.zip` (16 November 2026). Inventaris sumber:

## OpenTofu (`Gambar SS/opentofu/`)

- `tofu-version.png` — deklarasi provider
- `tofu-provider.png` — konfigurasi remote Incus
- `tofu-variables.png` — parameter input
- `tofu-main.png` — resource container
- `tofu-outputs.png` — output resource

## Ansible (`Gambar SS/ansible/`)

- `ansible-ansible.png` — konfigurasi Ansible
- `ansible-hosts-ini-example.png` — template inventory
- `ansible-controller-check.png` — diagnostic controller
- `ansible-lab-env-check.png` — diagnostic lab-env
- `ansible-container-check.png` — diagnostic container
- `ansible-install-incus-wrapper.png` — wrapper Incus
- `ansible-bootstrap-check.png` — student bootstrap
- `ansible-ctf-lab-pair.png` — paired CTF

## Bukti Git

- `fetch-origin-main.png` — screenshot sinkronisasi/inspeksi sumber Git.

**Status unggahan: SELESAI.** Ke-14 screenshot PNG asli dari Audit DevOps V2 sudah di-commit ke folder `opentofu/`, `ansible/`, dan `git/` pada branch dokumentasi. Semua gambar direferensikan langsung dari [dokumentasi OpenTofu](../scripts/opentofu-scripts.md), [dokumentasi Ansible](../scripts/ansible-scripts.md), dan [catatan progress](../notes/progress-2.md). Screenshot merupakan bukti source/configuration review, bukan bukti provisioning live.
