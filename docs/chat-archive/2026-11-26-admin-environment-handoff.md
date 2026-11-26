# IMNEHNET handoff — 2026-11-26 — Admin Environment & Grid Lab

**Waktu bukti operator:** 2026-11-26 05:33 UTC / 12:33 WIB.  
**Sumber:** output read-only `imnehnet_readonly_audit.sh` yang disalin operator; inspeksi repo GitHub dalam chat.  
**Batas akses:** AI dapat membaca GitHub connector, **tidak memiliki SSH terminal ke IAAS-MGMT**, sehingga tidak dapat memverifikasi database, queue, atau perubahan runtime secara langsung.

## Sasaran pekerjaan aktif

Selesaikan implementasi **Admin Create Environment** dan lifecycle (Create/Start/Stop/Reset/Delete) yang menggunakan service provisioning Student yang sudah ada, menjaga isolasi environment dan cleanup konsisten. Hapus artefak eksperimen Grid Lab lama **hanya setelah verifikasi dan persetujuan untuk langkah destruktif**.

## Fakta terverifikasi dari output audit operator

- Server pengirim: `iaas-mgmt`, branch portal `ui/sprint-1-dashboard`, HEAD pendek `575a74a` ketika diperiksa.
- Working tree portal **sangat kotor**: banyak file `M`, `D`, dan `??` termasuk implementasi Student, admin, monitoring, CTF, migrations, backup, dan artefak build. **JANGAN** melakukan `git reset --hard`, `git clean -fd`, force checkout, bulk add/commit, atau pull yang dapat menimpa perubahan ini tanpa backup dan kajian.
- `AGENTS.md` ada pada portal lokal; `docs/status.md` **tidak ada** di portal lokal (tetapi ada di repo infrastruktur `imnehnet-iac`).
- Beberapa source Admin Grid Pair masih terlihat sebagai file untracked di portal, termasuk `AdminEnvironmentPairCreator.php`, `ProvisionAdminEnvironmentPair.php`, `AdminEnvironmentPair.php`, dan migration `2026_11_21_150000_create_admin_environment_pairs_table.php`. **Keberadaan nama file tidak membuktikan class aktif, tabel ada, ataupun migration dijalankan.**
- File/source Student aktif dan source provisioning bersama ada, misalnya `EnvironmentLifecycleService.php`, `ProvisionLabSession.php`, `OpenTofuService.php`, `PairAddressAllocator.php`, dan `StudentPairRuntimeManager.php`.
- Read-only Incus inventory menunjukkan 18 instance: `lab-web-smoke` serta 17 instance `victim-user-*`; 16 RUNNING dan 2 STOPPED pada waktu snapshot. Ini **tidak** membuktikan ownership/database/tenant mapping. Jangan hapus instance berdasarkan nama saja.
- Script audit berakhir `=== Done. No changes made ===`. Audit tersebut tidak membuktikan semua fitur berjalan, tidak menjalankan test suite, dan tidak melakukan pengecekan database.
- GitHub `Alief1150/webportal-iaas` default `main` memiliki commit terakhir yang terdeteksi dari 2026-09-26 (`c129e29`), sehingga **bukan cerminan lengkap** working tree server 2026-11-26.
- GitHub `Alief1150/imnehnet-iac` adalah repositori dokumentasi/infrastruktur utama, dan `docs/status.md` terakhir bertanggal 2026-10-20 (sebagian verifikasi tertulis 2026-10-21), tidak boleh dianggap sebagai snapshot live November.

## Dilaporkan di percakapan terdahulu — belum diverifikasi ulang

- Pembersihan record eksperimen `admin_grid_pairs` ID 1 (`admin-test-01`) dan dua environment ID 123/124 **dilaporkan berhasil** sebelumnya.
- Source Grid Lab lama **dilaporkan sudah dibersihkan dari jalur aktif**, tetapi audit terbaru menunjukkan banyak source Grid Pair masih ada; perlu klasifikasi mana referensi aktif vs artefak tertinggal.
- Mekanisme Student sebelumnya berfungsi; perlu regression test ulang sebelum dan setelah perubahan.
- Hati-hati: `admin_grid_pairs` dan `lab_environments` pernah menggunakan **koneksi database berbeda**. Penghapusan lintas koneksi tidak atomik; wajib periksa referensi, gunakan backup privat dan prosedur recovery.

## Risiko teknis yang harus diperiksa

1. **OpenTofu workspaces**: pastikan setiap `workspace select/new`, `plan`, `apply/destroy`, dan `output` menarget workspace yang benar, berseri/terkunci; jangan memakai `default` tanpa inspeksi state. OpenTofu = provisioning resource Incus; Ansible = guest config.
2. **Student regression**: hindari mengubah mekanisme Student dan pasangan attacker/defender tanpa tes.
3. **Orphan resources**: reconcile database `lab_environments`, `admin_grid_pairs`, Incus instances, workspace names, queued jobs. Jangan hapus apa pun hanya karena metadata tidak ditemukan.
4. **Kapasitas host**: status terdokumentasi menyebut i5-4460 / ~15 GiB RAM dan OOM QEMU pada Oktober. Audit kapasitas sebelum provisioning tambahan.
5. **Akses**: jangan mengubah firewall, ACL, routing, Tailscale/WARP atau SSH tanpa rollback; jangan mengekspos secret dalam chat/repo.

## Checklist tahap berikutnya

- [ ] Audit **read-only** pada jalur Admin aktif (routes, Filament, services, jobs, model, migration, queue worker) dan bandingkan terhadap Student.
- [ ] Audit database **read-only** untuk keberadaan `admin_grid_pairs`, records, migration entry dan referensi dari dua koneksi; output harus disanitasi.
- [ ] Audit binding OpenTofu workspace dan inventory Incus; catat tiap mapping ownership serta status, jangan lakukan mutasi.
- [ ] Tentukan apakah Admin memakai abstraksi Student yang sama atau thin adapter khusus admin; hindari duplikasi lifecycle.
- [ ] Buat backup terbatas dan diff/plan, lalu tes unit/feature non-destruktif.
- [ ] Implementasi minimal yang lulus test pada branch/worktree terisolasi, tanpa menghapus perubahan lokal pengguna.
- [ ] Dengan persetujuan eksplisit serta rollback: deploy terkontrol dan smoke test Create, Start, Stop, Reset, Delete; cek gagal parsial dan orphan, cek Student tetap berfungsi.
- [ ] Verifikasi hasil lalu perbarui checkpoint ini dan `docs/status.md` berdasarkan bukti terbaru.
- [ ] Audit/stage source GitHub portal selektif, jangan auto-commit backup atau untracked file acak.

## Pola update setelah pekerjaan selesai

Tambahkan entri baru: tanggal WIB, aksi dan path yang berubah, hash commit, tes yang dijalankan beserta hasilnya, resource yang disentuh, hasil verifikasi live, rollback yang tersedia, dan next action. Jangan menandai `SELESAI` hanya karena kode ditulis jika belum deployed dan di-smoke-test.

## Referensi

- `Alief1150/imnehnet-iac`: `AGENTS.md`, `docs/status.md`, `docs/architecture.md`, `docs/networking.md`, `docs/decisions.md`, `docs/runbook.md`.
- `Alief1150/webportal-iaas`: portal aktif secara konsep tetapi default branch GitHub tertinggal dari local IAAS-MGMT.
- Output audit operator 2026-11-26 dari percakapan ChatGPT; data ringkas di atas tidak memuat kredensial, state, atau data pribadi pengguna.

**Batas kepercayaan:** semua hasil pemeriksaan live di atas berasal dari output operator, bukan sesi SSH mandiri model. Gunakan sesi server aktual untuk verifikasi sebelum mengambil tindakan.
