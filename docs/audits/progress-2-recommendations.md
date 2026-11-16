# Rekomendasi Setelah Progress 2

## Prioritas 1 — Stabilkan test harness dan web terminal

- Arsipkan raw failure list dari full suite berikutnya agar 55 kegagalan dapat
  dipetakan dengan jumlah yang terverifikasi per test class.
- Tangani ShellRunner, TerminalPage, TerminalService, session ownership, dan
  process cleanup lebih dahulu karena area ini mendominasi failure evidence
  yang tersedia.
- Pastikan suite selalu memakai database, queue, cache, dan filesystem testing
  yang terisolasi dari runtime portal.

## Prioritas 2 — Lifecycle dan two-phase provisioning

- Pertahankan urutan OpenTofu provisioning lalu Ansible configuration.
- Tambahkan regression coverage untuk partial apply, bootstrap failure,
  timeout, retry, stale operation, dan cleanup resource parsial.
- Bedakan desired state, operation state, dan actual provider state pada semua
  transisi create/start/stop/reset/delete.

## Prioritas 3 — E2E live terkontrol

Jalankan hanya pada pasangan container disposable dengan nama, alamat, owner,
dan rollback yang disetujui. Verifikasi berurutan:

1. request portal dan queue operation;
2. plan/apply OpenTofu serta output container;
3. bootstrap Ansible dan akun terminal;
4. allow hanya pasangan attacker/victim yang benar;
5. blokir east-west ke lab lain dan management plane;
6. objective/terminal portal;
7. telemetry dan audit event;
8. stop/start/reset/delete dan verifikasi cleanup.

Jangan memakai container referensi atau environment mahasiswa aktif sebagai
target destructive test.

## Prioritas 4 — CI dan evidence

- Simpan output targeted/full suite sebagai artefak CI yang sudah disanitasi.
- Bedakan hasil operator, hasil CI GitHub, dan bukti runtime live.
- Jangan merge ketika reviewer belum memahami known issues, batas E2E, dan
  konsekuensi baseline CI yang masih gagal.
