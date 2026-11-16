# Dokumentasi OpenTofu Scripts — IMNEHNET

**PIC:** Alief · **Kategori:** Infrastructure as Code (IaC), provider Incus, provisioning container · **Sumber:** [direktori `tofu/`](../../../tofu/)

## Arsitektur

OpenTofu mendeklarasikan resource container Incus melalui `incus_instance.student_lab`. Satu modul parameterized dapat digunakan untuk container victim di `lab-env` atau attacker di `lab-atk`; ini bukan klaim bahwa satu eksekusi modul membuat kedua container sekaligus. Portal Laravel mengorkestrasi pemilihan workspace dan lifecycle; Ansible menjalankan konfigurasi *post-provisioning*.

| File | Kategori | Penjelasan | Bukti visual dari ZIP audit V2 |
| --- | --- | --- | --- |
| [version.tf](../../../tofu/version.tf) | Dependency Management | Mendeklarasikan source provider `lxc/incus` dan constraint versi `~> 1.2`; **bukan** menentukan versi executable OpenTofu. | `tofu-version.png` |
| [provider.tf](../../../tofu/provider.tf) | Infrastructure Connectivity | Mengatur koneksi provider Incus menuju `lab-env` (victim) dan `lab-atk` (attacker). Default remote adalah `lab-env`. | `tofu-provider.png` |
| [variables.tf](../../../tofu/variables.tf) | Input Configuration | Parameter nama/image container, remote dan jaringan Incus, IPv4 statis, ACL opsional, limit CPU/RAM, dan desired running state; remote divalidasi. | `tofu-variables.png` |
| [main.tf](../../../tofu/main.tf) | Resource Provisioning | Menetapkan `incus_instance.student_lab`: profile, status, resource limit, `eth0`, IPv4 statis opsional, ACL opsional, serta `wait_for` IPv4. | `tofu-main.png` |
| [outputs.tf](../../../tofu/outputs.tf) | Infrastructure Output | Mengekspos nama, alamat IPv4, status container, dan remote untuk konsumsi portal. | `tofu-outputs.png` |

## Penjelasan implementasi

1. **Provider dan dependency** — provider Incus menggunakan file konfigurasi client yang ditentukan dalam source; jangan commit sertifikat pribadi atau credential.
2. **Penempatan compute plane** — victim dan attacker dipisahkan melalui nilai `incus_remote`, agar tidak mengasumsikan semua container berada pada host yang sama.
3. **Resource dan jaringan** — `main.tf` mendefinisikan CPU/memori dan device jaringan hanya saat `static_ipv4` tersedia. Parameter `security_acls` opsional untuk penerapan ACL pada attacker bila didukung.
4. **Lifecycle dan integrasi** — `desired_running` mengatur desired state; output dipakai aplikasi untuk merekonsiliasi identitas dan alamat container. State/workspace tidak boleh dipublikasikan sebagai bukti.
5. **Pembatasan pengujian** — `tofu fmt`, `validate`, dan targeted test tidak membuktikan `tofu apply` live berhasil.

## Bukti dan catatan

- Catatan [resource-opentofu](../notes/resource-opentofu.md), [file penting OpenTofu](../notes/opentofu-tf-important.md), dan [audit arsitektur](../notes/arsitektur-konfigurasi-audit.md).
- [Inventaris screenshot](../images/README.md): sumber `Gambar SS/opentofu/` pada ZIP `Audit File DevOps_V2.zip`.
- **Status lampiran:** gambar ZIP V2 belum terunggah sebagai file biner di repository; nama file di tabel adalah identitas screenshot sumber, bukan tautan GitHub yang sudah aktif.
