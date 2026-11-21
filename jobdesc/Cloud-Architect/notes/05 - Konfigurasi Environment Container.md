# Dokumentasi 05 — Konfigurasi Environment Container

## Tujuan

Tahap ini bertujuan untuk menyiapkan environment container pada VM LAB-ENV dan LAB-ATK menggunakan Incus. Konfigurasi meliputi image, profile, storage pool, network, alokasi resource, serta pembuatan container untuk mendukung lingkungan praktikum defender dan attacker.

## Kondisi Awal

Incus telah berhasil dipasang dan diinisialisasi pada VM LAB-ENV dan LAB-ATK. Kedua VM telah disiapkan sebagai host container dengan konfigurasi jaringan yang terpisah untuk lingkungan defender dan attacker.

## Langkah Pengerjaan

### 1. Verifikasi Image Container

Memeriksa image yang tersedia pada masing-masing host Incus untuk memastikan image sistem operasi yang akan digunakan.

Perintah:

```bash
incus image list
```

Pada LAB-ENV tersedia image `imnehnet-gi-victim-v1` sebagai versi khusus untuk kebutuhan lingkungan defender.

Pada LAB-ATK tersedia dua image Kali Linux, yaitu `ctf-attacker-kali-base` sebagai image dasar kebutuhan CTF dan `imnehnet-gi-attacker-v1` sebagai image dengan versi khusus untuk kebutuhan lingkungan attacker.

### 2. Verifikasi Storage Pool

Memeriksa storage pool yang digunakan oleh Incus untuk menyimpan data container.

Perintah:

```bash
incus storage list
```

Storage pool yang digunakan pada kedua host adalah `default` dengan driver `dir`.

### 3. Verifikasi Profile

Memeriksa profile yang digunakan sebagai konfigurasi dasar container.

Perintah:

```bash
incus profile list
```

Kedua host menggunakan profile `default` sebagai konfigurasi dasar container.

### 4. Konfigurasi Resource Container

Container dialokasikan resource agar penggunaan CPU dan RAM tetap terkontrol.

Alokasi resource yang digunakan:

| Resource | Alokasi |
|---|---|
| CPU | 2 Core |
| Memory | 2048 MiB |
| Profile | default |
| Storage Pool | default |

Konfigurasi resource diterapkan pada container di lingkungan defender dan attacker.

### 5. Konfigurasi Network Environment Defender

Environment defender berada pada VM LAB-ENV dengan subnet `10.20.0.0/24`.

Network bridge yang digunakan adalah `incusbr0`.

Container defender yang telah dibuat:

| Nama Container | IP Address | Status |
|---|---|---|
| victim-user-16 | 10.20.0.100 | RUNNING |
| victim-user-23 | 10.20.0.102 | RUNNING |

### 6. Konfigurasi Network Environment Attacker

Environment attacker berada pada VM LAB-ATK dengan subnet `10.30.0.0/24`.

Network bridge yang digunakan adalah `incusbr0`.

Container attacker yang telah dibuat:

| Nama Container | IP Address | Status |
|---|---|---|
| attacker-user-16 | 10.30.0.100 | RUNNING |
| attacker-user-23 | 10.30.0.102 | RUNNING |

### 7. Verifikasi Container

Memeriksa daftar container pada masing-masing host Incus untuk memastikan container telah dibuat dan berjalan.

Perintah:

```bash
incus list
```

Memeriksa konfigurasi salah satu container secara lebih detail:

```bash
incus config show victim-user-16 --expanded
```

Untuk container attacker:

```bash
incus config show attacker-user-16 --expanded
```

### 8. Verifikasi Akses Container

Memastikan container dapat diakses melalui terminal menggunakan perintah:

```bash
incus exec victim-user-16 -- bash
```

Untuk container attacker:

```bash
incus exec attacker-user-16 -- bash
```

## Dokumentasi

1. Daftar image container pada LAB-ENV dan LAB-ATK.
2. Konfigurasi storage pool pada kedua host.
3. Daftar profile Incus.
4. Konfigurasi resource container.
5. Daftar container defender pada LAB-ENV.
6. Daftar container attacker pada LAB-ATK.
7. Hasil pemeriksaan konfigurasi container.
8. Bukti akses terminal container.

## Hasil Tahap

Environment container berhasil disiapkan pada LAB-ENV dan LAB-ATK. LAB-ENV digunakan untuk lingkungan praktikum defender dengan subnet 10.20.0.0/24, sedangkan LAB-ATK digunakan untuk lingkungan praktikum attacker dengan subnet 10.30.0.0/24.

Container telah memiliki alokasi resource CPU dan RAM serta menggunakan profile dan storage pool default.

## Kesimpulan

Konfigurasi environment container telah dilakukan sebagai bagian dari penyediaan lingkungan praktikum defender dan attacker. Pemisahan kedua environment dilakukan melalui host dan subnet yang berbeda untuk mendukung kebutuhan implementasi micro-segmentation.

## Tahap Berikutnya

Melakukan pengujian infrastruktur, meliputi pengujian fungsi container, konektivitas jaringan, micro-segmentation, performa infrastruktur, serta QoS.
