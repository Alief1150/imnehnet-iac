# Pengujian DevOps

## Audit read-only 16 November 2026

Source yang diuji ditautkan dari
[`tofu/`](../../../tofu/) dan [`ansible/`](../../../ansible/). Tidak ada salinan
source di folder dokumentasi ini.

| Pemeriksaan | Hasil | Bukti/batas klaim |
| --- | --- | --- |
| Akses `IAAS-MGMT` | PASS | Public-key-only SSH berhasil sebagai user `iaas` ke hostname `iaas-mgmt`; `sudo -n` tidak tersedia. |
| OpenTofu | PASS | `OpenTofu v1.12.6`; `tofu validate -no-color` menghasilkan `Success! The configuration is valid.` |
| Provider Incus | PASS | Konfigurasi dan state sama-sama merujuk `registry.opentofu.org/lxc/incus`; provider terpasang `v1.2.0`. |
| Workspace aktif | PASS | `ctf-attacker-104`; resource address `incus_instance.student_lab`. |
| Output workspace | PASS | Output non-sensitif: `attacker-user-31`, remote `lab-atk`, status `Running`, IPv4 `10.30.0.104`. |
| Ansible | PASS | `ansible [core 2.16.3]`. |
| Ping inventory | PASS | `iaas-mgmt` dan `lab-env` mengembalikan `SUCCESS`, `changed: false`, dan `pong`. |
| Syntax enam playbook | PASS dengan warning | Seluruh playbook menghasilkan baris `playbook: ...`; warning muncul karena grup `student_labs` dan `lab_atk` belum ada pada inventory aktif. |
| Inventory Incus | PASS | Tiga victim pada `lab-env` dan tiga attacker pada `lab-atk` teramati `RUNNING`. |

Container yang teramati:

- `lab-env`: `victim-user-23`, `victim-user-25`, `victim-user-31`;
- `lab-atk`: `attacker-user-23`, `attacker-user-25`, `attacker-user-31`.

## Targeted testing Progress 2

Hasil targeted test yang dijalankan operator pada source portal aktif:

| Test | Hasil | Assertions |
| --- | ---: | ---: |
| `OpenTofuWorkspaceTest` | 3 PASS | 14 |
| `TwoPhaseProvisioningTest` | 4 PASS | 13 |
| **Total targeted DevOps** | **7 PASS** | **27** |

Hasil full suite sebelumnya adalah **308 PASS dan 55 FAIL**. Full suite tidak
dijalankan ulang untuk finalisasi ini. Kegagalan tersebut dicatat sebagai
known issues dan ditunda ke iterasi development berikutnya; hasil targeted test
tidak mengubah 55 kegagalan menjadi PASS.

Targeted test memakai test database/fake driver dan tidak menjalankan
provisioning nyata. Karena itu, hasil ini tidak membuktikan `tofu apply`,
konfigurasi Ansible, perubahan firewall, atau lifecycle container Incus secara
end-to-end pada infrastruktur aktif.

## Kendala dan batas pengujian

- Inventory aktif belum mendefinisikan `lab_atk` dan `student_labs`; akibatnya syntax-check memberi warning untuk playbook yang menargetkan dua grup tersebut.
- Syntax-check membuktikan syntax dapat diparsing, bukan bahwa setiap task berhasil dijalankan atau idempotent.
- Ping Ansible hanya memverifikasi controller dan `LAB-ENV`; konektivitas berbasis inventory ke `LAB-ATK` dan container mahasiswa belum teruji.
- Tidak ada `tofu plan`, `apply`, `destroy`, import, atau operasi state yang dijalankan.
- Tidak ada playbook mutasi yang dijalankan.
- Tidak ada pengujian Laravel yang dijalankan pada source produksi karena tree tersebut memiliki banyak perubahan lokal dan test suite dapat menulis cache, log, atau database.
- Enam container berstatus `RUNNING` tidak membuktikan micro-segmentation, akses aplikasi, atau seluruh lifecycle portal telah lulus.
- Source staging belum pernah menjalankan `apply`, `destroy`, provisioning, atau
  playbook mutasi. Validasi staging dibatasi pada format, parsing, syntax/lint,
  pemeriksaan repository, dan perbandingan hash/provenance.
- `ansible-lint` pada source staging semula menemukan 10 pelanggaran: delapan
  pipeline shell tanpa `pipefail` dan dua baris terlalu panjang. Dua playbook
  diperbaiki di staging, lalu lint diulang sampai lulus profil `production`
  dengan 0 failure dan 0 warning.
- Enam playbook staging juga lolos `ansible-playbook --syntax-check` menggunakan
  inventory `.example` beralamat TEST-NET-1; tidak ada koneksi atau task yang
  dijalankan dalam pemeriksaan syntax tersebut.

Dengan batas tersebut, audit tidak menyatakan seluruh pengujian IMNEHNET PASS.
