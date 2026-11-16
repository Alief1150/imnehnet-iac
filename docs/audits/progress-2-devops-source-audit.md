# Audit Source DevOps Progress 2

## Scope dan provenance

Audit membandingkan `origin/main` dengan checkout aktif
`feat/lab-provisioning` pada IAAS-MGMT. Source publik dipilih dari object Git
commit `123434f`, bukan dari penyalinan seluruh working tree produksi.

Commit tersebut adalah satu commit lokal yang belum dipush pada branch aktif:

```text
123434f Let one module create both halves of a lab
```

Commit itu mengubah `tofu/main.tf`, `tofu/outputs.tf`, `tofu/provider.tf`, dan
`tofu/variables.tf` dengan 121 penambahan serta 7 penghapusan. Commit
`123434f` tidak menjadi ancestry branch dokumentasi.

## Source yang dipublikasikan

Lima file `.tf` yang sebelumnya belum tersedia pada `main`:

- `tofu/main.tf`
- `tofu/outputs.tf`
- `tofu/provider.tf`
- `tofu/variables.tf`
- `tofu/version.tf`

Enam playbook Ansible yang sebelumnya belum tersedia pada `main`:

- `ansible/playbooks/container-check.yml`
- `ansible/playbooks/controller-check.yml`
- `ansible/playbooks/ctf-lab-pair.yml`
- `ansible/playbooks/install-incus-wrapper.yml`
- `ansible/playbooks/lab-env-check.yml`
- `ansible/playbooks/student-bootstrap.yml`

File pendukung meliputi lock provider, README, `ansible.cfg`, placeholder
`group_vars`/`host_vars`, dan `inventory/hosts.ini.example`.

## Sanitasi dan konsistensi

- Inventory aktif `ansible/inventory/hosts.ini` tidak dipublikasikan.
- Template inventory memakai alamat TEST-NET-1 dan placeholder akun/key.
- State, cache, plan, `.env`, private key, token, dan Incus trust material tidak disertakan.
- Tiga file memiliki perubahan staging yang disengaja: sanitasi inventory
  example serta hardening `pipefail`/quoting pada dua playbook.
- Tiga belas file source lainnya identik dengan source Git aktif yang diaudit.
- Perbandingan 16 hash source produksi setelah persiapan menghasilkan nol perubahan.

## Konflik dan baseline

Branch dokumentasi menggunakan baseline `origin/main` commit `37ef61d`.
Perubahan anggota lain pada panduan Grafana/Prometheus, aset gambar, dan
dokumentasi Cloud Architect tidak memiliki overlap path dengan source Progress
2. Patch staging diterapkan kembali ke baseline terbaru tanpa conflict marker.

## Batas audit

Audit source tidak menjalankan `tofu apply`, `tofu destroy`, provisioning
Ansible, migrasi database, perubahan firewall, queue produksi, atau lifecycle
container Incus.
