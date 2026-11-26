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

## Update 2026-11-26 — targeted tests fail-safe regression

Operator ran `a6c1dea` in DEV worktree. **Targeted suite: 10 passed, 2 errors, 1 risky (55 assertions).** Production unchanged, SSH session closed after `exit 1`.

Root cause examined in feature branch:
- `DeleteLabSession::handle()` invokes `PairedLabTeardownService::teardown()` directly; infrastructure errors propagate out of direct `handle()`. The Laravel queue calls `failed()` separately on terminal failure. Previous tests incorrectly expected direct `handle()` to swallow exceptions and fill `last_error`.
- Fail-closed invariant `Container exists but its OpenTofu workspace is missing.` remains **required**. Never weaken production teardown to make a test green.
- `MyLabsLifecycleTest::test_stopped_lab_cannot_open_workspace` previously had no assertions.
- **Commits on feature branch:** `7142d0a` adjusts teardown tests to assert propagated exception and unchanged quota/rows; `ba0ce3a` asserts no redirect for stopped lab; `c8b6c75` fixes PHP RuntimeException namespace syntax.
- **No server test run yet for these new commits.** Next operator action: fetch/fast-forward DEV then `bash scripts/test-btrfs-p3-isolated.sh`. Do not deploy production until test result and separate live diff audit. Feature flag remains OFF.

## Update 2026-11-26 — P3 DEV regression PASS; production preflight

- Operator executed P3 target suite on DEV worktree commit `c8b6c75`: **13/13 PASS, 65 assertions, 0 errors, 0 risky**, runtime PHP 8.3.6, PHPUnit 12.5.33; no production mutation.
- This is a targeted fake/SQLite suite, **not** production web E2E or full Laravel suite.
- Assistant added read-only production preflight script `scripts/audit-btrfs-p3-production-preflight.sh` on feature branch at `a44339d221ed986f474a7b526faf53d153f554a3`. It inspects source hashes/missing migration, version metadata, .env key **presence only**, Incus pool info and capacity without printing secrets or running migrate/Tofu/Incus changes.
- Next operator action: fetch/fast-forward DEV worktree, run the preflight script from there, share sanitized output. **Do not deploy** before reviewing actual production code and active IaC diffs. Full Laravel CI was previously 301 PASS / 68 FAIL / 1 risky at prior commit, requiring separate triage before claiming full-suite health.
- Rollout flag stays OFF. No direct live SSH/browser access available in this session. Staging manual pool/image PASS was operator-reported, while Laravel E2E production remains unverified.

## Update 2026-11-26 05:57 UTC — production preflight evidence

Operator ran `scripts/audit-btrfs-p3-production-preflight.sh` from DEV commit `a44339d`.

**Observed:**
- DEV worktree HEAD `a44339d`, tracked dirty count 0.
- Live Laravel `/home/iaas/projects/webportal-iaas` HEAD `575a74a`, branch `ui/sprint-1-dashboard`, **72 modified tracked files**.
- Active IaC `/home/iaas/imnehnet-iac` HEAD `123434f`, branch `feat/lab-provisioning`, **2 modified tracked files**.
- All five P3 Laravel integration files differ by SHA-256 between DEV and live: `LiveLifecycleDriver.php`, `ProvisionLabSession.php`, `LabSessionManager.php`, `OpenTofuService.php`, `config/infrastructure.php`.
- The additive migration `2026_11_26_000001_add_storage_pool_to_lab_environments.php` is **missing** in Laravel live, and migration status does not report it. Both active OpenTofu TF files differ from DEV.
- All 4 named Btrfs .env flags were **absent** in live .env (presence check only, not actual app-config values).
- Incus pool info lines did not appear in filtered output; **pool existence NOT verified from this particular preflight** (operator had reported it earlier).
- IAAS-MGMT `df -h /` 884G total/100G used/748G available (12%); memory 5.8 GiB, 2.6 GiB available, swap 4.0 GiB total/1.8 GiB used. Host/device VM storage capacity is **not** established by this.
- No production mutations made during preflight.

**Decision:** No blanket file copy, git merge, or database migration yet. Need exact, controlled source diff, image/pool confirmation, DB backup strategy and rollback review before production. Assistant added `scripts/collect-btrfs-p3-selective-diff.sh` on feature branch commit `0f678dc`, which generates a **PRIVATE** report under /tmp including relevant DEV-vs-live source diffs, migration, IaC diffs and unfiltered pool inventory. Operator must inspect report for secrets and upload privately to chat, NEVER commit public. Next step: selectively reconcile changes and then test one web lab only after explicit production change approval.

## Update 2026-11-26 05:59 UTC — selective diff reviewed and production patch candidate prepared

- Operator uploaded private diff report (391 lines / 17,175 bytes). Reviewed locally, NOT copied to public GitHub.
- Actual Incus `lab-atk:imnehnet-btrfs-prod` and `lab-env:imnehnet-btrfs-prod`: both **CREATED**, Btrfs, 64 GiB, ~660.61 MiB / ~654.41 MiB used; image fingerprints begin `62c78b24fd0d` and `8b12db08acbe`. This verifies pool state, not student web E2E.
- All five Laravel integration diffs are small and hunk-targeted. Active OpenTofu already has a storage_pool enum with `default`, `imnehnet-btrfs-test`, `imnehnet-btrfs-prod`, and root disk dynamic override; IaC file differences are comment/reordering only according to supplied report. **Do not copy/merge entire TF files**.
- Important compatibility fix discovered: feature `LabSessionManager` changed config() fallback for legacy image selection even when flag OFF. Fixed in feature branch commit `f2540bad56a97779aa4f4ff4988cb9eb6ea68163` to retain `images:ubuntu/24.04` and `images:kali` fallback while OFF. After that fix targeted suite needs rerun.
- Assistant assembled a **private download artifact** `imnehnet-p3-selective-candidate.zip` with five-file unified diff patch (86 insertions, 17 deletions), additive migration and README. This was built from the operator's private diff and is NOT in public repo. Do NOT assume it applies to any modified production files without `git apply --check` against live; no deploy or migrate has been executed.
- Next: operator transfer candidate zip to IAAS-MGMT and run **read-only** `git apply --check` in live Laravel tree; verify PHP syntax and DB backups/migration process and queue drain plan. Apply only after explicit approval and safe rollback path. Existing production has 72 dirty tracked files and active IaC 2 dirty tracked files. Web acceptance and rollout flag ON still pending.
