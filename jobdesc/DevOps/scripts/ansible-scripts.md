# Dokumentasi Ansible Scripts — IMNEHNET

**PIC:** Alief · **Kategori:** Configuration Management, Post-Provisioning, Infrastructure Diagnostics · **Sumber:** [direktori `ansible/`](../../../ansible/)

## File konfigurasi

| File | Fungsi | Bukti visual dari ZIP audit V2 |
| --- | --- | --- |
| [ansible.cfg](../../../ansible/ansible.cfg) | Opsi controller: inventory, SSH, host-key checking, timeout, roles path, pipelining. | `ansible-ansible.png` |
| [hosts.ini.example](../../../ansible/inventory/hosts.ini.example) | Contoh inventory yang disanitasi untuk host/group; bukan inventory produksi. | `ansible-hosts-ini-example.png` |

## Enam playbook utama

| Playbook | Kategori | Fungsi | Screenshot sumber |
| --- | --- | --- | --- |
| [controller-check.yml](../../../ansible/playbooks/controller-check.yml) | Diagnostic | Verifikasi hostname, distribusi, alamat IP dan Python controller `IAAS-MGMT`. | `ansible-controller-check.png` |
| [lab-env-check.yml](../../../ansible/playbooks/lab-env-check.yml) | Diagnostic | Verifikasi koneksi, fakta host `LAB-ENV`, instalasi dan versi Incus. | `ansible-lab-env-check.png` |
| [container-check.yml](../../../ansible/playbooks/container-check.yml) | Diagnostic | Pemeriksaan konektivitas, hostname, alamat, dan uptime target `student_labs`. | `ansible-container-check.png` |
| [install-incus-wrapper.yml](../../../ansible/playbooks/install-incus-wrapper.yml) | Setup Infrastructure | Instalasi wrapper Incus terbatas dan sudoers khusus untuk operasi terotorisasi. | `ansible-install-incus-wrapper.png` |
| [student-bootstrap.yml](../../../ansible/playbooks/student-bootstrap.yml) | Provisioning | Verifikasi container, pembuatan user mahasiswa, instalasi paket dasar, validasi lingkungan setelah container tersedia. | `ansible-bootstrap-check.png` |
| [ctf-lab-pair.yml](../../../ansible/playbooks/ctf-lab-pair.yml) | Provisioning CTF | Penyiapan victim di `lab_env` dan attacker di `lab_atk` serta layanan skenario CTF. | `ansible-ctf-lab-pair.png` |

## Alur integrasi

`Laravel queue → OpenTofu (container) → Ansible (bootstrap/configure) → verifikasi status`. Playbook diagnostik bukan pengganti verifikasi layanan runtime; syntax-check menguji parser YAML/Ansible, bukan konektivitas atau eksekusi task nyata.

## Keamanan dan batas pengujian

- Jangan masukkan inventory aktif, private key, `.env`, atau token ke repository.
- Playbook provisioning/instalasi **dapat memodifikasi host atau container**. Jangan menjalankannya pada produksi hanya untuk dokumentasi.
- Catatan struktur [struktur-ansible](../notes/struktur-ansible.md), [playbook utama](../notes/ansible-playbook-important.md), dan [integrasi Laravel](../notes/integrasi-laravel.md).
- [Inventaris screenshot](../images/README.md). **Status:** screenshot sumber ZIP V2 belum terunggah sebagai file biner di repository.
