# Portal Developer — Dokumentasi Implementasi Webportal IMNEHNET

**PIC:** Malik · **Terakhir diaudit:** 23 November 2026 · **Basis source final:** [commit `b3370b5` — `feat(portal): joh nggateli`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf).

## Daftar Bab

1. [01 — Persiapan dan instalasi Webportal](01-persiapan-instalasi-webportal-imnehnet.md) — PHP, Composer, Laravel, Filament, database, Node/Vite, Apache, queue, konfigurasi dan bukti instalasi.
2. [02 — Frontpage, login dan registrasi](02-frontpage-login-register.md) — route, login role Student/Admin, Blade, Livewire, validasi, redirect, CSS/JS.
3. [03 — Admin Panel (10 modul)](03-admin/README.md) — Dashboard, Terminal, Infrastructure, Monitoring, Security, Environments, Users, CTF Management, Activity Logs, System Settings.
4. [04 — Student Panel (6 modul)](04-student/README.md) — Dashboard, My Labs, Pentest, History, Documentation, Profile.

## Provenance dan Status

- [Source Webportal final](../../../webportal/) telah disinkronkan ke `main` pada commit `b3370b5`.
- Setiap modul memuat contoh PHP/Blade dan tautan langsung ke file asli di bawah cuplikan.
- **Implementasi source:** tersedia dan diaudit; **keberhasilan pengujian runtime:** hanya dinyatakan PASS bila ada langkah, hasil aktual dan bukti. Website yang dapat diakses adalah bukti integrasi awal, bukan bukti keseluruhan skenario.
- Screenshot disimpan pada [images/](../images/) dan harus disensor bila berisi data sensitif.

## Penanda Khusus

**System Health:** lima kemungkinan keluaran `Healthy / Degraded / Critical / Offline / Unknown` berdasarkan service checks, bukan KPI persentase. **Terminal:** Open shell memanggil Livewire/backend, berbeda dengan membuka tab fullscreen. **Users:** Presence dan Last Seen didokumentasikan berdasarkan source final.
