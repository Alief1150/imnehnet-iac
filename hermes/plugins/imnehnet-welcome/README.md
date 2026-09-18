# IMNEHNET Telegram Welcome Plugin

Plugin Hermes ini menyisipkan sambutan pada respons Telegram pertama setiap pengguna
yang sudah lolos otorisasi, maksimal sekali per hari menurut zona waktu
`Asia/Jakarta`.

Update diambil secara read-only dari commit Git repository IMNEHNET:

- kunjungan pertama: maksimal lima commit terbaru;
- kunjungan berikutnya: maksimal lima commit sejak sambutan terakhir;
- jika tidak ada commit baru, plugin menyatakannya secara eksplisit.

State lokal disimpan dengan mode `0600` di:

```text
~/.hermes/state/imnehnet-welcome.json
```

## Deploy

```bash
mkdir -p ~/.hermes/plugins/imnehnet-welcome
cp plugin.yaml __init__.py ~/.hermes/plugins/imnehnet-welcome/
hermes plugins enable imnehnet-welcome
hermes gateway restart
```

## Validasi

```bash
hermes plugins list --plain --no-bundled
hermes plugins doctor imnehnet-welcome
systemctl --user is-active hermes-gateway.service
```

## Rollback

```bash
hermes plugins disable imnehnet-welcome
hermes gateway restart
```

Menonaktifkan plugin tidak menghapus state. Hapus state hanya jika operator secara
sengaja ingin mengirim ulang sambutan pada hari yang sama.

Variabel opsional:

- `IMNEHNET_WELCOME_REPO`: lokasi repository (default `~/imnehnet-iac`);
- `IMNEHNET_WELCOME_TIMEZONE`: zona waktu rate limit (default `Asia/Jakarta`).
