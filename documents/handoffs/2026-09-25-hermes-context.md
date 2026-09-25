# Context untuk Hermes - IMNEHNET Breakpoint 2026-09-25

Gunakan file `IMNEHNET_Backend_Automation_Breakpoint_2026-09-25.md` sebagai handoff utama.

## Ringkasan yang wajib dipahami

- Repo: `Alief1150/imnehnet-iac`.
- IAAS-MGMT: management/controller plane (`192.168.100.10`).
- LAB-ENV: Incus compute/lab plane (`192.168.100.20`).
- Incus internal network aktif: `incusbr0`, `10.20.0.0/24`.
- OpenTofu resource referensi: `incus_instance.student_lab`.
- Environment referensi: `lab-tofu-test`, IP `10.20.0.202`, runtime `RUNNING`.
- OpenTofu output dan state sudah valid pada workspace `default`.
- Ansible dari IAAS-MGMT ke LAB-ENV sukses.
- Wrapper `/usr/local/sbin/imnehnet-incus` sudah dapat dipanggil `sudo -n` tanpa prompt password.
- `student-bootstrap.yml` sukses `ok=7`, `failed=0`.
- Database `imnehnet`, table `lab_environments`, row `lab-tofu-test` sekarang `status=READY`, `runtime_status=Running`.
- Laravel/Filament belum dibuat. `/home/iaas/projects/webportal-iaas` kosong.
- PHP/PHP-FPM, Apache, MySQL tersedia; Composer dan Node/npm belum tersedia di PATH pada audit terakhir.

## Gap repository yang harus diperbaiki

`AGENTS.md`, `docs/architecture.md`, `docs/networking.md`, dan `docs/decisions.md` masih mengandung desain legacy VM-centric dan subnet `10.10.x`. Jangan hapus sejarah decision lama. Tambahkan decision superseding dan update target architecture agar konsisten dengan proposal final dan prototype live.

`docs/status.md` per 2026-09-24 sudah mengenali prototype Incus/OpenTofu, tetapi belum mencatat keberhasilan MySQL READY, Ansible bootstrap, dan wrapper Incus non-interaktif pada 2026-09-25.

## Instruksi kerja

1. Lakukan read-only verification terhadap fakta runtime yang dapat berubah.
2. Jangan menjalankan `tofu apply`, destroy, firewall/network changes, atau playbook mutating hanya untuk dokumentasi.
3. Update source-of-truth files secara konsisten.
4. Tambahkan handoff MD dan PDF ke folder dokumentasi yang sesuai.
5. Jangan commit state, secret, `.env`, password, raw audit log, private key, atau identity mapping.
6. Review `git diff` dan `git status` sebelum commit.
7. Gunakan safe-sync setelah verifikasi.

Suggested commit:

```text
docs: record 2026-09-25 backend automation breakpoint
```

Suggested sync:

```bash
cd ~/imnehnet-iac
./scripts/safe-sync.sh "docs: record 2026-09-25 backend automation breakpoint"
```
