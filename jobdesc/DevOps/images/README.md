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

**Status unggahan:** sumber ZIP telah diperiksa, tetapi screenshot biner **belum di-commit ke GitHub**. Dokumen ini hanya daftar referensi supaya tidak menghasilkan tautan gambar rusak. Untuk publikasi perlu menambahkan 14 PNG ke direktori ini sesuai kelompok `opentofu/`, `ansible/` dan `git/`, lalu mengubah dokumentasi menjadi menyematkan gambar yang benar.
