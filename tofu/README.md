# OpenTofu

Folder ini adalah source utama OpenTofu untuk lifecycle container Incus IMNEHNET.
Source ini disalin secara utuh dari checkout aktif `feat/lab-provisioning` yang
diaudit pada `IAAS-MGMT`; state, cache, credential, dan konfigurasi client Incus
tidak ikut dipublikasikan.

## Struktur

- [`version.tf`](version.tf) — deklarasi provider `lxc/incus` dengan batas versi `~> 1.2`.
- [`provider.tf`](provider.tf) — registrasi remote `lab-env` dan `lab-atk`.
- [`variables.tf`](variables.tf) — input nama/image container, remote, network, IPv4, ACL, resource limit, dan desired runtime state.
- [`main.tf`](main.tf) — resource `incus_instance.student_lab` beserta NIC, limit, ACL opsional, dan boot autostart.
- [`outputs.tf`](outputs.tf) — output non-sensitif nama, IPv4, status, dan remote container.
- [`.terraform.lock.hcl`](.terraform.lock.hcl) — lock provider agar instalasi dapat direproduksi.

Alamat pada `provider.tf` adalah alamat RFC1918 management plane yang sudah menjadi
bagian dari arsitektur repository. Trust material Incus tetap berada pada
`/home/iaas/.config/incus` di controller dan tidak disimpan di Git.

## Workspace dan state

Implementasi menggunakan workspace terpisah per environment. Portal memilih
workspace di bawah lock eksklusif sebelum plan/apply agar dua queue job tidak
mengoperasikan state yang salah. Workspace `default` dan container referensi
dilindungi oleh aplikasi.

State lokal (`*.tfstate`, `terraform.tfstate.d/`), plan, cache `.terraform/`, dan
backup state dilarang masuk Git. Jangan mengunggah hasil `tofu state pull` atau
`tofu show -json`.

## Validasi aman

```bash
tofu -chdir=tofu fmt -check -recursive
tofu -chdir=tofu init -backend=false
tofu -chdir=tofu validate
```

`tofu plan`, `apply`, `destroy`, import, dan operasi state hanya boleh dijalankan
dari controller yang berwenang setelah review dan persetujuan operator. GitHub
Actions hanya melakukan format/init tanpa backend/validate dan tidak menjalankan
perubahan infrastruktur.
