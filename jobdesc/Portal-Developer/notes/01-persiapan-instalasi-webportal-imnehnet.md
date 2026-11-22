# 01 — Persiapan Server dan Instalasi Fondasi Webportal IMNEHNET

**Jobdesk:** Portal Developer (PIC: Malik)  
**Proyek:** IMNEHNET — *Rancang Bangun Model IaaS Berbasis Micro-segmentation untuk Meningkatkan Keamanan dan Skalabilitas Praktikum Keamanan Siber*  
**Tahap:** 01 — pembangunan awal dan penyiapan kebutuhan Webportal (sebelum implementasi fitur Admin dan Student)  
**Node aplikasi:** `IAAS-MGMT` (`iaas-mgmt`)  
**Workspace aplikasi:** `/home/iaas/projects/webportal-iaas`  
**Status dokumen:** dokumentasi retrospektif berbasis bukti repository dan audit, bukan catatan eksekusi installer saat pertama kali dijalankan  
**Rujukan source:** [webportal/](../../../webportal/) dan commit [658b8cd](https://github.com/Alief1150/imnehnet-iac/commit/658b8cd2e82375f015450250f87b1a1158aee66f)

> **Penting — provenance.** Audit bertanggal **25 September 2026** menyatakan PHP 8.3, PHP-FPM, Apache, MySQL, dan Git telah tersedia di IAAS-MGMT, tetapi folder aplikasi masih kosong dan Composer/Node.js belum tersedia di `PATH` pada pemeriksaan tersebut. Audit **26 September 2026** selanjutnya memverifikasi aplikasi Laravel 13.33.0/Filament 5.8.4, asset build, dan pengujian. Maka tanggal yang pasti adalah **rentang verifikasi 25–26 September 2026**, bukan tanggal pasti instalasi tiap paket. Contoh perintah instalasi di bawah adalah **prosedur reproduksi**, bukan bukti perintah identik dijalankan Malik pada saat itu. Jangan menjalankannya ulang pada server yang sudah aktif tanpa change plan dan backup.

---

## A. Tujuan dan batas pekerjaan

Tahap ini menyiapkan **management-plane web application** di VM `IAAS-MGMT`: runtime PHP, dependency Laravel, database, panel admin Filament, pipeline frontend, HTTP service, dan background queue. Portal menjadi antarmuka pengelolaan; ia **tidak menggantikan** OpenTofu, Ansible, maupun Incus yang berada pada lapisan orkestrasi/infrastruktur.

**Cakupan Notes 01:** lingkungan pengembangan/deployment, instalasi dan dependensi, pembuatan proyek Laravel, koneksi MySQL, persiapan Filament, frontend assets, queue, verifikasi dasar, serta bukti visual. Pembuatan fitur **Admin Panel** dan **Student Panel** dirinci pada Notes berikutnya.

## B. Konteks infrastruktur dan arsitektur

| Komponen | Peran dalam implementasi | Dasar verifikasi |
| --- | --- | --- |
| `IAAS-MGMT` | VM tempat Laravel, PHP, MySQL, automation controller | [docs/status.md](../../../docs/status.md) |
| Ubuntu Server | Sistem operasi VM pengelola (audit proyek menyebut Ubuntu Server 24.04 LTS) | [Dokumen verifikasi IAAS-MGMT](../../../docs/Alief-DEVOPS-01-Verifikasi-IAAS-MGMT-Ansible-Controller.md) |
| PHP 8.3 & PHP-FPM | Runtime backend dan pemrosesan PHP melalui web server | Audit 25 September; [composer.json](../../../webportal/composer.json) meminta `php:^8.3` |
| Apache HTTP Server | HTTP server/vhost, PHP-FPM dan proxy terminal | [deploy/](../../../webportal/deploy/) |
| MySQL | Database aplikasi dan database inventaris infrastruktur | [config/database.php](../../../webportal/config/database.php) |
| Composer | Dependency manager untuk Laravel/Filament/Pest | [composer.json](../../../webportal/composer.json) |
| Laravel 13 | Framework backend, migrations, Artisan, Eloquent, jobs, routing | [composer.json](../../../webportal/composer.json) |
| Filament 5 | Panel administrasi Laravel | [composer.json](../../../webportal/composer.json), [AdminPanelProvider](../../../webportal/app/Providers/Filament/AdminPanelProvider.php) |
| Node.js + npm | Toolchain kompilasi asset | [package.json](../../../webportal/package.json) |
| Vite 8 + Tailwind CSS 4 | Build asset frontend dan styling | [package.json](../../../webportal/package.json) |
| Database queue worker | Pekerjaan asynchronous untuk lifecycle lab | [config/queue.php](../../../webportal/config/queue.php), [queue supervisor](../../../webportal/scripts/queue-supervisor.sh) |
| Git/GitHub | Version control dan kolaborasi kelompok | repository `imnehnet-iac`, folder `webportal/` |

**Versi harus dibaca sesuai bukti:** `composer.json` menyatakan batas dependensi Laravel `^13.17` dan Filament `^5.0` (bukan angka versi terpasang saat ini); audit 26 September mencatat **Laravel 13.33.0 dan Filament 5.8.4** pada saat verifikasi. `package.json` meminta **Node >=24** pada konfigurasi saat ini; audit September sebelumnya mengamati Node user-local 22.23.3 untuk build, sedangkan [script build terkini](../../../webportal/scripts/build-assets.sh) menggunakan Node **24** melalui nvm. Ini adalah evolusi konfigurasi, bukan kontradiksi yang perlu ditutup-tutupi.

### Topologi konseptual

```text
Pengguna (Admin / Student)
           |
           v
     Web / HTTP(S)
           |
           v
  IAAS-MGMT: Apache + PHP-FPM
           |
           v
     Laravel + Filament
       |          |
       |          +--> Queue jobs (background worker)
       |                        |
       v                        +--> OpenTofu / Ansible / Incus integration
 MySQL application
 + inventory connection
```

## C. Tahapan persiapan dan instalasi

### 1. Verifikasi VM dan tool dasar

Masuk ke `IAAS-MGMT` dengan SSH melalui jalur akses pengelolaan yang sah. Validasi hostname, OS, versi runtime dan service sebelum mengubah apa pun:

```bash
hostnamectl
php -v | head -n 2
php -m | grep -Ei 'pdo_mysql|mysqli|mbstring|xml|curl|zip|bcmath|intl' || true
composer --version
git --version
apache2 -v
mysql --version
systemctl is-active apache2 mysql
```

Jika `composer` belum terpasang atau tidak ditemukan, catat sebagai **belum tersedia**; jangan langsung menginstal di server produksi hanya untuk bukti screenshot.

**Contoh perintah paket sistem untuk pembangunan ulang VM Ubuntu baru** (belum dijalankan sebagai bagian dari dokumentasi ini):

```bash
sudo apt update
sudo apt install -y git curl unzip ca-certificates \
  apache2 mysql-server php8.3 php8.3-cli php8.3-fpm \
  php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
```

Versi minor dan nama paket harus disesuaikan dengan repositori Ubuntu/OS aktual serta kebutuhan `composer check-platform-reqs`. PHP-FPM, Apache, atau MySQL yang sudah berjalan **tidak perlu dipasang ulang**.

### 2. Memasang Composer dan menyiapkan Laravel

**Alur rekonstruksi pada lingkungan baru**:

1. Instal Composer dari sumber resmi menggunakan installer yang diverifikasi checksum/signature sesuai prosedur resmi Composer; jangan menyalin skrip pemasangan dari sumber tidak dikenal.
2. Siapkan direktori proyek untuk aplikasi.
3. Instal dependency berdasarkan `composer.lock`, sehingga versi ter-resolve sesuai source repository.

```bash
cd /home/iaas/projects/webportal-iaas
composer --version
composer validate --no-check-publish
composer check-platform-reqs
php artisan --version
```

Untuk deployment/clone baru, lazimnya gunakan `composer install --no-dev --optimize-autoloader`, **bukan** `composer update` acak pada produksi. Perintah `composer install` adalah mutasi dan **tidak perlu dijalankan untuk pengambilan bukti**. Di tahap awal pengembangan, dependency dev seperti Pint/Pest dapat disertakan pada lingkungan development.

Source utama: [composer.json](../../../webportal/composer.json), [artisan](../../../webportal/artisan), [bootstrap/providers.php](../../../webportal/bootstrap/providers.php).

### 3. Pembuatan proyek Laravel dan struktur aplikasi

Workspace kanonik Laravel berada di:

```text
/home/iaas/projects/webportal-iaas/
├── app/               # Models, Services, Jobs, Filament
├── bootstrap/         # bootstrap Laravel dan providers
├── config/            # database, queue, infrastructure, dst.
├── database/          # migrations, seeders
├── public/            # document root dan asset publik
├── resources/         # Blade, CSS, JS, SVG
├── routes/            # web dan API
├── storage/           # runtime/logs (tidak dipublikasikan ke Git)
├── tests/             # pengujian otomatis
├── artisan
├── composer.json
└── package.json
```

Pada sistem yang sudah aktif, cek keberadaan struktur **tanpa membuat/mengubah apa pun**:

```bash
cd /home/iaas/projects/webportal-iaas
pwd
ls -ld app bootstrap config database public resources routes storage tests
php artisan --version
```

**Keterangan dokumentasi:** audit 25 September membuktikan workspace masih kosong; audit 26 September membuktikan struktur aplikasi sudah dibangun. Ini adalah bukti progres fondasi, bukan klaim bahwa seluruhnya dibuat dengan satu perintah `composer create-project`.

### 4. Menyiapkan MySQL dan koneksi database

Implementasi mengonfigurasi dua koneksi dengan fungsi berbeda:

| Database/koneksi | Kegunaan |
| --- | --- |
| Database aplikasi Laravel (dalam audit disebut `iaas_portal`) | Users, autentikasi, sessions, queue jobs, activity log, migrations |
| Koneksi `imnehnet` | Membaca inventory dan data infrastruktur melalui koneksi terpisah |

File rujukan: [config/database.php](../../../webportal/config/database.php). Koneksi utama dapat diatur sebagai MySQL melalui `DB_CONNECTION=mysql`, sedangkan koneksi kedua menggunakan variabel `IMNEHNET_DB_*`. File contoh [.env.example](../../../webportal/.env.example) **masih memiliki default `DB_CONNECTION=sqlite`**; jangan menyamakannya dengan konfigurasi produksi yang sudah diverifikasi menggunakan MySQL.

**Contoh nama variabel konfigurasi saja — bukan kredensial produksi:**

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iaas_portal
DB_USERNAME=<user-aplikasi>
DB_PASSWORD=<isi-secara-lokal>

IMNEHNET_DB_CONNECTION=mysql
IMNEHNET_DB_HOST=127.0.0.1
IMNEHNET_DB_PORT=3306
IMNEHNET_DB_DATABASE=imnehnet
IMNEHNET_DB_USERNAME=<user-inventaris>
IMNEHNET_DB_PASSWORD=<isi-secara-lokal>
```

File `.env` asli **tidak boleh diunggah, dipotret, atau ditempel dalam dokumentasi**. Jangan menampilkan hasil `php artisan config:show database` mentah jika berpotensi menampilkan detail koneksi. Jangan mengambil screenshot tabel berisi data mahasiswa, IP personal, token, atau password.

Pemeriksaan aman:

```bash
systemctl is-active mysql
mysql --version
cd /home/iaas/projects/webportal-iaas
php artisan migrate:status --no-interaction
```

`migrate:status` hanya menampilkan status migrasi; **jangan menjalankan `migrate`, `migrate:fresh`, `db:wipe` atau SQL penghapusan** untuk keperluan screenshot. Audit 26 September telah mencatat migrasi awal selesai dan **0 pending migrations** pada waktu verifikasi tersebut.

### 5. Menyiapkan Filament Admin Panel

Filament terdaftar sebagai dependency aplikasi. Provider yang dipakai sekarang:

- [AdminPanelProvider.php](../../../webportal/app/Providers/Filament/AdminPanelProvider.php)
- [StudentPanelProvider.php](../../../webportal/app/Providers/Filament/StudentPanelProvider.php)
- [bootstrap/providers.php](../../../webportal/bootstrap/providers.php)

Tahap 01 hanya membuktikan **fondasi panel terpasang dan dapat diakses**, belum membahas fitur CRUD Environments, Users, CTF Management, Activity Logs, atau halaman Student. Fitur itu memiliki dokumentasi lanjutan.

Perintah baca-saja untuk bukti:

```bash
cd /home/iaas/projects/webportal-iaas
composer show filament/filament | sed -n '1,9p'
php artisan route:list --path=admin --except-vendor | head -n 25
```

Jika route vendor tidak tampil karena filter `--except-vendor`, gunakan `php artisan route:list --path=admin | head -n 25`. Jangan menjalankan `filament:install` ulang pada aplikasi existing hanya untuk bukti.

### 6. Node.js, npm, Vite, Tailwind, dan frontend assets

Berdasarkan [package.json](../../../webportal/package.json), stack frontend saat ini mencakup Vite, Tailwind CSS, plugin Laravel Vite, React, Lucide, ApexCharts dan komponen terkait. **Dependency terpasang tidak otomatis membuktikan semua library telah digunakan pada semua halaman.**

`Node >=24` adalah persyaratan package saat ini; [scripts/build-assets.sh](../../../webportal/scripts/build-assets.sh) menggunakan nvm untuk memilih **Node 24**, karena Node sistem lama tidak cocok dengan build terkini.

Verifikasi tanpa build ulang:

```bash
cd /home/iaas/projects/webportal-iaas
node --version
npm --version
test -f package.json && echo "package.json: FOUND"
test -f public/build/manifest.json && echo "Vite manifest: FOUND"
sed -n '1,60p' scripts/build-assets.sh
```

**Prosedur build saat perubahan frontend memang hendak diterapkan**:

```bash
cd /home/iaas/projects/webportal-iaas
bash scripts/build-assets.sh
```

Perintah terakhir **mengubah output asset**, sehingga **bukan** bagian dari audit read-only dan tidak perlu dijalankan lagi sekarang. Audit September 2026 sudah mengamati build Vite berhasil; versinya pada waktu itu dapat berbeda dari keadaan sekarang.

### 7. Web server Apache, PHP-FPM, dan routing Laravel

Apache tersedia pada audit awal, tetapi pada **25 September** masih melayani halaman default Ubuntu dan **belum** menjadi public entrypoint portal. Pada **26 September**, bukti yang dicatat adalah akses aplikasi melalui development listener `php artisan serve` di port 8000. Pada iterasi berikutnya repository menyediakan konfigurasi deployment Apache:

- [deploy/README.md](../../../webportal/deploy/README.md)
- [deploy/install-apache-vhost.sh](../../../webportal/deploy/install-apache-vhost.sh)
- [deploy/apache/imnehnet-portal.conf](../../../webportal/deploy/apache/imnehnet-portal.conf)

Konfigurasi Apache membutuhkan routing `public/index.php`, modul PHP-FPM (`proxy_fcgi`), `rewrite`, dan dukungan proxy/WebSocket untuk terminal. **Keberadaan file deployment tidak sendirinya membuktikan kapan konfigurasi itu diaktifkan**, jadi verifikasi kondisi hidup sebelum menulis klaim HTTP production.

Pemeriksaan baca-saja:

```bash
apache2 -v
sudo apache2ctl -S
sudo apache2ctl -M | grep -Ei 'rewrite|proxy|fcgi|ssl|headers' || true
systemctl is-active apache2 php8.3-fpm
curl -sS -o /dev/null -w 'Local /up: HTTP %{http_code}\n' http://127.0.0.1/up
curl -sS -o /dev/null -w 'Local /admin/login: HTTP %{http_code}\n' http://127.0.0.1/admin/login
```

Tidak menjalankan `deploy/install-apache-vhost.sh`, `a2ensite`, restart Apache, atau mengubah permission untuk sekadar memotret bukti.

### 8. Queue worker dan persiapan background processing

Laravel menggunakan **database queue** yang diatur di [config/queue.php](../../../webportal/config/queue.php). Queue menyelesaikan operasi asynchronous seperti provisioning, penghentian, penghapusan, atau reset lab tanpa memblokir request browser.

Berkas operasional terkait:

- [scripts/queue-worker.sh](../../../webportal/scripts/queue-worker.sh)
- [scripts/queue-supervisor.sh](../../../webportal/scripts/queue-supervisor.sh)
- [scripts/reload-app.sh](../../../webportal/scripts/reload-app.sh)

Pemeriksaan baca-saja:

```bash
cd /home/iaas/projects/webportal-iaas
pgrep -af '[q]ueue:work|[q]ueue-supervisor' || true
php artisan queue:failed --no-interaction
```

Jangan menjalankan `queue:restart`, `queue:clear`, atau menjalankan supervisor kedua untuk sekadar membuat screenshot. Pekerjaan lifecycle yang sedang berlangsung harus tetap aman.

### 9. Kontrol awal keamanan dan validasi

Pada tahap fondasi, aturan minimal:

1. Simpan credential hanya pada environment file yang tidak ter-commit.
2. Jangan memasukkan `vendor/`, `node_modules/`, `storage/logs/`, dump SQL, key, serta state OpenTofu ke Git.
3. Hindari operasi database destruktif pada sistem aktif.
4. Pisahkan database aplikasi dengan inventaris infrastruktur.
5. Gunakan `php artisan` read-only dan pengecekan endpoint untuk bukti.
6. Uji perubahan lewat test suite terarah; pengecekan `php -l` saja tidak mencakup class loading dan fungsionalitas.

Berkas pengujian aplikasi: [scripts/verify.sh](../../../webportal/scripts/verify.sh). **Tidak diklaim bahwa test suite terakhir sudah dijalankan pada saat penyusunan Notes ini.**

## D. Checklist dokumentasi screenshot (bukti visual)

**Folder target dalam GitHub:** `jobdesc/Portal-Developer/images/01-instalasi-webportal/`. Simpan screenshot **dari terminal/browser milik operator** kemudian unggah file PNG/JPG yang sudah disanitasi; *jangan* memotret data rahasia. Di bawah ini adalah daftar bukti yang perlu dikumpulkan, **bukan klaim bahwa file screenshot sudah tersedia**.

| Kode | Nama file yang disarankan | Layar/perintah yang difoto | Caption siap pakai |
| --- | --- | --- | --- |
| SS-01 | `01-hostname-os.png` | `hostnamectl` | Identifikasi VM IAAS-MGMT sebagai server aplikasi Webportal IMNEHNET. |
| SS-02 | `02-php-extensions.png` | `php -v` dan `php -m` yang difilter | Verifikasi runtime PHP dan ekstensi yang diperlukan Laravel/MySQL. |
| SS-03 | `03-composer-laravel.png` | `composer --version` dan `php artisan --version` | Verifikasi Composer dan framework Laravel di workspace aplikasi. |
| SS-04 | `04-mysql-service.png` | `mysql --version`, `systemctl is-active mysql` | Verifikasi layanan MySQL pada management VM. |
| SS-05 | `05-struktur-laravel.png` | `pwd` dan `ls -ld app bootstrap config database public resources routes` | Struktur direktori proyek Laravel sebagai fondasi pengembangan portal. |
| SS-06 | `06-migrasi-database.png` | `php artisan migrate:status --no-interaction` | Status migration Laravel yang telah tersedia pada database aplikasi. |
| SS-07 | `07-filament-package.png` | `composer show filament/filament` | Verifikasi package Filament sebagai framework panel administrasi. |
| SS-08 | `08-node-npm-vite.png` | `node -v`, `npm -v`, dan keberadaan `public/build/manifest.json` | Verifikasi toolchain dan hasil build aset frontend. |
| SS-09 | `09-apache-phpfpm.png` | `apache2 -v`, `systemctl is-active apache2 php8.3-fpm` | Status Apache dan PHP-FPM sebagai komponen penyajian aplikasi. |
| SS-10 | `10-admin-login.png` | Browser pada halaman login admin, **tanpa memasukkan password** | Tampilan akses awal panel administrasi IMNEHNET. |
| SS-11 | `11-queue-worker.png` | `pgrep -af '[q]ueue:work|[q]ueue-supervisor'` | Keberadaan worker untuk proses asynchronous backend. |

**Cara mengambil screenshot:**

1. Masuk ke server lewat SSH, gunakan perintah yang sesuai pada tabel; ambil screenshot **di laptop/PC tempat terminal SSH dibuka**, bukan dengan memasang utilitas screenshot pada server tanpa GUI.
2. Pastikan prompt, perintah, dan output yang relevan terlihat; hindari password/token, isi `.env`, sesi terminal mahasiswa, IP publik pribadi, atau identitas yang tidak diperlukan.
3. Simpan sebagai PNG dengan nama yang direkomendasikan; crop hanya untuk membuang informasi sensitif/tidak relevan tanpa mengubah isi output teknis.
4. Untuk SS-10, ambil gambar browser yang membuka login panel; tidak perlu mengunggah screenshot setelah login jika menampilkan informasi privat.
5. Unggah gambar yang sudah diperiksa ke `jobdesc/Portal-Developer/images/01-instalasi-webportal/` lewat GitHub **Add file → Upload files** (atau proses Git yang disepakati tim). Jangan membuat file gambar kosong untuk mengklaim bukti.
6. Setelah gambar tersedia, tambahkan tautan Markdown relatif seperti contoh:

```markdown
![SS-03 — Verifikasi Composer dan Laravel](../images/01-instalasi-webportal/03-composer-laravel.png)
```

**Bundel command read-only untuk mengumpulkan SS-01 s.d. SS-09 dan SS-11 (jalankan satu blok per screenshot, bukan semuanya untuk satu gambar):**

```bash
cd /home/iaas/projects/webportal-iaas || exit 1
# SS-01
hostnamectl
# SS-02
php -v | head -n 2
php -m | grep -Ei 'pdo_mysql|mysqli|mbstring|xml|curl|zip|bcmath|intl' || true
# SS-03
composer --version
php artisan --version
# SS-04
mysql --version
systemctl is-active mysql
# SS-05
pwd
ls -ld app bootstrap config database public resources routes
# SS-06
php artisan migrate:status --no-interaction
# SS-07
composer show filament/filament | sed -n '1,12p'
# SS-08
node -v
npm -v
test -f public/build/manifest.json && echo 'Vite manifest: FOUND'
# SS-09
apache2 -v
systemctl is-active apache2 php8.3-fpm
# SS-11
pgrep -af '[q]ueue:work|[q]ueue-supervisor' || true
```

> Sebelum menjalankan `php artisan migrate:status` dan `queue:failed`, pastikan terminal menggunakan aplikasi dan environment yang benar. Perintah tersebut tidak memigrasikan atau menghapus data, tetapi tetap membaca kondisi database aktual. Jika versi/perintah tertentu tidak tersedia, catat apa adanya.

## E. Hasil yang dapat dinyatakan dan keterbatasan

### Hasil terverifikasi dari audit lama

- **25 September 2026:** tersedia PHP 8.3, PHP-FPM, Apache, MySQL, Git pada IAAS-MGMT; folder Webportal belum berisi project Laravel.
- **26 September 2026:** aplikasi Laravel/Filament telah tersedia pada workspace kanonik; audit mencatat Laravel 13.33.0/Filament 5.8.4, aset frontend yang berhasil dibangun, pengujian **42 tests / 154 assertions**, migrasi awal dengan **0 pending** pada saat verifikasi, serta endpoint pengembangan berjalan.
- **22 November 2026:** source perkembangan Webportal sebanyak **74 file** tercatat dalam commit [658b8cd](https://github.com/Alief1150/imnehnet-iac/commit/658b8cd2e82375f015450250f87b1a1158aee66f) di branch `main`. Ini **bukti sinkronisasi source**, bukan bukti bahwa seluruh langkah instalasi dilakukan ulang pada tanggal tersebut.

### Batasan

- Tidak ada rekaman instalasi awal setiap paket yang membuktikan **perintah dan jam instalasi persis**; instruksi `apt` dan instalasi dependency di dokumen ini bersifat rekonstruksi untuk keperluan reproducibility.
- Status Apache, Node, database, queue, serta versi Filament **saat ini** harus dibuktikan ulang dengan screenshot read-only; tidak otomatis sama dengan keadaan September.
- Screenshot SS-01 s.d. SS-11 **belum dilampirkan** sampai operator mengambil dan mengunggahnya.
- Notes 01 **belum menyatakan** Admin CRUD, CTF Management, micro-segmentation, atau fitur Student telah lulus pengujian menyeluruh.

## F. Kesimpulan dan kelanjutan

Tahap 01 merangkum fondasi pembangunan Webportal IMNEHNET: pengenalan VM IAAS-MGMT, runtime PHP dan Composer, aplikasi Laravel, penyimpanan MySQL, panel Filament, toolchain Node/Vite/Tailwind, mekanisme HTTP Apache/PHP-FPM, serta queue worker untuk lifecycle asynchronous. Audit proyek menunjukkan adanya peralihan dari server dengan workspace kosong menuju kontrol-plane Laravel/Filament yang telah dapat diuji pada akhir September 2026.

**Tahap 02 (dokumen terpisah):** penyusunan struktur dan fitur **Admin Panel**, dimulai dari konfigurasi Filament, dashboard, pengelolaan pengguna, pengelolaan environment, CTF Management, dan activity logs. **Student Panel dibahas setelah seri Admin selesai.**


### Referensi internal

- [Status proyek](../../../docs/status.md)
- [Verifikasi IAAS-MGMT](../../../docs/Alief-DEVOPS-01-Verifikasi-IAAS-MGMT-Ansible-Controller.md)
- [Source Webportal](../../../webportal/)
- [Dependency PHP](../../../webportal/composer.json)
- [Dependency Node](../../../webportal/package.json)
- [Koneksi database](../../../webportal/config/database.php)
- [Pengaturan queue](../../../webportal/config/queue.php)
- [Prosedur deployment Apache](../../../webportal/deploy/README.md)
- [Build frontend](../../../webportal/scripts/build-assets.sh)
- [Commit source 22 November](https://github.com/Alief1150/imnehnet-iac/commit/658b8cd2e82375f015450250f87b1a1158aee66f)
