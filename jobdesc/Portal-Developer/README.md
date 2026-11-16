# Portal Developer

## Ruang lingkup

Portal Developer bertanggung jawab atas aplikasi Laravel/Filament sebagai control-plane IMNEHNET, termasuk:

- autentikasi, otorisasi, dan pembatasan kepemilikan resource;
- UI/API lifecycle environment dan status operasi asynchronous;
- integrasi melalui contract/service dengan OpenTofu, Ansible, Incus, dan monitoring;
- validasi input, sanitasi error, audit activity, dan notification flow;
- pengujian aplikasi, build frontend, serta kesiapan deployment.

## Batas dokumentasi

Portal mengorkestrasi provider dan tidak menggantikan tanggung jawab OpenTofu atau Ansible. Folder ini tidak menyatakan fitur deployed, sehat, atau production-ready tanpa hasil pengujian dan verifikasi endpoint aktual. `.env`, credential database, token, state, dan material private key tidak boleh disimpan di repository.

## Struktur folder terstandar

- [scripts/](scripts/) — rujukan script dan prosedur.
- [notes/](notes/) — catatan serta dokumentasi teknis.
- [pengujian/](pengujian/) — bukti/hasil pengujian.
- [images/](images/) — screenshot bukti konfigurasi.
\n\n**Status kontribusi individu:** dokumentasi progres Malik belum tersedia; direktori disediakan sebagai template, bukan bukti implementasi.\n