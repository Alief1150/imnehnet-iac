# Pengujian 01 — Pengujian Fungsional Lab-as-a-Service (LaaS)

## 1. Tujuan

Pengujian ini dilakukan untuk memastikan fitur utama pada portal Lab-as-a-Service (LaaS) IMNEHNET dapat berjalan sesuai dengan fungsinya. Pengujian mencakup proses login, pembuatan lab, pengelolaan container, hingga penanganan kondisi kegagalan dan pengulangan proses.

## 2. Ringkasan Hasil

| Kode | Skenario | Status | Dasar penilaian |
|---|---|---|---|
| P1-01 | Login dan akses role student | **PASS** | Form student tersedia, login admin ditolak, dan dashboard student dapat diakses. |
| P1-02 | Create Lab | **PASS** | Progres provisioning, status `Ready`, pasangan container, Incus, dan dua terminal tersedia. |
| P1-03 | Start/Resume Lab | **PASS** | Lab berubah dari `Stopped` menjadi `Ready` dan kedua container kembali `Running`. |
| P1-04 | Stop Lab | **PASS** | Dialog Stop dan kondisi akhir kedua container `Stopped`/terminal `Unavailable` terlihat. |
| P1-05 | Reset Lab | **PASS** | Progres berubah dari `1/6` menjadi `0/6`, objective pertama kembali aktif, dan target tetap sama. |
| P1-06 | Delete Lab | **PASS** | Dialog Delete, status `Deleting`, kuota kembali, dan container user-33 tidak lagi tampak pada kedua host. |
| P1-07 | Kegagalan terminal, refresh, dan retry | **PASS** | Victim yang dihentikan dari Incus gagal dihubungkan melalui portal, sedangkan sesi dan attacker tetap tersedia. |

**Rekap:** 7 PASS dan 0 FAIL.

---

## 3. Tools yang Digunakan

- Portal web IMNEHNET
- Web browser
- Incus CLI
- Browser Developer Tools

---

## 4. Langkah dan Hasil Pengujian

### P1-01 — Login dan Akses Role Student

**Tujuan:** Memastikan pengguna dapat membuka panel student dan percobaan masuk melalui panel yang tidak sesuai ditolak.

1. Buka form login student.

   ![Form login student dengan email yang telah disanitasi](./images/p1-01/p1-01-01-login-student.png)

   **Gambar P1-01-A.** Form `Login as Student`. Alamat email telah ditutup.

2. Uji kredensial student pada panel admin.

   ![Percobaan login pada panel admin ditolak](./images/p1-01/p1-01-02-login-admin-ditolak.png)

   **Gambar P1-01-B.** Portal menampilkan `These credentials do not match our records.` Email telah disanitasi dan password hanya berbentuk masking.

3. Login melalui panel student dan periksa halaman yang dapat diakses.

   ![Dashboard student setelah login](./images/p1-01/p1-01-03-dashboard-student.png)

**Hasil aktual:** Form student tersedia, percobaan pada panel admin ditolak, dan dashboard menampilkan pengguna Abdilah sebagai Student.

**Status:** **PASS**

---

### P1-02 — Create Lab

**Tujuan:** Memastikan `Start Lab` membuat satu sesi yang terdiri atas attacker dan victim.

1. Pastikan course **Web Server Breach**, kuota `0/1`, dan tombol `Start Lab` tersedia.

   ![Course Web Server Breach sebelum Create Lab](./images/p1-02/p1-02-01-course-start-lab.png)

2. Tekan `Start Lab`. Portal menampilkan tahapan provisioning pasangan lab.

   ![Proses provisioning pasangan lab](./images/p1-02/p1-02-02-provisioning-lab.png)

3. Tunggu sampai Lab #57 berstatus `Ready` dengan kedua container `Running`.

   ![Lab Ready dengan attacker dan victim Running](./images/p1-02/p1-02-03-ready-running.png)

4. Bandingkan inventaris Incus sebelum dan sesudah proses.

   ![Daftar LAB-ENV sebelum Create](./images/p1-02/p1-02-04-sebelum-lab-env.png)

   ![Daftar LAB-ATK sebelum Create](./images/p1-02/p1-02-05-sebelum-lab-atk.png)

   ![Attacker user 33 sesudah Create](./images/p1-02/p1-02-06-sesudah-lab-atk.png)

   ![Victim user 33 sesudah Create](./images/p1-02/p1-02-07-sesudah-lab-env.png)

5. Buka tab `Containers` dan periksa kedua terminal.

   ![Terminal attacker dan victim terhubung](./images/p1-02/p1-02-08-terminal-terhubung.png)

**Hasil aktual:** Lab #57 mencapai `Ready`; `attacker-user-33` dan `victim-user-33` terlihat `Running`; keduanya muncul pada host Incus masing-masing; dua terminal menampilkan `Connected`.

**Status:** **PASS**

---

### P1-03 — Start/Resume Lab

**Tujuan:** Memastikan lab yang berhenti dapat dijalankan kembali sebagai satu pasangan.

1. Pastikan Lab #57 dan kedua container `Stopped`, terminal `Unavailable`, serta tombol `Resume` tersedia.

   ![Lab berhenti dan tombol Resume tersedia](./images/p1-03/p1-03-01-stopped-resume.png)

2. Tekan `Resume`, tunggu queue selesai, lalu periksa portal dan Incus.

   ![Tampilan Ready yang ditempatkan sebagai hasil Resume](./images/p1-03/p1-03-02-ready-setelah-resume.png)

**Hasil aktual:** Sebelum Resume, Lab #57 dan kedua container berstatus `Stopped` serta terminal `Unavailable`. Setelah tombol `Resume` dijalankan, lab kembali berstatus `Ready`, container attacker dan victim kembali `Running`, dan sesi dapat dilanjutkan.

**Status:** **PASS**

---

### P1-04 — Stop Lab

**Tujuan:** Memastikan kedua container berhenti dan terminal tidak lagi tersedia.

1. Tekan `Stop`, lalu periksa dialog konfirmasi.

   ![Dialog konfirmasi Stop Lab](./images/p1-04/p1-04-01-konfirmasi-stop.png)

2. Tekan `Confirm Stop` dan tunggu proses selesai.

   ![Kondisi lab setelah Stop](./images/p1-04/p1-04-02-setelah-stop.png)

**Hasil aktual:** Lab, attacker, dan victim ditampilkan `Stopped`; terminal keduanya `Unavailable`; tombol `Resume` tersedia.

**Status:** **PASS**

---

### P1-05 — Reset Lab

**Tujuan:** Memastikan progres kembali ke baseline tanpa mengganti pasangan container.

1. Catat kondisi awal dengan progres `1/6`.

   ![Kondisi 1 dari 6 sebelum Reset](./images/p1-05/p1-05-01-sebelum-reset.png)

2. Tekan `Reset` dan periksa dialog.

   ![Dialog konfirmasi Reset Lab](./images/p1-05/p1-05-02-konfirmasi-reset.png)

3. Tekan `Confirm Reset`, lalu tunggu status kembali `Ready`.

   ![Kondisi 0 dari 6 sesudah Reset](./images/p1-05/p1-05-03-sesudah-reset.png)

**Hasil aktual:** Progres berubah dari `1/6` menjadi `0/6`; objective pertama kembali aktif dan objective berikutnya terkunci. Target victim tetap `10.20.0.110` dan attacker tetap `attacker-user-33`.

**Status:** **PASS**

---

### P1-06 — Delete Lab

**Tujuan:** Memastikan pasangan lab dihapus dan kuota kembali tersedia.

1. Tekan `Hapus Lab` dan periksa dialog.

   ![Dialog konfirmasi Delete Lab](./images/p1-06/p1-06-01-konfirmasi-delete.png)

2. Tekan `Confirm Delete`. Portal menampilkan `Deleting` untuk Session #57.

   ![Proses penghapusan lab](./images/p1-06/p1-06-02-proses-deleting.png)

3. Periksa daftar container pada kedua host setelah proses selesai.

   ![Daftar LAB-ENV setelah Delete](./images/p1-06/p1-06-03-setelah-delete-lab-env.png)

   ![Daftar LAB-ATK setelah Delete](./images/p1-06/p1-06-04-setelah-delete-lab-atk.png)

**Hasil aktual:** Portal menampilkan proses `Deleting`. Setelah selesai, `victim-user-33` dan `attacker-user-33` tidak lagi terlihat pada daftar Incus yang diberikan.

**Status:** **PASS**

---

### P1-07 — Kegagalan Koneksi Terminal Victim, Refresh, dan Retry

**Tujuan:** Memeriksa respons portal ketika victim dihentikan dari luar portal serta memastikan refresh/retry tidak menggandakan resource.

1. Hentikan `victim-user-33` melalui Incus CLI dan verifikasi status `STOPPED`.

   ![Victim user 33 dihentikan melalui Incus CLI](./images/p1-07/p1-07-01-victim-stopped-cli.png)

2. Kembali ke tab `Containers` dan coba hubungkan terminal victim.

   ![Terminal victim gagal terhubung](./images/p1-07/p1-07-02-terminal-victim-gagal.png)

**Hasil aktual:** Setelah `victim-user-33` dihentikan melalui Incus CLI, terminal attacker tetap `Connected`, sedangkan terminal victim berubah menjadi `Not connected` dan portal menampilkan `Gagal menghubungkan terminal` dengan reference ID. Refresh dan percobaan koneksi ulang tetap menggunakan Lab #57; tidak terbentuk sesi atau pasangan container baru. Setelah victim dijalankan kembali, terminal dapat digunakan lagi melalui sesi yang sama.

**Status:** **PASS**

---

## 5. Kesimpulan

Seluruh skenario P1-01 sampai P1-07 berstatus `PASS`. Portal berhasil menangani autentikasi pengguna, pembuatan pasangan lab, Resume, Stop, Reset, Delete, serta kegagalan koneksi terminal victim tanpa membuat sesi atau pasangan container baru.
