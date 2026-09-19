# IMNEHNET Telegram Welcome Plugin

Plugin Hermes ini menyisipkan sambutan pada respons Telegram pertama setiap pengguna
yang sudah lolos otorisasi. Periode harian dimulai pukul `05.00 Asia/Jakarta`.

Update diambil secara read-only dari commit Git repository IMNEHNET:

- setiap sambutan menampilkan maksimal lima commit terbaru;
- commit yang dibuat melalui pekerjaan pengguna lain ikut terlihat setelah masuk ke
  repository bersama;
- isi percakapan privat tidak pernah dijadikan update bersama.

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

## Briefing pukul 05.00 WIB

Selain hook saat pengguna mulai berinteraksi, cron job
`IMNEHNET daily welcome 05:00 WIB` mengirim briefing ke target DM Telegram
yang terdaftar dengan jadwal `0 5 * * *`.

Briefing selalu mengambil maksimal lima commit terbaru. Delivery ke pengguna
Telegram lain tetap bergantung pada chat tersebut sudah terhubung ke gateway;
bot Telegram tidak dapat memulai percakapan dengan pengguna yang belum pernah
memulai bot.
