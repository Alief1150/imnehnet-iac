# IMNEHNET — Arsip Konteks & Progres Percakapan

Direktori ini adalah **handoff lintas chat** untuk proyek PBL IMNEHNET. Tujuannya agar percakapan baru dapat melanjutkan pekerjaan tanpa mengulang investigasi atau mengira rencana sebagai implementasi.

## Urutan baca wajib untuk chat baru

1. [AGENTS.md](../../AGENTS.md) — kebijakan keamanan dan pembagian OpenTofu/Ansible.
2. [Checkpoint terbaru: Btrfs P3 / Student Portal, 2026-11-26](./2026-11-26-btrfs-p3-student-portal-handoff.md) — prioritas implementasi terkini.\n3. [Checkpoint Admin Environment, 2026-11-26](./2026-11-26-admin-environment-handoff.md) — pekerjaan terpisah yang masih relevan.
4. [docs/status.md](../status.md) — bukti historis terverifikasi; cek tanggal pembaruan, tidak otomatis menggambarkan runtime sekarang.
5. [docs/architecture.md](../architecture.md), [docs/networking.md](../networking.md), [docs/decisions.md](../decisions.md), [docs/runbook.md](../runbook.md) sesuai kebutuhan.
6. Inspeksi **repository dan server IAAS-MGMT aktual** sebelum perubahan; portal server mempunyai perubahan lokal besar yang belum tentu ada di GitHub.

## Protokol pembaruan (setiap milestone)

- Buat atau perbarui file bertanggal `YYYY-MM-DD-topik-handoff.md`, dengan: **waktu dan sumber bukti**, **sudah diverifikasi**, **belum diverifikasi**, **keputusan**, **risiko**, **file/komponen terdampak**, **tes**, **langkah berikutnya**, **rollback**.
- Bedakan `TERVERIFIKASI`, `DILAPORKAN OPERATOR`, `RENCANA`, dan `BELUM DIKETAHUI`.
- Jangan menyimpan chat mentah, kredensial, access token, private key, file .env, database dump, OpenTofu state, atau keluaran log yang mungkin memuat rahasia.
- Jika ada perubahan aktual pada runtime, verifikasi lalu perbarui `docs/status.md`; jangan mengubahnya berdasarkan dugaan.
- Jangan menyinkronkan folder kerja portal yang penuh backup/untracked files secara massal. Audit dan staging selektif terlebih dahulu.

## Prompt pembuka chat baru

> Lanjutkan proyek IMNEHNET. Baca `AGENTS.md`, `docs/chat-archive/README.md`, dan checkpoint paling baru dalam `docs/chat-archive/`, lalu `docs/status.md`. Gunakan kondisi dari server sebagai sumber kebenaran jika sudah diverifikasi; jangan samakan GitHub portal dengan working tree IAAS-MGMT. Inspeksi sebelum mutasi, jaga Student dan SSH/Tailscale, jangan bocorkan secrets, dan lanjutkan pekerjaan dari daftar next steps checkpoint terakhir.

Catatan: arsip ini adalah **ringkasan handoff**, bukan transkrip lengkap; percakapan baru perlu membaca file terbaru dan memverifikasi keadaan live.
