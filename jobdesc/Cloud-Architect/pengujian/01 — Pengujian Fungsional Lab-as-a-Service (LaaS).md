# Pengujian 01 — Pengujian Fungsional Lab-as-a-Service (LaaS)

> **Status dokumen:** Draf berbasis implementasi portal. Kolom hasil belum boleh dinyatakan `PASS` sebelum bukti antarmuka dan kondisi aktual container dilampirkan.

## 1. Identitas Pengujian

- **Kelompok skenario:** P1 — Functional LaaS
- **PIC:** Abdilah — Cloud Architect
- **Objek uji:** Portal IMNEHNET, course **Web Server Breach**, pasangan container LAB-ATK (*attacker*) dan LAB-ENV (*victim*)
- **Halaman utama:** `My Labs` pada `/student/my-labs`
- **Tools:** browser, portal IMNEHNET, Incus CLI, dan Browser Developer Tools bila terjadi kegagalan request

## 2. Tujuan

Pengujian ini memeriksa fungsi utama Lab-as-a-Service (LaaS) IMNEHNET, mulai dari autentikasi hingga penghapusan lab. Pengujian tidak hanya menilai perubahan tampilan portal, tetapi juga mencocokkan status yang ditampilkan dengan kondisi aktual pasangan container pada Incus.

## 3. Ketentuan Bukti dan Penilaian

1. Screenshot portal digunakan untuk memverifikasi tindakan dan status yang dilihat pengguna.
2. Output Incus digunakan untuk memverifikasi kondisi aktual container pada `LAB-ATK` dan `LAB-ENV`.
3. Status antrean seperti `CREATING`, `PROVISIONING`, `CONFIGURING`, `RESETTING`, atau `DELETING` bukan hasil akhir operasi.
4. Refresh halaman saja tidak cukup untuk memverifikasi idempotensi. Tidak adanya duplikasi harus diperiksa melalui portal dan inventaris Incus.
5. Screenshot harus menyembunyikan password, token, cookie, private key, isi `.env`, dan data autentikasi lainnya.

## 4. Ringkasan Skenario

| Kode | Skenario | Status |
|---|---|---|
| P1-01 | Login dan hak akses pengguna | BELUM DIVERIFIKASI |
| P1-02 | Create Lab | BELUM DIVERIFIKASI |
| P1-03 | Start/Resume Lab | BELUM DIVERIFIKASI |
| P1-04 | Stop Lab | BELUM DIVERIFIKASI |
| P1-05 | Reset Lab | BELUM DIVERIFIKASI |
| P1-06 | Delete Lab | BELUM DIVERIFIKASI |
| P1-07 | Penanganan kegagalan, refresh, dan retry | BELUM DIVERIFIKASI |

---

## 5. Langkah dan Hasil Pengujian

### P1-01 — Login dan Hak Akses Pengguna

**Tujuan:** Memastikan pengguna aktif dapat masuk ke panel sesuai perannya, sedangkan akses yang tidak sesuai ditolak atau tidak ditampilkan.

**Prasyarat:**

- Tersedia akun pengguna aktif dan akun/kondisi uji yang tidak berhak mengakses panel tujuan.
- Portal dapat dijangkau melalui browser.

**Langkah pengujian:**

1. Buka halaman login portal IMNEHNET.

   ![P1-01 halaman login](./images/p1-01/p1-01-01-halaman-login.svg)

2. Masukkan akun pengguna aktif melalui mekanisme login portal, lalu kirim formulir.
3. Pastikan pengguna diarahkan ke panel pengguna dan dapat membuka halaman `My Labs`.

   ![P1-01 login berhasil dan My Labs dapat diakses](./images/p1-01/p1-01-02-login-berhasil.svg)

4. Keluar dari akun tersebut. Uji akun yang tidak aktif, kredensial tidak valid, atau akses langsung ke panel yang tidak sesuai peran.
5. Pastikan portal menolak login atau tidak menampilkan halaman dan aksi di luar hak akses pengguna.

   ![P1-01 akses tidak sesuai ditolak](./images/p1-01/p1-01-03-akses-ditolak.svg)

**Hasil yang diharapkan:** Pengguna aktif masuk ke panel yang sesuai. Akses tidak sah ditolak tanpa membocorkan halaman atau fungsi administratif.

**Hasil aktual:** `[PERLU DATA: hasil login, akun/peran uji, dan respons akses tidak sah]`

**Status:** **BELUM DIVERIFIKASI**

---

### P1-02 — Create Lab

**Tujuan:** Memastikan aksi `Start Lab` membuat satu sesi course **Web Server Breach** yang terdiri atas container victim pada LAB-ENV dan attacker pada LAB-ATK.

**Prasyarat:**

- Pengguna sudah login.
- Halaman `My Labs` menampilkan kuota `0 / 1` dan tombol `Start Lab`.
- Queue worker portal aktif.
- Remote Incus `lab-env` dan `lab-atk` dapat diperiksa oleh operator.

**Langkah pengujian:**

1. Buka `My Labs`, lalu pastikan course **Web Server Breach** tersedia dan kuota masih `0 / 1`.

   ![P1-02 course dan tombol Start Lab](./images/p1-02/p1-02-01-course-start-lab.svg)

2. Tekan `Start Lab`. Portal menerima permintaan, membuat record sesi dan environment, lalu memasukkan `ProvisionLabSession` ke queue.
3. Pastikan notifikasi `Provisioning dimulai` tampil dengan keterangan bahwa LAB-ATK dan LAB-ENV sedang disiapkan.
4. Amati halaman **Membuat Lab Environment** yang diperbarui otomatis setiap 5 detik. Tahap yang harus terlihat adalah:
   - creating lab session;
   - creating victim container (LAB-ENV);
   - creating attacker container (LAB-ATK);
   - applying security policy;
   - configuring environment;
   - finalizing setup.

   ![P1-02 progres provisioning](./images/p1-02/p1-02-02-progress-provisioning.svg)

5. Tunggu sampai status akhir menjadi `READY` dan portal menampilkan identitas kedua container. Jangan menilai `PASS` ketika status masih `CREATING`, `PROVISIONING`, atau `CONFIGURING`.
6. Cocokkan nama dan status container pada portal dengan Incus:

   ```bash
   incus list lab-env: --format json
   incus list lab-atk: --format json
   ```

   Catat hanya record yang namanya sama dengan kolom **LAB-ENV (Victim)** dan **LAB-ATK (Attacker)** pada portal.

   ![P1-02 status akhir portal dan Incus](./images/p1-02/p1-02-03-ready-dan-incus.svg)

**Hasil yang diharapkan:** Tepat satu sesi dibuat. Kedua container tersedia dan berstatus `Running`; policy pasangan diterapkan; portal berstatus `READY`; kuota berubah menjadi `1 / 1`.

**Hasil aktual:** `[PERLU DATA: Session ID, nama kedua container, status portal, dan status Incus]`

**Status:** **BELUM DIVERIFIKASI**

---

### P1-03 — Start/Resume Lab

**Tujuan:** Memastikan lab yang berstatus `STOPPED` dapat dijalankan kembali sebagai satu pasangan.

**Prasyarat:** Lab milik pengguna sudah berada pada status `STOPPED`.

**Langkah pengujian:**

1. Buka `My Labs`, lalu pastikan status lab adalah `STOPPED` dan tombol yang tampil adalah `Resume` atau `Resume Lab`.

   ![P1-03 lab stopped dan tombol Resume](./images/p1-03/p1-03-01-resume-action.svg)

2. Tekan `Resume`. Portal memasukkan perubahan daya pasangan ke queue; aksi ini tidak boleh dianggap selesai hanya karena tombol berhasil ditekan.
3. Tunggu sampai portal berstatus `READY` dan runtime kedua environment menunjukkan `Running`.
4. Buka tab `Containers`. Pastikan terminal LAB-ATK dan LAB-ENV dapat dihubungkan kembali.
5. Verifikasi status aktual kedua container melalui Incus dan cocokkan dengan nama yang ditampilkan portal.

   ![P1-03 pasangan kembali running](./images/p1-03/p1-03-02-running-terverifikasi.svg)

**Hasil yang diharapkan:** Kedua container berstatus `Running`, policy pasangan kembali diterapkan, portal kembali `READY`, dan terminal dapat dihubungkan.

**Hasil aktual:** `[PERLU DATA: status sebelum/sesudah, nama container, dan hasil verifikasi Incus]`

**Status:** **BELUM DIVERIFIKASI**

---

### P1-04 — Stop Lab

**Tujuan:** Memastikan lab dapat dihentikan tanpa menghapus progres objective.

**Prasyarat:** Lab berstatus `READY` atau `RUNNING`.

**Langkah pengujian:**

1. Pada detail lab, tekan `Stop`.
2. Pastikan dialog `Stop Lab?` menjelaskan bahwa akses jaringan dinonaktifkan sementara, kedua container dihentikan, dan progres objective dipertahankan.

   ![P1-04 dialog konfirmasi Stop](./images/p1-04/p1-04-01-confirm-stop.svg)

3. Tekan `Confirm Stop`, lalu tunggu queue menyelesaikan perubahan daya.
4. Pastikan portal berstatus `STOPPED`, runtime kedua environment menunjukkan `Stopped`, dan tombol berubah menjadi `Resume`.
5. Buka tab `Containers` atau gunakan sesi terminal lama. Pastikan terminal tidak lagi terhubung ke container yang sudah berhenti.
6. Verifikasi kondisi kedua container melalui Incus.

   ![P1-04 kedua container stopped](./images/p1-04/p1-04-02-stopped-terverifikasi.svg)

**Hasil yang diharapkan:** Kedua container terverifikasi `Stopped`, terminal ditutup/tidak tersedia, policy pasangan dicabut selama lab berhenti, dan progres objective tetap tersimpan.

**Hasil aktual:** `[PERLU DATA: status portal, status kedua container, kondisi terminal, dan nilai progress]`

**Status:** **BELUM DIVERIFIKASI**

---

### P1-05 — Reset Lab

**Tujuan:** Memastikan skenario victim dan progres Guided Capture The Flag (CTF) kembali ke baseline tanpa menghapus kedua container.

**Prasyarat:** Lab berstatus `READY`, `RUNNING`, atau `COMPLETED`. Sebelum reset, buat perubahan uji yang aman pada skenario dan selesaikan minimal satu objective agar pemulihan dapat dibuktikan.

**Langkah pengujian:**

1. Catat kondisi sebelum reset: progres objective, bukti penyelesaian, nama kedua container, dan perubahan uji pada skenario.

   ![P1-05 kondisi sebelum reset](./images/p1-05/p1-05-01-kondisi-sebelum-reset.svg)

2. Tekan `Reset`, lalu periksa dialog `Reset Lab?`. Dialog harus menyatakan bahwa progres kembali menjadi `0/6`, evidence dibersihkan, dan kedua container tidak dihapus.
3. Tekan `Confirm Reset`. Portal menutup sesi terminal aktif, mengubah status menjadi `RESETTING`, dan memasukkan `ResetLabSession` ke queue.
4. Tunggu sampai status kembali `READY`.
5. Pastikan objective pertama berstatus aktif, objective lain terkunci, progres `0/6`, evidence lama kosong, dan kondisi skenario victim kembali ke baseline.
6. Verifikasi bahwa nama/identitas kedua container masih sama dan keduanya tetap tersedia.

   ![P1-05 baseline pulih](./images/p1-05/p1-05-02-baseline-terpulihkan.svg)

**Hasil yang diharapkan:** Skenario kembali ke baseline, progres menjadi `0/6`, evidence dibersihkan, timer percobaan dimulai ulang, dan kedua container tidak dibuat ulang atau dihapus.

**Hasil aktual:** `[PERLU DATA: perubahan uji, progress sebelum/sesudah, hasil baseline, dan identitas container]`

**Status:** **BELUM DIVERIFIKASI**

---

### P1-06 — Delete Lab

**Tujuan:** Memastikan lab dan kedua environment terkait dihapus, sedangkan kuota baru dilepas setelah penghapusan infrastruktur terverifikasi.

**Prasyarat:** Tersedia satu lab milik pengguna dan nama kedua container sudah dicatat.

**Langkah pengujian:**

1. Tekan `Hapus Lab` atau `Delete Lab`.
2. Pastikan dialog `Delete Lab?` menyatakan bahwa container victim dan attacker akan dihapus permanen, riwayat objective tetap disimpan, dan kuota belum dilepas sebelum verifikasi selesai.

   ![P1-06 dialog konfirmasi Delete](./images/p1-06/p1-06-01-confirm-delete.svg)

3. Tekan `Confirm Delete`. Pastikan status menjadi `DELETING`; jangan melakukan Create baru selama proses ini.
4. Tunggu proses teardown selesai dan halaman kembali menampilkan kuota `0 / 1` serta tombol `Start Lab`.
5. Gunakan nama yang dicatat sebelum penghapusan untuk memastikan kedua container tidak lagi muncul pada remote Incus masing-masing.

   ![P1-06 resource dan kuota terhapus](./images/p1-06/p1-06-02-resource-terhapus.svg)

**Hasil yang diharapkan:** Container victim dan attacker tidak ditemukan, environment terkait dihapus, sesi berstatus internal `DELETED`, dan kuota pengguna kembali tersedia. Riwayat objective tetap dipertahankan sesuai desain.

**Hasil aktual:** `[PERLU DATA: nama resource sebelum delete, status akhir portal, hasil Incus, dan kuota]`

**Status:** **BELUM DIVERIFIKASI**

---

### P1-07 — Penanganan Kegagalan, Refresh, dan Retry

**Tujuan:** Memastikan kegagalan ditampilkan secara jujur, refresh tidak menggandakan sesi/resource, dan pemulihan tidak dijalankan secara buta.

**Catatan implementasi:** Provisioning memakai satu percobaan queue (`tries = 1`). Portal menampilkan status `FAILED` dan `last_error`, tetapi implementasi yang diperiksa tidak menyediakan tombol retry provisioning generik pada halaman progres. Karena itu, skenario ini tidak boleh menulis bahwa tombol `Retry` tersedia tanpa screenshot dari versi portal yang benar-benar diuji.

**Prasyarat:** Gunakan kegagalan terkendali pada lingkungan uji yang telah disetujui operator. Jangan mematikan jaringan, firewall, Tailscale, service produksi, atau queue worker tanpa persetujuan dan rollback yang jelas.

**Langkah pengujian:**

1. Picu kondisi gagal yang aman pada lingkungan pengujian atau gunakan bukti kegagalan aktual yang sudah terjadi.
2. Pastikan halaman **Membuat Lab Environment** menampilkan `Provisioning gagal`, alasan yang sudah disanitasi, dan tahap yang belum selesai.

   ![P1-07 kondisi gagal](./images/p1-07/p1-07-01-kondisi-gagal.svg)

3. Refresh browser beberapa kali ketika proses masih berjalan atau setelah berstatus `FAILED`.
4. Pastikan Session ID tidak berubah dan inventaris Incus tidak bertambah menjadi lebih dari satu attacker dan satu victim milik sesi tersebut.
5. Periksa Browser Developer Tools hanya bila perlu untuk memverifikasi request gagal/berulang; sensor cookie, token, header autentikasi, dan muatan data sensitif.
6. Lakukan pemulihan sesuai prosedur operator. Bila versi portal tidak memiliki aksi retry, jangan mengulang `Start Lab` sebelum resource parsial dipastikan sudah dibersihkan dan kuota kembali tersedia.
7. Setelah pemulihan, periksa kembali portal serta kedua remote Incus untuk memastikan hanya satu pasangan yang sah.

   ![P1-07 refresh dan tidak ada duplikasi](./images/p1-07/p1-07-02-tanpa-duplikasi.svg)

**Hasil yang diharapkan:** Kegagalan tampil sebagai `FAILED` dengan pesan tersanitasi; refresh tidak membuat sesi, job, atau container baru; resource parsial dibersihkan; pemulihan hanya dilakukan setelah pemeriksaan kondisi aktual.

**Hasil aktual:** `[PERLU DATA: metode kegagalan, Session ID, pesan portal, jumlah resource sebelum/sesudah refresh, dan prosedur pemulihan]`

**Status:** **BELUM DIVERIFIKASI**

---

## 6. Kesimpulan

Kesimpulan akhir baru dapat ditulis setelah screenshot asli menggantikan seluruh gambar placeholder dan hasil aktual setiap skenario diisi. Suatu skenario dinyatakan `PASS` hanya jika hasil antarmuka sesuai dengan verifikasi kondisi aktual resource. Jika salah satu pasangan container berada pada kondisi berbeda, operasi tetap dinilai gagal meskipun portal menampilkan notifikasi berhasil.

## 7. Pemetaan Bukti yang Harus Diganti

Seluruh berkas `.svg` pada direktori `images/p1-01` sampai `images/p1-07` adalah placeholder. Ganti satu per satu dengan screenshot asli menggunakan nama dasar yang sama; bila formatnya `.png`, ubah ekstensi tautan pada dokumen ini menjadi `.png`.
