# Document Database

Direktori ini adalah katalog dokumen proyek IMNEHNET yang disimpan pada server lokal
dan direplikasi melalui repository GitHub privat.

- `index.json` adalah indeks mesin-terbaca dan menyimpan checksum SHA-256.
- `proposals/` menyimpan proposal akademik dalam format PDF dan arsip sumber final.
- Revisi proposal terbaru diterima dan diarsipkan pada Selasa, 6 Oktober 2026; metadata dan checksum tercatat di `index.json` serta `SHA256SUMS`.
- `presentations/` menyimpan artefak presentasi PBL yang dapat diedit dan sudah melalui structural read-back serta visual QA.
- `audit/` menyimpan dokumentasi setup/troubleshooting audit yang sudah disanitasi; raw audit log dan mapping identitas aktual tidak boleh disimpan di repository.
- `handoffs/` menyimpan breakpoint implementasi dan context note yang sudah disanitasi; setiap fakta runtime yang dapat berubah tetap harus diverifikasi terhadap `docs/status.md` dan sistem live.
- Arsip ZIP disimpan utuh; daftar isi dan hasil pemeriksaan keselamatannya dicatat di `index.json`.
- Dokumen berstatus `reference` tidak otomatis menjadi fakta deployment.
- Status runtime tetap hanya dicatat di `docs/status.md`.
- Perubahan arsitektur harus dicatat di `docs/decisions.md` dan diselaraskan ke
  `docs/architecture.md` sebelum dianggap keputusan proyek.

## Verifikasi integritas

```bash
sha256sum -c documents/SHA256SUMS
```
