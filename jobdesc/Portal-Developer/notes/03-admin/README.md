# 03 — Dokumentasi Admin Panel IMNEHNET

**Penanggung jawab:** Septihadi Malik — Portal Developer  
**Terakhir diperiksa:** 23 November 2026  
**Status:** 10 dari 10 dokumen implementasi telah ditulis berdasarkan audit source GitHub; screenshot dan uji fungsi perlu dilengkapi.

## Daftar dokumentasi

| No | Modul | Dokumentasi source | Screenshot & pengujian |
|---|---|---|---|
| 1 | [Dashboard](01-dashboard.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |
| 2 | [Terminal](02-terminal.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |
| 3 | [Infrastructure](03-infrastructure.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |
| 4 | [Monitoring](04-monitoring.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |
| 5 | [Security](05-security.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |
| 6 | [Environments](06-environments.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |
| 7 | [Users](07-users.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |
| 8 | [CTF Management](08-ctf-management.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |
| 9 | [Activity Logs](09-activity-logs.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |
| 10 | [System Settings](10-system-settings.md) | Sudah ditulis; cuplikan kode dan link file asli | Perlu dilengkapi |

## Aturan dokumentasi

1. Semua cuplikan bersumber dari kode Webportal di repository `main`; komentar ditambahkan untuk penjelasan.
2. Setiap blok kode ditautkan ke file aslinya, sebagian sampai nomor baris.
3. **Blade** digunakan untuk markup/komponen visual dan event Livewire; **PHP Page/Widget/Resource** digunakan untuk query, validasi, state, aksi dan guard.
4. Hasil audit kode tidak otomatis membuktikan pengujian end-to-end berhasil.
5. Implementasi server `iaas-mgmt` dapat memiliki perubahan lokal yang belum tercermin pada GitHub `main`. Perubahan tersebut harus disinkronkan dan diuji sebelum status akhir ditetapkan.
6. Screenshot ditempatkan di `../../images/03-admin/<nomor-modul>/` sesuai nama yang tercantum dalam setiap dokumen.

## Tahap selanjutnya

- Lengkapi screenshot per halaman tanpa token, password, atau data pribadi.
- Laksanakan pengujian skenario sesuai tabel pada masing-masing file.
- Verifikasi sinkronisasi patch **System Health empat status** dan **Presence + Last Seen** antara server dan repository.
- Setelah Admin selesai dari sisi bukti, lanjutkan dokumentasi `../04-student/`.
