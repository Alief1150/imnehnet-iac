# IMNEHNET Backend & Automation Breakpoint

**Tanggal:** 25 September 2026  
**Repository:** `Alief1150/imnehnet-iac`  
**Klasifikasi:** handoff implementasi; bukan pengganti verifikasi live

## Ringkasan

Jalur automation inti telah dibuktikan untuk satu environment referensi:

```text
IAAS-MGMT -> OpenTofu -> LAB-ENV/Incus -> lab-tofu-test
IAAS-MGMT -> Ansible  -> LAB-ENV/Incus -> bootstrap container
IAAS-MGMT -> MySQL    -> environment mapping/status
```

Fondasi provisioning tidak perlu dibangun ulang dari nol. Pekerjaan berikutnya adalah generalisasi multi-user, backend Laravel/Filament, lifecycle orchestration, web terminal, micro-segmentation, observability, dan pengujian.

## Topologi target dan prototype

```text
Admin / Lecturer
       |
   Tailscale / management network 192.168.100.0/24
       |
       +-- IAAS-MGMT (192.168.100.10)
       |     OpenTofu, Ansible, MySQL
       |     Laravel + Filament: belum dibuat
       |
       +-- LAB-ENV (192.168.100.20)
             Incus
             incusbr0 (10.20.0.1/24)
                +-- lab-test      (10.20.0.31)
                +-- lab-tofu-test (10.20.0.202)
```

Peran komponen:

- OpenTofu membuat dan mengelola resource Incus.
- Ansible mengonfigurasi OS, user, dan package setelah container tersedia.
- MySQL menyimpan mapping environment, status orkestrasi, status runtime, IP, dan metadata resource.
- Incus menjalankan system container mahasiswa pada LAB-ENV.
- Laravel REST API dan Filament menjadi target portal/backend, tetapi belum dibuat atau di-deploy.

## OpenTofu

Lokasi controller:

```text
/home/iaas/imnehnet-iac/tofu
```

Verifikasi ulang read-only pada 25 September 2026:

```text
workspace: default
resource:  incus_instance.student_lab
name:      lab-tofu-test
IPv4:      10.20.0.202
runtime:   Running
```

Konfigurasi saat ini masih proof-of-concept satu environment. Strategi state multi-user—workspace per environment, `for_each`, atau module/resource map—belum diputuskan. Jangan menganggap workspace-per-user sebagai keputusan final sebelum dicatat di `docs/decisions.md`.

## Ansible dan Incus

Lokasi controller:

```text
/home/iaas/imnehnet-iac/ansible
```

Verifikasi ulang read-only pada 25 September 2026:

- `ansible.builtin.ping` dari IAAS-MGMT ke `lab-env`: `SUCCESS`, `changed=false`, `pong`.
- Wrapper `/usr/local/sbin/imnehnet-incus` dapat dipanggil melalui `sudo -n` pada LAB-ENV.
- Wrapper menampilkan `lab-test` dan `lab-tofu-test` dalam keadaan `RUNNING`.
- `lab-tofu-test` tetap memiliki IPv4 `10.20.0.202`.

Environment SSH noninteraktif perlu memakai locale valid, misalnya:

```bash
export LANG=C.UTF-8
export LC_ALL=C.UTF-8
```

Tanpa itu, Ansible pada controller dapat berhenti dengan `unsupported locale setting` sebelum menghubungi target.

Bukti breakpoint yang diberikan mencatat:

```text
student-bootstrap.yml: ok=7 changed=0 unreachable=0 failed=0
student UID:           1001
```

Playbook mutating tidak dijalankan ulang hanya untuk dokumentasi.

## MySQL mapping

Bukti breakpoint yang diberikan mencatat:

```text
database:          imnehnet
table:             lab_environments
container_name:    lab-tofu-test
tofu_workspace:    default
internal_ip:       10.20.0.202
status:            READY
runtime_status:    Running
```

`status` merepresentasikan workflow orkestrasi, sedangkan `runtime_status` merepresentasikan keadaan aktual Incus. Query ulang tanpa credential ditolak oleh MySQL dan tidak dipaksa; tidak ada password, `.env`, atau option file yang dibaca untuk dokumentasi ini.

## Laravel dan Filament

Verifikasi ulang pada IAAS-MGMT:

```text
/home/iaas/projects/webportal-iaas
```

Direktori tersedia tetapi kosong. Belum ada:

- `artisan`;
- `composer.json` atau `package.json`;
- source Laravel/Filament;
- migration atau `.env` aplikasi;
- Git repository aplikasi;
- deployment `/admin`;
- proses Laravel.

PHP 8.3, PHP-FPM, Apache, dan MySQL tersedia. Apache port 80 masih menampilkan halaman default Ubuntu. Grafana menggunakan port 3000. Composer dan Node.js/npm belum tersedia di `PATH` pada verifikasi terakhir.

Baseline berikutnya adalah membuat project baru di:

```text
/home/iaas/projects/webportal-iaas
```

Stack target mengikuti proposal final: Laravel REST API, Filament, dan MySQL. Provisioning sebaiknya asynchronous melalui queue agar request browser tidak menunggu OpenTofu dan Ansible.

## Target API

```text
GET    /api/environments
GET    /api/environments/{id}
POST   /api/environments
POST   /api/environments/{id}/start
POST   /api/environments/{id}/stop
POST   /api/environments/{id}/reset
DELETE /api/environments/{id}
GET    /api/environments/{id}/status
```

Flow CREATE yang dituju:

```text
validate/auth
  -> MySQL PROVISIONING
  -> OpenTofu apply
  -> read non-sensitive outputs
  -> MySQL CONFIGURING
  -> Ansible bootstrap
  -> validation
  -> MySQL READY
```

## Pekerjaan lanjutan

1. Generalisasi `student-bootstrap.yml`; jadikan nama container input, pertahankan idempotensi, dan hasilkan output machine-readable.
2. Putuskan strategi OpenTofu multi-user dan catat di `docs/decisions.md` sebelum implementasi massal.
3. Bootstrap Laravel/Filament pada path kanonik dan hubungkan ke schema MySQL yang sudah ada.
4. Implementasikan service layer dan queue untuk OpenTofu/Ansible.
5. Implementasikan lifecycle CREATE/START/STOP/RESET/DELETE dan rekonsiliasi status dengan Incus.
6. Bangun web terminal dengan mapping user ke container yang diizinkan; jangan mengekspos socket Incus atau subnet internal langsung kepada mahasiswa.
7. Terapkan dan uji Incus ACL + nftables dengan default-deny antar-environment.
8. Integrasikan Wazuh serta Prometheus/Grafana dan lakukan pengujian fungsional, keamanan, performa, skalabilitas, serta QoS secara terpisah.

## Batas keamanan

- Jangan commit OpenTofu state, `.terraform/`, `.env`, password, token, private key, raw audit log, atau identity mapping.
- Jangan menjalankan `tofu apply`, destroy, playbook mutating, atau perubahan firewall/network hanya untuk membuktikan dokumentasi.
- Review plan/diff dan rollback sebelum perubahan live.
- Proposal dan handoff menyatakan target/breakpoint; `docs/status.md` tetap menjadi catatan runtime terverifikasi.
