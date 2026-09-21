# Document Database

Direktori ini adalah katalog dokumen proyek IMNEHNET yang disimpan pada server lokal
dan direplikasi melalui repository GitHub privat.

- `index.json` adalah indeks mesin-terbaca dan menyimpan checksum SHA-256.
- `proposals/` menyimpan proposal akademik dalam format PDF.
- Dokumen berstatus `reference` tidak otomatis menjadi fakta deployment.
- Status runtime tetap hanya dicatat di `docs/status.md`.
- Perubahan arsitektur harus dicatat di `docs/decisions.md` dan diselaraskan ke
  `docs/architecture.md` sebelum dianggap keputusan proyek.

## Verifikasi integritas

```bash
sha256sum -c documents/SHA256SUMS
```
