# Security Engineer

## Ruang lingkup

Security Engineer bertanggung jawab atas kontrol keamanan dan bukti pengujiannya, termasuk:

- threat model dan evaluasi trust boundary;
- rancangan serta verifikasi micro-segmentation dan default-deny;
- hardening host, VM, container, identitas, dan akses administratif;
- pemantauan keamanan melalui Wazuh serta penanganan temuan;
- pengujian jalur yang diizinkan dan diblokir;
- pencatatan risiko, bukti, mitigasi, dan residual risk.

## Batas dokumentasi

Folder ini tidak menyatakan kontrol telah diterapkan hanya karena tercantum dalam rancangan. Setiap status implementasi harus disertai bukti aktual yang sudah disanitasi. Kredensial, raw secret, private key, token, dan log sensitif tidak boleh disimpan di repository.

## Struktur folder terstandar

- [scripts/](scripts/) — rujukan script dan prosedur.
- [notes/](notes/) — catatan serta dokumentasi teknis.
- [pengujian/](pengujian/) — bukti/hasil pengujian.
- [images/](images/) — screenshot bukti konfigurasi.
\n\nDokumentasi Ridho mengenai Grafana/Prometheus tersedia di [notes/](notes/); keenam screenshot telah dipindahkan ke [images/](images/).\n