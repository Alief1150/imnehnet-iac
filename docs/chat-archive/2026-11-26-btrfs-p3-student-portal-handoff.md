# IMNEHNET handoff — 2026-11-26 — Btrfs P3 / Student Portal integration

**Checkpoint ini lebih baru dari `2026-11-26-admin-environment-handoff.md` untuk prioritas implementasi.**
Sumber: export ROYAL GUARD (pesan terakhir 26 November 12:19 WIB), output operator, dan GitHub PR #6 / CI diperiksa langsung dalam ChatGPT 26 November.

## Prioritas aktual

Lanjutkan **Golden Image P3 + pool Btrfs pada Student Portal**, bukan cleanup Admin Grid Lab. Jangan abaikan cleanup Admin, tetapi pekerjaan terbaru adalah integrasi Laravel–OpenTofu untuk Student lab setelah staging manual berhasil.

## Terverifikasi / dilaporkan

- **Dilaporkan operator:** pool `imnehnet-btrfs-prod` pada kedua Incus remote `lab-atk` dan `lab-env`, masing-masing 64 GiB, driver Btrfs, image lokal terpasang; setelah cleanup staging, `lab-atk` menggunakan 664.67 MiB dan `lab-env` menggunakan 669.61 MiB. Manual staging pair sebelumnya lolos create/boot/delete. Jangan menghapus pool atau image.
- **Dilaporkan operator:** di IAAS-MGMT repo IaC aktif `/home/iaas/imnehnet-iac`, branch `feat/lab-provisioning`; `tofu/main.tf` dan `tofu/variables.tf` **dimodifikasi lokal**; ada `tofu/variables.tf.backup-20261126-041955`. Jangan merge/reset/checkout force ke path ini.
- **Dilaporkan operator:** Laravel aktif `/home/iaas/projects/webportal-iaas`, belum memiliki field/migration `storage_pool` pada tanggal pemeriksaan. Ada banyak modifikasi lokal portal (audit terpisah), jangan bulk sync/rewrite.
- Worktree DEV operator: `/tmp/imnehnet-btrfs-p3-dev`; arsip sebelumnya terakhir menunjuk `8d41eda` lalu `1948e316`, tetapi **GitHub sekarang menunjukkan head PR #6 `53541e4332bb050e952a01168d773c4e9daad4c8` saat diperiksa**, kemudian skrip tes ditambahkan ke feature branch pada commit `7c8d5bdc21c1cdd61c2e44ff0319a089efa147f9`.
- **Diverifikasi GitHub:** PR [#6](https://github.com/Alief1150/imnehnet-iac/pull/6) tetap OPEN, DRAFT, NOT MERGED; target `main`; masih berstatus DO NOT MERGE. Memuat 14 file termasuk `tofu/main.tf`, `tofu/variables.tf`, backend provisioning, driver, config, additive migration dan tes.
- **Diverifikasi GitHub:** CI pada SHA `53541e4`, run `45761162742`: jobs `safety-and-shell`, `opentofu`, `ansible` PASS; `webportal` FAIL pada `Run application tests` (301 passed, 68 failed, 1 risky, 1257 assertions). Kegagalan meliputi Terminal/ShellRunner tests dan ketidakcocokan status/otorisasi; jangan menyatakan full suite PASS atau menganggap 68 kegagalan aman.
- Commit terbaru `53541e4` memperbaiki mock `reconcilePairedWorkspace` pada test `DeleteLabSessionE2ETest`. Belum ada hasil tes target 13 yang terkonfirmasi setelah perubahan.
- **Perubahan repositori oleh asisten pada sesi ini:** `scripts/test-btrfs-p3-isolated.sh` ditambahkan ke feature branch pada commit `7c8d5bd`, untuk menjalankan 4 file regression tests di worktree DEV tanpa operasi Incus/Tofu live. **Belum dijalankan di IAAS-MGMT.** Script menolak path produksi dan branch yang bukan feature.

## Langkah berikut yang paling tepat (tidak destruktif)

1. Pada IAAS-MGMT **hanya dalam worktree DEV** `/tmp/imnehnet-btrfs-p3-dev`, lakukan `git fetch origin feature/ctf-btrfs-p3-staged-rollout`, lalu `git merge --ff-only FETCH_HEAD` bila worktree bersih dan fast-forward memungkinkan. Jangan lakukan ini di repo IaC produksi.
2. Jalankan `bash scripts/test-btrfs-p3-isolated.sh` dari worktree DEV. Jalankan pengujian PHP/CI lain yang relevan jika hasilnya memerlukan tindak lanjut.
3. Jika test gagal, baca error dan patch secara terisolasi pada branch feature; jangan mengubah Student Portal live.
4. Telaah diff sumber feature terhadap Laravel produksi yang sangat kotor; tulis manifest perubahan selektif dan backup terbatas. Jangan langsung deploy tanpa review.
5. Pastikan migration `storage_pool` belum ada, backup DB privat, dan verifikasi dependency real; rollout feature flag **OFF** selama deployment awal.
6. Setelah test dan review selesai serta operator mengizinkan perubahan live, lakukan deploy selektif dengan rollback, smoke test satu lab dari **Student Portal** dan lifecycle plus ACL, lalu review kapasitas.
7. Catat bukti final ke `docs/status.md` hanya setelah runtime berhasil diverifikasi.

## Safety / risikonya

- Fitur Student aktif, Tailscale/SSH/WARP, dan policy ACL harus dipertahankan.
- Image Attacker P3 `imnehnet-gi-attacker-p3`; Victim `imnehnet-gi-victim-v1`; default rollout `IMNEHNET_CTF_BTRFS_ROLLOUT_ENABLED=false`. Nilai aktual .env jangan dicetak.
- Jangan menghapus container yang ada; jangan `tofu apply/destroy`, migrate database live, atau mengubah shared network tanpa diff, backup, dan persetujuan spesifik.
- Tujuan benchmark P2/P6/P7 setelah integrasi: waktu create/boot/ready, cleanup, keamanan, CPU/RAM, throughput, RTT, jitter, loss pada pasangan yang diizinkan.
- Status: **GitHub development progress exists; deployment production NOT DONE**.

## Pertanyaan pertama untuk chat berikutnya

> Apa output setelah menjalankan skrip tes terisolasi pada worktree DEV? Jika belum ada, lanjutkan audit read-only dan gunakan PR #6 serta checkpoint ini. Jangan kembali ke desain awal.

## Update 2026-11-26 — DEV script guard corrected

- Operator menjalankan `git fetch` + `git merge --ff-only FETCH_HEAD` di `/tmp/imnehnet-btrfs-p3-dev`; fast-forward berhasil dari `1948e31` ke `7c8d5bd`.
- Tes belum berjalan karena guard awal mensyaratkan `git branch --show-current` bernilai nama feature, sedangkan output kosong mengindikasikan kemungkinan *detached HEAD*.
- **Perbaikan committed** ke feature branch: `a6c1dea140c6fe0fde09ab193bd64b826707ee0a`, guard baru menerima detached HEAD jika SHA `HEAD` tepat sama dengan `origin/feature/ctf-btrfs-p3-staged-rollout` yang telah di-fetch dan file tracked bersih. Tidak mengubah production.
- Instruksi operator: ulangi `git fetch origin feature/ctf-btrfs-p3-staged-rollout && git merge --ff-only FETCH_HEAD && bash scripts/test-btrfs-p3-isolated.sh` di worktree DEV, kirim hasil tes. Sebelum production deployment, audit diff lokal dan database terlebih dulu; feature flag tetap OFF sampai staging web E2E PASS.
