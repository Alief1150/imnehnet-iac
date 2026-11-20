# IMNEHNET — Golden Image & Lifecycle Container
## Handoff teknis untuk Malik (Developer Portal Admin/Dosen)

**Tanggal:** 20 November 2026 (WIB)  
**Disusun untuk:** Malik — Developer Portal IMNEHNET  
**Kontributor konteks:** Alief (Attacker/DevOps), tim IMNEHNET (Victim, Security, Portal)  
**Lingkup:** Golden Image yang sudah digunakan, infrastruktur Incus/OpenTofu, integrasi Laravel, dan spesifikasi perencanaan kontrol **Start, Stop, Reset, Delete** dari portal admin/dosen.  
**Jenis dokumen:** Handoff implementasi dan rancangan integrasi; **bukan** bukti semua aksi admin/dosen sudah berhasil diuji.

> **Aturan membaca status**: **[VERIFIED]** = dibuktikan melalui output/screenshot atau informasi teknis langsung yang tersedia; **[REPORTED]** = disampaikan tim/konteks histori tetapi belum diaudit ulang pada server hari ini; **[PROPOSED]** = desain/rekomendasi untuk Malik, belum dinyatakan terimplementasi; **[CHECK]** = butuh inspeksi source/live sebelum coding. **Tidak ada perintah destruktif dalam dokumen ini untuk langsung dijalankan.**

---

## 1. Executive summary untuk Malik

1. **[VERIFIED]** Lab Guided CTF IMNEHNET menggunakan **dua container per satu LabSession**: **LAB-ATK / Attacker** dan **LAB-ENV / Victim**. Dua host berbeda (`lab-atk:` dan `lab-env:`) dikelola melalui remote Incus.
2. **[VERIFIED]** Contoh pasangan aktif mahasiswa `user-29` pada 20 November: `attacker-user-29` (Kali GNU/Linux Rolling 2026.3, IP `10.30.0.103`) dan `victim-user-29` (Ubuntu 24.04.5 LTS, IP `10.20.0.103`). UI My Labs memperlihatkan keduanya **Running**. Ini contoh **tenant tertentu**, bukan IP/identitas untuk hardcode.
3. **[REPORTED]** Alias Golden Image produksi yang digunakan: `imnehnet-gi-attacker-v1` pada LAB-ATK dan `imnehnet-gi-victim-v1` pada LAB-ENV. Fingerprint terdokumentasi dalam §3; **cek ulang sebelum cutover**.
4. **[VERIFIED]** UI Student saat ini menampilkan OS, nama container, IP, status, dan Security Tools Attacker secara informasional. Nilai nama/IP adalah per session. Daftar tools di UI adalah metadata **statis**, bukan bukti semua binary tersedia.
5. **[REPORTED]** Alur provisioning Guided CTF berjalan melalui **Laravel jobs → OpenTofu workspace → Incus remote**. Jangan membuat alur start/reset/stop/delete admin yang langsung menjalankan command shell sembarang, melewati service/lock existing.
6. **[PROPOSED]** Portal admin/dosen sebaiknya mengelola **pasangan lab secara atomik/terkoordinasi**, bukan hanya salah satu container secara independen tanpa alasan dan audit. Hak admin/dosen, antrian job, lock, idempotensi, reconciler, dan konfirmasi operasi harus didefinisikan.

**Keputusan produk utama yang diperlukan:** apakah tombol **Reset** berarti *restart saja* atau *rebuild dari Golden Image*. **Rekomendasi:** pisahkan `Restart` (power cycle container) dan `Reset Environment` (reprovision ke baseline), karena dampaknya terhadap pekerjaan mahasiswa berbeda jauh.

---

## 2. Arsitektur sistem & ownership

```text
                 https://imnehnet.web.id
                       Laravel / Filament
                        IAAS-MGMT
             (authz, DB, jobs, orchestration)
                             |
                    OpenTofu / Incus
                     per-lab workspaces
                     /             \
             lab-atk:                 lab-env:
          Incus remote               Incus remote
             |                            |
  imnehnet-gi-attacker-v1       imnehnet-gi-victim-v1
             |                            |
       attacker-user-N             victim-user-N
       10.30.0.x                  10.20.0.x
              \___ allowed paired traffic __/
                 cross-tenant: DENY
```

| Komponen | Fungsi | Jalur / alamat referensi | Status |
|---|---|---|---|
| IAAS-MGMT | Portal Laravel/Filament, queue worker, OpenTofu orchestrator | `/home/iaas/projects/webportal-iaas` | [REPORTED] |
| IaC | Workspace OpenTofu per instance | `/home/iaas/imnehnet-iac/tofu` | [REPORTED] |
| LAB-ATK | Remote Incus Attacker | `lab-atk:`; host `192.168.100.30` | [REPORTED] |
| LAB-ENV | Remote Incus Victim | `lab-env:`; host `192.168.100.20` | [REPORTED] |
| Student Portal | UI My Labs / terminal / Guided CTF | `/student/my-labs` dan halaman Pentest | [VERIFIED] |
| Management network | Jaringan administrasi | `192.168.100.0/24` (contoh host di atas) | [REPORTED] |
| Tenant Attacker | Segment lab penyerang | `10.30.0.x` | [VERIFIED untuk user-29] |
| Tenant Victim | Segment lab target | `10.20.0.x` | [VERIFIED untuk user-29] |

**Batas tanggung jawab:** OpenTofu mengelola provisioning dan deklarasi resource Incus; Ansible dipakai untuk konfigurasi guest/build ketika diperlukan. Golden Image menyediakan baseline sistem. Laravel memegang identitas sesi, workflow, authorization, status dan audit. **Tidak** melakukan `incus delete`, `tofu destroy`, `queue:retry all`, atau perubahan ACL secara manual dari endpoint HTTP.

---

## 3. Golden Image final: informasi yang diketahui

### 3.1 Attacker — LAB-ATK

| Properti | Nilai | Status |
|---|---|---|
| Peran | **Attacker** | [VERIFIED] |
| Alias image | `imnehnet-gi-attacker-v1` | [REPORTED] |
| Fingerprint | `9158e8f91317125ce25a4dab288dbc33835211444db18dfb2fd5c4174c68e5af` | [REPORTED; cek ulang] |
| OS container | **Kali GNU/Linux Rolling 2026.3** | [VERIFIED, `/etc/os-release` dari `attacker-user-29`] |
| Remote Incus | `lab-atk:` | [VERIFIED melalui command user] |
| Nama instance | `attacker-user-{user_id}` pada contoh | [VERIFIED pada user-29; jangan mengasumsikan format universal] |
| IPv4 contoh | `10.30.0.103` | [VERIFIED UI user-29] |
| CPU contoh | `2 vCPU` | [VERIFIED UI user-29, bukan template global] |
| RAM contoh | `1024 MiB` | [VERIFIED UI user-29, bukan template global] |
| Runtime | Incus / LXC | [VERIFIED pada UI dan arsitektur] |
| UI tools | Nmap, Gobuster, Dirb, Curl, Ping, Python 3 | [VERIFIED sebagai label UI; **versi/availability binary perlu cek**] |

### 3.2 Victim — LAB-ENV

| Properti | Nilai | Status |
|---|---|---|
| Peran CTF | **Victim** (bukan label *Defender* pada UI final) | [VERIFIED] |
| Alias image | `imnehnet-gi-victim-v1` | [REPORTED] |
| Fingerprint | `8b12db08acbe8bd572023689407f510cf2f9be53ccf1e78c2c915ec5f568a99a` | [REPORTED; cek ulang] |
| OS container | **Ubuntu 24.04.5 LTS (Noble Numbat)** | [VERIFIED, `/etc/os-release` dari `victim-user-29`] |
| Remote Incus | `lab-env:` | [VERIFIED melalui command user] |
| Nama instance | `victim-user-{user_id}` pada contoh | [VERIFIED pada user-29; jangan mengasumsikan format universal] |
| IPv4 contoh | `10.20.0.103` | [VERIFIED UI user-29] |
| CPU contoh | `2 vCPU` | [VERIFIED UI user-29, bukan template global] |
| RAM contoh | `2048 MiB` | [VERIFIED UI user-29, bukan template global] |
| Runtime | Incus / LXC | [VERIFIED pada UI dan arsitektur] |
| Lab role | Target/sandbox Guided CTF dengan enam objective | [VERIFIED pada UI] |

**Penting mengenai OS:** `lab-atk` dan `lab-env` adalah *host VM/remote*; nilai OS di atas diperiksa **di dalam container mahasiswa**. Rootfs container Attacker adalah Kali; rootfs container Victim adalah Ubuntu. Nama role *Victim* bukan berarti harus menonaktifkan networking atau terminal pada container secara otomatis.

### 3.3 Fingerprint, alias dan expiry

- **[REPORTED]** Metadata image Attacker pernah menunjukkan expiry `2026-11-02`; ini **sudah lewat pada tanggal dokumen**, tetapi alias sebelumnya dilaporkan masih resolve. **Expiry metadata tidak cukup untuk menyimpulkan image hilang atau tidak dapat digunakan**; uji `incus image info` pada host yang benar.
- **[REPORTED]** Metadata image Victim pernah menunjukkan expiry `2026-12-17`; belum tentu masih sama setelah update.
- `alias` = nama yang mungkin diubah/dipindahkan; `fingerprint` = identitas image immutable tertentu. Persist **fingerprint yang benar-benar dipakai provisioning** dan versi image/recipe dalam audit log sesi baru.
- Jangan *replace* alias image lama sembarangan jika masih digunakan oleh sesi yang belum selesai; lakukan versioning/canary.

### 3.4 Pengaruh Golden Image terhadap lifecycle

**Golden Image adalah template dasar, bukan container bersama.** Setiap create/reprovision menghasilkan instance tenant sendiri. Reset yang mengembalikan baseline harus memakai **image dan konfigurasi versi yang terikat pada session** atau keputusan migration yang eksplisit. Hapus container tenant tidak berarti menghapus Golden Image. Sebaliknya, jangan hapus image yang mungkin masih diperlukan untuk reset sesi lama tanpa pengecekan referensi/dependensi.

**Golden Image bukan sumber IP unik.** IP, nama container, pemetaan user/session, workspace, rule firewall, dan konten dinamis/flag perlu diurus pada provisioning, bukan dibekukan dalam image.

---

## 4. Hal yang sudah terlihat di Student Portal

Dari halaman My Labs contoh user-29:

- Lab #55 memperlihatkan status **Ready** pada 18–20 November, course **IMNEHNET Web Server Breach**, pasangan `attacker-user-29` dan `victim-user-29` berstatus Running.
- Detail `Container Information` telah diperbarui bergaya Fastfetch: label **LAB-ATK — Attacker** dan **LAB-ENV — Victim**, icon monitor/server SVG, OS container, nama instance dan IPv4; informasi `Terminal: Available/Unavailable`, CPU, memory, runtime, role teks duplikat dikeluarkan dari card sesuai revisi UI terakhir.
- Daftar Security Tools pada Attacker masih ditampilkan secara statis; bukan inventaris tools real-time.
- Tab **Containers / Terminal** berisi dua terminal; workspace **Pentest** menampilkan attacker embedded terminal, target victim IP, enam objective dan validasi flag/progres.
- Halaman **Progress** merekap selesai/aktif/terkunci. Progres Guided CTF disimpan oleh aplikasi dan **harus dibedakan dari lifecycle container**.
- Tombol student existing mencakup **Reset**, **Stop**, **Hapus Lab**. **Keberadaan tombol tidak membuktikan semua edge case job telah lulus pengujian**.

**File Blade yang diketahui:**

```text
resources/views/filament/pages/partials/my-labs-active.blade.php
resources/views/filament/pages/partials/imnehnet-progress.blade.php
resources/views/filament/pages/partials/imnehnet-pentest-workspace.blade.php
```

Perubahan CSS/tampilan **bukan** bukti bahwa backend lifecycle atau kebijakan jaringan berubah.

---

## 5. Jalur backend yang harus direuse Malik

**[REPORTED dari inspeksi source sebelumnya; cek ulang source produksi sebelum implementasi]**

```text
Student/Admin action
  -> Laravel Policy/authorization
  -> LabSessionManager / orchestration service
  -> queued job (ProvisionLabSession, ResetLabSession,
                 StopLabSession / DeleteLabSession sesuai code terbaru)
  -> lock + recheck database state
  -> OpenTofu workspace / Incus remote
  -> health check and status reconciliation
  -> audit event + UI notification
```

Referensi yang pernah ditemukan:

```text
app/Jobs/ProvisionLabSession.php
app/Jobs/ResetLabSession.php
app/Jobs/DeleteLabSession.php
app/Services/GuidedCtf/LabSessionManager.php
app/Services/.../OpenTofuService.php  # lokasi tepat perlu ditemukan
```

**Catatan penting:** Stop pernah dilaporkan menggunakan service power action (`TerminalService::queueOwnedLabPower()`) pada jalur student tertentu; **jangan menganggap selalu ada `StopLabSession` production job yang sama**. Periksa route/component/handler aktual. Kelola state via service bersama, bukan menyalin implementasi tombol student ke panel admin tanpa policy.

### 5.1 Konteks OpenTofu workspace

- **[REPORTED]** Setiap instance memakai workspace terpisah, misalnya `ctf-attacker-108` dan `ctf-victim-108` pada contoh historis user-30. **Ini contoh**, bukan workspace untuk dioperasikan.
- Workspace, instance, remote, dan `LabEnvironment` harus dipetakan melalui database/state sumber kebenaran sebelum setiap operasi.
- **Tidak boleh** menggunakan nama workspace yang dirakit hanya dari parameter HTTP user tanpa validasi ownership.
- **[REPORTED]** Controller menggunakan lock OpenTofu (`flock`) dan lock session provisioning. Audit implementasi lock existing; jangan buat dua mekanisme balapan yang independen.
- Pemanggilan OpenTofu pada live session wajib memakai `-chdir`/working directory, workspace dan var yang terverifikasi serta output terstruktur, bukan parsing ad-hoc stderr tanpa status.

### 5.2 Contoh sesi yang harus diperlakukan hati-hati

Terdapat contoh historis **session #52** milik user-30, status **FAILED** pada 18 November, dengan resource `victim-user-30`/`attacker-user-30` yang saat pemeriksaan lama tidak ada di Incus dan workspace state kosong. **Jangan menganggap kondisi ini masih sama**; status dan resource bisa berubah. Contoh ini menunjukkan perlunya **reconciliation** antara database, queue, OpenTofu state dan Incus sebelum retry/recovery. Menjalankan `queue:retry all` pernah berisiko memutar ulang job penghapusan lama — **dilarang sebagai prosedur recovery umum**.

---

## 6. Kontrak lifecycle untuk Portal Admin/Dosen (PROPOSED)

### 6.1 Domain model dan unit operasi

**Default operasi adalah satu `LabSession` / satu pasangan Attacker + Victim**, bukan satu container yang dipilih secara bebas. Alasan: satu course, satu kepemilikan user, satu policy paired network dan satu progres. Tetap sediakan diagnostik per container untuk admin, tetapi tidak untuk melakukan perubahan destruktif tanpa alasan.

Model data minimum yang perlu tersedia/ditinjau:

| Field/domain | Makna |
|---|---|
| `lab_sessions.id`, `user_id`, `course_id` | Identitas dan pemilik lab |
| `lab_sessions.status` | State sesi canonical (enum existing) |
| `attacker_environment_id`, `victim_environment_id` atau relasi actual | Pair yang tepat; cek schema |
| `attacker_ip`, `victim_ip` | IP per session, boleh null selama provisioning |
| `attacker_container`, `victim_container` | Nama instance per session |
| `lab_environments.runtime_status` | Status instance/observasi runtime |
| image fingerprint/version | Image yang harus digunakan pada rebuild (usulan metadata jika belum tersimpan) |
| OpenTofu workspace/state pointer | Identitas resource IaC per instance |
| `lifecycle_operation_id` / audit event | Correlation ID untuk job, actor, dan outcome |

**Jangan membuat status enum baru sebelum memeriksa enum/migrations yang ada.** Status UI harus membedakan **requested, processing, actual** agar tidak langsung mengumumkan Running/Deleted setelah tombol diklik.

### 6.2 Matriks aksi admin/dosen

| Aksi | Prasyarat | Perilaku | Outcome sukses | Failure/recovery |
|---|---|---|---|---|
| **Start / Resume** | Lab exists; tidak sedang provision/reset/delete; actor authorized | Power on kedua instance existing; tunggu boot, health/terminal readiness sesuai kontrak | Kedua container Running, layanan penting Ready; network policy tetap berlaku | Partial start → retry targeted atau rollback sesuai policy |
| **Stop** | Lab exists dan tidak sedang transisi konflik | Hentikan kedua instance dengan graceful timeout terkontrol; tidak hapus data | Dua container Stopped, state tercatat | Sebelah masih running → tampilkan Partial/Failed, reconcile |
| **Reset Environment** | Authorization tingkat tinggi; konfirmasi kehilangan data; tidak ada job lain | Rebuild pasangan dari Golden Image/version yang disetujui **atau** restore snapshot baseline yang sudah diuji; reapply network, flags, health | Fresh environment kedua instance; state/progress sesuai keputusan produk | Rebuild gagal → FAILED + preserve evidence + cleanup terbatas |
| **Delete Lab** | Actor authorized; konfirmasi destruktif; session cocok; tidak ada job lain | Revoke terminal, cleanup policy/IP allocations, destroy resource via owner IaC, bersihkan state referensi sesuai urutan yang aman | Kedua resource benar-benar absent, DB terminal state final, audit utuh | Partial cleanup → retry hanya scope session, jangan orphan atau hapus tenant lain |

**Istilah:** `Start` menyalakan *existing instances*. **Create/Provision** membuat instance baru; jangan menyamakan keduanya. **Reset** tidak sama dengan Stop lalu Start.

### 6.3 Keputusan Reset yang harus Malik sepakati dengan tim

Ada tiga arti teknis yang berbeda:

1. `Restart` — power cycle existing container; data tetap ada.
2. `Reset environment` — hapus/recreate atau restore pristine snapshot; **filesystem tenant kembali ke baseline**. Data dalam container bisa hilang.
3. `Reset course progress` — mengembalikan progres enam objective ke awal; **DB berubah**, bisa terpisah dari reset filesystem.

**[PROPOSED]** UI admin/dosen menyediakan pilihan terpisah atau dialog eksplisit: `Restart Containers`, `Rebuild Lab Environment`, dan `Reset CTF Progress`. Kalau produk harus hanya memiliki tombol `Reset`, konfirmasi harus menjelaskan tepat apa yang direset dan harus ada spesifikasi atomisitas DB + infra.

**Berbahaya:** jika lab rebuilt tetapi progres masih objective 4, keadaan target dan progress bisa tidak sinkron; jika progres direset tanpa memperbarui flag/target, validator mungkin tidak konsisten. Definisikan reset policy sebelum implementasi.

### 6.4 State machine acuan (sesuaikan dengan enum existing)

```text
NO_LAB -- provision --> PROVISIONING --> READY/RUNNING
                                    \----> FAILED
READY/RUNNING -- stop  --> STOPPING --> STOPPED
STOPPED       -- start --> STARTING --> READY/RUNNING
READY/RUNNING/STOPPED -- reset --> RESETTING --> READY/RUNNING
(any existing allowed) -- delete --> DELETING --> DELETED

Failure on any transition -> FAILED/PARTIAL state + reconciliation
```

Nama state ini adalah **model desain**, belum tentu identik dengan enum Laravel production. Jangan langsung membuat enum/migration berdasarkan diagram.

---

## 7. Implementasi aman dari sisi Laravel

### 7.1 Authorization & audit

- Terapkan **policy server-side** berdasarkan role, course/kelas, tenant ownership dan izin delegasi dosen. Tombol tersembunyi di UI bukan kontrol akses.
- Bedakan `view`, `start/stop`, `reset`, `delete`, dan kemungkinan `force cleanup`. Delete/reset lebih sensitif dan sebaiknya punya audit + confirmation.
- Semua aksi mencatat actor, role, session ID, dua environment ID, action, reason, request/correlation ID, timestamp WIB/UTC, state sebelum/sesudah dan hasil. Jangan log secrets, terminal token, `.env`, output state mentah.
- Validasi `LabSession` + relasi Attacker/Victim dan owner resource dari database; jangan menerima `instance_name`/workspace/user ID arbitrer dari browser.

### 7.2 Queue & concurrency

- HTTP handler: authorize → validate precondition → buat operation record atomik → enqueue → segera respons **Accepted/Processing**, bukan `Done`.
- Worker: acquire lock per `session_id` ditambah lock global/per remote yang dibutuhkan OpenTofu; re-read state sesudah lock; idempotent handler; timeout dan bounded retry.
- Deduplicate klik ganda menggunakan idempotency key/per-active-operation constraint; satu lifecycle transition per session pada satu waktu.
- Jangan menjalankan `queue:retry all` di produksi, terutama jika ada historical `DeleteLabSession` jobs. Retry harus memakai operation ID dan guard yang memeriksa target masih cocok.
- Hindari mengunci semua sesi terlalu lama bila operasi non-konflik bisa diproses paralel; tetap hormati kemampuan storage, OpenTofu, remote, dan queue existing.

### 7.3 Reconciliation

Cek **empat sumber** sebelum menyatakan selesai:

1. Relasi `LabSession`/`LabEnvironment` dan state DB.
2. OpenTofu workspace/state yang benar (jangan cetak state yang mengandung secrets).
3. `incus info`/status pada **remote yang benar** untuk instance tepat.
4. Health readiness: shell/terminal Attacker, layanan challenge Victim, paired route dan network isolation yang diperlukan.

Bedakan `container Running` dari `Lab Ready`: Running tidak otomatis berarti service Victim sudah siap atau terminal/websocket tersambung.

### 7.4 Stop/start vs OpenTofu ownership

- **[CHECK]** Lihat apakah power action existing sudah melalui service Incus yang kompatibel dengan OpenTofu; jangan membuat drift dengan mengubah image, network, profile atau resource spec di luar IaC.
- Jika OpenTofu menganggap desired state `running` dan operator men-stop instance melalui Incus, `tofu apply` berikutnya bisa menyalakannya lagi. Tentukan **single authoritative path** untuk power state atau reconcile desired state secara eksplisit.
- Sebelum delete, hentikan atau invalidasi terminal connection/token dan batasi job provisioning yang masih berlangsung untuk session yang sama.

### 7.5 Delete aman

Urutan konseptual (bukan perintah langsung):

```text
Authorize + confirmation
  -> lock session; validate ownership and resource map
  -> invalidate/revoke terminal access
  -> prevent new lifecycle jobs for this session
  -> reconcile current Incus/Tofu resources
  -> controlled teardown for victim and attacker
  -> remove per-session network/ACL/firewall mappings safely
  -> verify both instances absent and state cleaned
  -> DB final state + audit + UI notification
```

Jangan `incus delete` container dengan nama wildcard; jangan menghapus image alias bersama, bridge/shared ACL/profile, workspace sesi lain atau riwayat bukti/log yang diperlukan audit. Bila salah satu teardown gagal, simpan marker partial + operasi retry yang terarah; jangan menyembunyikan error.

---

## 8. Integrasi Security/Micro-segmentation

**[REPORTED]** Pengujian P4 Micro-segmentation & Sandbox Isolation sudah dinyatakan **selesai** oleh tim pada 20 November; dokumen ini tidak memuat bukti lengkap PASS setiap test case. Untuk admin/dosen lifecycle:

- Attacker A → Victim A **ALLOW** hanya pada jalur/port sesuai policy.
- Attacker A → Victim B **DENY**.
- Attacker A → Attacker B **DENY**.
- Victim A → Victim B **DENY**, kecuali pengecualian tertulis.
- Student → IAAS management/admin plane **DENY**.
- Portal/worker → resource melalui jalur resmi **ALLOW**.

**Risiko operasi lifecycle:** reset/rebuild dapat mengubah IP, MAC, link network atau ID instance. Bila identity berubah, policy paired network perlu direkonsiliasi ulang secara atomik. **IP bukan authorization boundary tunggal**; jangan mengandalkan string `10.30.0.N` sebagai bukti ownership.

**P4 acceptance lifecycle**: setelah Start/Reset, pasangan tetap bisa melakukan jalur yang diizinkan, sedangkan lintas tenant dan management tetap tertutup. Stop/Delete tidak meninggalkan firewall allow rule atau tunnel terminal orphan.

---

## 9. UX Portal Admin/Dosen yang direkomendasikan

**List view:** lab ID, owner/kelas, course, status session, status Attacker/Victim, created/last activity, image version (bila tersedia), tombol View/Start/Stop/Reset/Delete sesuai permission.

**Detail view:**

- Pair card: `LAB-ATK (Attacker)` dan `LAB-ENV (Victim)`, nama instance dan IPv4 dari **session terkait**, OS Kali 2026.3 / Ubuntu 24.04.5 sesuai metadata verified, status last-known dan `last_checked_at` bila ada.
- Golden Image: alias + fingerprint singkat + image version + waktu provisioning.
- Operation timeline: `REQUESTED → RUNNING → SUCCEEDED/FAILED/PARTIAL`, durasi, actor, sanitized error dan tombol retry yang diotorisasi.
- Destructive confirmation: tampilkan `Lab #ID`, pemilik, kedua instance, konsekuensi filesystem/progres. Untuk Delete/Reset, alasan tindakan (reason) dan konfirmasi eksplisit.
- Mobile friendly: kartu satu kolom, tabel scroll/stack, tombol berjarak, dialog tidak terpotong.
- Theme: gunakan token Light/Dark existing (jangan hardcode teks navy di card dark atau teks abu muda di card putih). Pembedaan `READY` vs `RUNNING` vs `FAILED` tidak hanya melalui warna.

**Jangan melakukan polling Incus per browser/view render.** Query DB + async status sync/cached health lebih aman untuk UAT ~100 mahasiswa. Hindari mengeksekusi `incus exec` di request HTTP.

---

## 10. Test plan yang harus Malik jalankan

### 10.1 Unit/Feature (mock services)

| ID | Test | Expected |
|---|---|---|
| ADM-LC-01 | Admin dengan hak akses menjalankan Stop | Operation accepted, queued exactly once |
| ADM-LC-02 | Dosen tanpa scope kelas melakukan Delete | 403/denied, zero job |
| ADM-LC-03 | Dua klik Reset bersamaan | Satu operation / idempotent |
| ADM-LC-04 | Start pada dua container stopped | Pair transitions benar |
| ADM-LC-05 | Start pada already Running | Idempotent/no destructive rebuild |
| ADM-LC-06 | Stop salah satu container gagal | Partial/Failed terlihat, tidak false success |
| ADM-LC-07 | Reset gagal di Victim setelah Attacker dibangun | No cross-tenant cleanup; recoverable state |
| ADM-LC-08 | Delete saat Provisioning masih berjalan | Reject/serialize, not racing |
| ADM-LC-09 | Workspace/data tidak sesuai session ID | Hard stop, no command |
| ADM-LC-10 | Legacy queue job retry | Guard cegah replay destruktif |
| ADM-LC-11 | Status incus Running tapi service HTTP mati | Tidak tandai Lab Ready |
| ADM-LC-12 | Flag/progress setelah Reset | Sesuai policy yang disepakati |

### 10.2 Staging/integration (dua pasangan lab uji terisolasi)

1. Provision dua akun **A/B** dari image yang dipin; catat baseline fingerprint/workspace, IP, status, dan health.
2. Stop pasangan A → verifikasi hanya A yang Stopped; B tetap berjalan.
3. Start A → verifikasi kedua container dan layanan Victim READY; B tidak berubah.
4. Restart (bila fitur tersedia) → data uji persisten.
5. Reset A → baseline filesystem dipulihkan; policy/flag/progres sesuai kontrak; B tidak berubah.
6. Delete A → kedua instance A hilang; workspace/ACL per-A dibersihkan; B dan Golden Image **tetap ada**.
7. Uji failure injection, concurrent requests, browser disconnect, 2x click, queue timeout, retry dan denied authorization.
8. Bandingkan DB, IaC dan Incus sesudah setiap aksi. Simpan screenshot, log sanitisasi, timestamp WIB dan hasil **PASS/FAIL**, bukan klaim tanpa bukti.

**Gate deploy:** staging PASS, backup dan rollback tersedia; canary hanya satu akun nonproduksi; operator tim menyetujui change window. **Jangan melakukan uji delete/reset pada Lab #55 atau tenant aktif lainnya.**

---

## 11. Read-only commands untuk Malik (dengan otorisasi operator)

Perintah berikut **hanya inspeksi** dan tidak memodifikasi resource. Hindari dump `.env`, token atau state OpenTofu:

```bash
cd /home/iaas/projects/webportal-iaas || exit 1

# Temukan service/handler lifecycle aktual di source production
rg -n 'class (ProvisionLabSession|ResetLabSession|DeleteLabSession|StopLabSession)|function (startLab|resetLab|stopLab|deleteLab)|queueOwnedLabPower|LabSessionManager' app resources/views/filament

# Lihat kelas model dan properti tanpa mengakses secret
rg -n 'class LabSession|class LabEnvironment|attacker_ip|victim_ip|cpu_limit|memory_limit_mb|container_image' app

# Incus: alias & fingerprint saat ini
incus image info lab-atk:imnehnet-gi-attacker-v1
incus image info lab-env:imnehnet-gi-victim-v1

# Instance sampel yang sudah diidentifikasi, hanya untuk validasi metadata
incus info lab-atk:attacker-user-29
incus info lab-env:victim-user-29
```

**[CHECK]** Apakah `incus image info remote:alias` sesuai syntax/permissions Incus 7 pada environment? Bila tidak, gunakan `incus image list remote:` lalu lookup fingerprint exact. Jangan menginterpretasi command error sebagai image absent.

**Verifikasi source IaC/queue tambahan (read-only):** lokasi workspace sebenarnya, image ref pada `ProvisionLabSession`, enum status, lifecycle power service, session lock/cache lock, payload job & middleware, retention audit, dan migration yang mencatat image version.

---

## 12. Risiko utama dan keputusan desain

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Alias image digeser tanpa pin | Reset lab lama memakai baseline baru tak terduga | Persist fingerprint/version per session |
| Reset disamakan dengan restart | Data/progres mahasiswa hilang atau tidak konsisten | Pisahkan UX dan kontrak reset |
| Stop langsung Incus tetapi IaC expects running | Drift / container menyala kembali | Single ownership power state |
| Delete paralel dengan provisioning | Orphan state, resource salah, DB corrupt | Session locking & operation guard |
| Retry massal failed jobs | Replay delete/cleanup lama | Targeted retry with identity checks |
| Cleanup broad ACL/IP | Tenant lain bisa terputus/terekspos | Per-session scoped policy, test A/B |
| Running dianggap Ready | Terminal atau Victim web mati | Multi-layer health check |
| Dosen terlalu banyak permission | Destructive misuse | RBAC + scope kelas + audit |
| Browser polling langsung Incus | Load tinggi saat UAT | Async worker + cached DB status |
| Legacy student action diganti total | Regression produksi | Reuse services, feature flag, staged rollout |

**Stop condition:** mapping tenant tidak cocok; fingerprint image ambigu; ada existing session operation aktif; workspace state tidak konsisten; backup/rollback tidak jelas; partial deletion tidak recoverable; indikasi komunikasi lintas tenant; konektivitas management/SSH/Tailscale terancam.

---

## 13. Checklist implementasi untuk Malik

- [ ] Baca `AGENTS.md`, `docs/status.md`, architecture/networking/decisions/runbook dari repo live.
- [ ] Verifikasi fingerprint/alias dua Golden Image pada host yang benar.
- [ ] Inspeksi enum model, database migration, source lifecycle job/service terbaru dan authorization existing.
- [ ] Tetapkan kontrak `Start`, `Stop`, `Restart`, `Reset Environment`, `Reset Progress`, `Delete` serta privilege admin vs dosen.
- [ ] Tetapkan siapa owner power state: service Incus + desired state OpenTofu yang tidak drift.
- [ ] Simpan image fingerprint/version **per session** (bila belum tersedia) dengan migrasi dan backward compatibility yang disetujui.
- [ ] Buat shared service orchestration untuk student/admin; adapter UI hanya memanggil use case authorized.
- [ ] Tambahkan per-session lock, idempotency, active-operation guard, dan correlation ID.
- [ ] Tambahkan audit trail dan state reconciliation DB/Incus/OpenTofu/health.
- [ ] Implementasi UX responsif, status Processing/Partial/Failed dan confirmation destruktif.
- [ ] Uji dengan dua tenant staging A/B termasuk failure injection, cross-tenant isolation dan regressions terminal/objective.
- [ ] Siapkan rollback dan approval sebelum production deploy.

---

## 14. Pertanyaan teknis yang harus dijawab setelah inspeksi Malik

1. Di production source hari ini, operasi Start/Stop/Reset/Delete diproses oleh kelas/service mana saja? Adakah queue middleware/session lock?
2. Status enum yang tersedia apa saja, dan bagaimana penanganan *partial start/stop/delete* sekarang?
3. Power `stop/start` mengubah desired-state OpenTofu atau hanya status runtime Incus? Bagaimana mencegah drift?
4. Reset student yang existing menghapus filesystem dan progres, atau hanya membangun ulang container? Bagaimana deklarasi normalisasi database?
5. Fingerprint image per session sudah disimpan atau hanya alias/image string pada `LabEnvironment`?
6. Apakah role dosen dibatasi hanya kelas/course miliknya? Apakah delete hanya admin?
7. Bagaimana resource dan firewall cleanup pada worker yang crash di tengah operasi?
8. Bagaimana health check terminal dan service target dibuktikan sesudah Start/Reset?

Semua pertanyaan di atas adalah **review gates**, bukan alasan untuk menulis lifecycle baru dari nol bila service existing sudah memadai.

---

## 15. Referensi dan batas verifikasi

**Dasar utama handoff ini:**

- Output terminal 20 November 2026: `incus exec lab-atk:attacker-user-29 -- cat /etc/os-release` menunjukkan **Kali Rolling 2026.3** dan `incus exec lab-env:victim-user-29 -- cat /etc/os-release` menunjukkan **Ubuntu 24.04.5 LTS**.
- Screenshot portal Student 20 November: pasangan Lab #55 user-29, IP `10.30.0.103` / `10.20.0.103`, role/status, dan konfigurasi sistem yang ditampilkan.
- Source potongan Blade `my-labs-active.blade.php`, `imnehnet-progress.blade.php`, dan `imnehnet-pentest-workspace.blade.php` yang disampaikan dalam percakapan.
- Informasi teknis terdahulu dari sesi implementasi IMNEHNET mengenai alias/fingerprint image, Laravel jobs, OpenTofu dan dua Incus remote.
- `IMNEHNET_Golden_Image_Handoff_Abdi_Alief_2026-11-18.md`: dokumen **rencana/audit sebelumnya**, bukan bukti seluruh fase implementasi PASS.
- `project-instructions-for-new-chat.md`: aturan source of truth, pemisahan VERIFIED/DESIGN/ASSUMPTION dan operasi aman.

**Bukan diverifikasi langsung dalam penyusunan dokumen ini:** repo live, versi final image build recipe dan paket yang terpasang, ACL/nftables terkini, hasil E2E lifecycle admin/dosen, status queue hari ini, hasil semua pengujian P4, log bench performa, atau fungsi reset existing secara end-to-end. Malik harus memeriksa hal-hal tersebut sebelum implementasi/rollout.

**Catatan keamanan:** Dokumen ini memuat IP internal dan identitas resource contoh; bagikan **hanya kepada tim proyek**, bukan repositori publik atau presentasi terbuka tanpa penyuntingan.

---

**Arah implementasi:** **Golden Image (immutable baseline) → per-tenant IaC instances → centralized lifecycle orchestration → authorized admin/dosen actions → reconciled runtime state + audit**. Hindari endpoint yang mengeksekusi shell destruktif langsung dari input browser.