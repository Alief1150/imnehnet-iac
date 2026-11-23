# Bukti Pengujian Implementasi Webportal IMNEHNET — IAAS-MGMT

**Tanggal eksekusi:** 23 November 2026 · **Host:** `iaas-mgmt` · **Direktori:** `/home/iaas/projects/webportal-iaas` · **Metode:** skrip PHP CLI Laravel bootstrap, pemeriksaan read-only (tidak menjalankan operasi mutasi).

## 1. Bukti Admin Panel V2

**Waktu menurut output terminal:** `2026-11-23 05:04:41`. Transkrip berikut adalah **ringkasan baris hasil asli yang diberikan operator**, bukan reproduksi seluruh log atau eksekusi ulang oleh penulis dokumentasi.

```text
IMNEHNET ADMIN PANEL - DEEP VERIFICATION V2
Server  : iaas-mgmt
Time    : 2026-11-23 05:04:41
Mode    : READ ONLY

01 - Dashboard                : PASS
02 - Terminal                 : PASS
03 - Infrastructure           : PASS
04 - Monitoring               : PASS
05 - Security                 : PASS
06 - Environments             : PASS
07 - Users                    : PASS
08 - CTF Management           : PASS
09 - Activity Logs            : PASS
10 - System Settings          : PASS

Modules PASS : 10/10
Checks PASS  : 146
Checks FAIL  : 0
OVERALL RESULT: PASS
```

**Cuplikan bukti spesifik modul (baris yang muncul pada terminal operator):**

```text
[PASS] GET /admin
[PASS] System Health: Healthy
[PASS] System Health: Degraded
[PASS] System Health: Critical
[PASS] System Health: Offline
[PASS] System Health: Unknown

[PASS] GET /admin/terminal
[PASS] wire:click -> openAdminSessionFor()
[PASS] wire:click -> closeAdminSessionFor()
[PASS] Open Shell UI binding
[PASS] Close Shell UI binding

[PASS] GET /admin/infrastructure
[PASS] Method statusSummary()
[PASS] Method topology()
[PASS] GET /admin/monitoring
[PASS] Method health()
[PASS] Method resources()
[PASS] Method containerInventory()
[PASS] GET /admin/security
[PASS] wire:click -> toggleDetail()
[PASS] wire:click -> toggleSev()
[PASS] wire:click -> openAlerts()

[PASS] GET /admin/environments
[PASS] Resource table configure()
[INFO] Action: stopAdminPairV2
[INFO] Action: startAdminPairV2
[INFO] Action: resetAdminPair
[INFO] Action: deleteAdminPair

[PASS] GET /admin/users
[PASS] Resource table configure()
[PASS] GET /admin/ctf-management
[PASS] wire:submit -> saveCourse()
[PASS] wire:submit -> saveObjective()
[PASS] GET /admin/activity-logs
[PASS] Resource table configure()
[PASS] GET /admin/system-settings
[PASS] wire:submit -> saveOverview()
[PASS] wire:submit -> updateProfile()
[PASS] wire:submit -> changePassword()
```

**Interpretasi:** Pemeriksaan memverifikasi route GET terdaftar, class/method ada, deklarasi aksi dan binding UI tersedia, serta indikasi pemanggilan service, validasi dan otorisasi pada potongan source. Ini **bukan bukti sukses menjalankan tombol**, request Livewire, koneksi shell, query monitoring live, ekspor, atau perubahan pengaturan.

## 2. Bukti Student Panel

**Waktu menurut output terminal:** `2026-11-23 04:56:09`. Script awal memeriksa 6 modul, menghasilkan 82 PASS dan 1 FAIL dari asumsi URL Pentest.

```text
01 - Dashboard                : PASS
02 - My Labs                  : PASS
03 - Pentest                  : FAIL
04 - History                  : PASS
05 - Documentation            : PASS
06 - Profile                  : PASS

Modules PASS : 5/6
Checks PASS  : 82
Checks FAIL  : 1
OVERALL RESULT: NEEDS REVIEW
```

**Koreksi penyebab:** script mencari `/student/pentest`, sementara aplikasi menggunakan `/student/guided-ctf`. Operator menjalankan pemeriksaan ulang route:

```text
=== PENTEST ROUTE VERIFICATION ===
[PASS] Route: /student/guided-ctf
[INFO] Name: filament.student.pages.guided-ctf
[INFO] Action: App\Filament\Pages\GuidedCtf

RESULT: PASS
```

Hasil final: **6/6 modul lolos verifikasi implementasi**, dengan koreksi kesalahan asumsi pada script terdokumentasi. Tidak menyatakan skenario Pentest dinamis (submit flag/provisioning) sudah dieksekusi.

**Cuplikan pemeriksaan Student yang diberikan operator:**

```text
[PASS] Route /student
[PASS] Backend method: dashboardData()
[PASS] Dashboard menggunakan user scope
[PASS] Route /student/my-labs
[PASS] UI click -> PHP startLab()
[PASS] UI click -> PHP stopLab()
[PASS] UI click -> PHP resumeLab()
[PASS] UI click -> PHP confirmReset()
[PASS] UI click -> PHP confirmDelete()
[PASS] UI click -> PHP openWorkspace()
[PASS] Backend method: submitFlag()
[PASS] Backend method: checkProgress()
[PASS] Route /student/history
[PASS] UI reference: status
[PASS] UI reference: page
[PASS] Route /student/documentation
[PASS] UI reference: manualBookReady
[PASS] Route /student/profile
[PASS] UI submit -> PHP saveAvatar()
[PASS] UI submit -> PHP saveProfile()
[PASS] UI submit -> PHP changePassword()
```

## 3. Bukti Registrasi, Login Admin, Login Student, dan Database

**Waktu menurut output terminal:** `2026-11-23 05:06:05`.

```text
IMNEHNET - FINAL AUTHENTICATION VERIFICATION
Server  : iaas-mgmt
Time    : 2026-11-23 05:06:05
Mode    : READ ONLY

Registration Student         : PASS
Login Admin                  : PASS
Login Student                : PASS
Database Integration         : PASS

CHECKS PASS : 57
CHECKS FAIL : 0
OVERALL     : PASS
```

**Cuplikan pemeriksaan autentikasi (dari output operator):**

```text
[PASS] Registration route
[PASS] Registration URL /login/regist
[PASS] Registration wrapper Blade
[PASS] Form submits to register()
[PASS] Server-side validation
[PASS] Email uniqueness validation
[PASS] Rate limiting
[PASS] Role assigned as Student
[PASS] Password hashing
[PASS] Registration activity log
[PASS] Automatic login after registration
[PASS] Student dashboard redirect
[PASS] Admin login route
[PASS] Admin login URL /admin/login
[PASS] Panel access validation
[PASS] Student login route
[PASS] Student login URL /student/login
[PASS] Student panel redirect middleware
[PASS] Users table exists
[INFO] Total accounts: 10
[INFO] Role admin: 3 account(s)
[INFO] Role student: 7 account(s)
```

**Interpretasi:** Pengujian ini memeriksa keberadaan route, form, method dan string kebijakan dalam source, serta pembacaan tabel users. **Tidak ada akun baru yang dibuat, kredensial yang dikirim, login browser yang dijalankan, atau redirect aktual yang diuji.**

## 4. Batasan dan Reproduksibilitas

- Bukti bersumber dari **hasil terminal yang diserahkan operator**, bukan dari akses shell langsung oleh penulis.
- Teks hasil di atas adalah **kutipan ringkas dari transkrip**, bukan keseluruhan stdout. Untuk penilaian, lengkapi dengan screenshot terminal penuh yang telah disensor.
- Tidak ada secret, nama/email pengguna, hash password, token, ataupun cookie dicantumkan.
- `PASS — Verifikasi Implementasi` = pemeriksaan yang dijalankan sukses; `BELUM DIUJI END-TO-END` = aksi nyata tidak dijalankan. Kedua status ini tidak boleh dipertukarkan.
- Rujuk source final ter-audit pada [commit `b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf).
