# DevOps

## Ruang lingkup

DevOps bertanggung jawab atas otomasi provisioning dan konfigurasi IMNEHNET, meliputi:

- pemeliharaan konfigurasi OpenTofu dan integrasi provider Incus;
- pemeliharaan inventory, konfigurasi, dan playbook Ansible;
- pengujian statis dan konektivitas otomasi yang aman;
- integrasi antrean lifecycle Laravel dengan OpenTofu dan Ansible;
- pencatatan progres, hasil pengujian, kendala, dan rollback;
- pencegahan masuknya state, kredensial, dan material privat ke Git.

## Struktur dokumentasi

- [scripts/](scripts/) — penjelasan konfigurasi OpenTofu dan Ansible serta tautan ke source kanonik.
- [notes/](notes/) — enam catatan audit V2, audit konfigurasi sebelumnya, dan laporan progres.
- [pengujian/](pengujian/) — hasil pengujian read-only, targeted tests, dan known issues.
- [images/](images/) — 14 screenshot asli audit V2 (5 OpenTofu, 8 Ansible, 1 Git), disematkan dalam dokumentasi terkait.

## Source utama

- [OpenTofu](../../tofu/) — definisi lifecycle container melalui provider Incus.
- [Ansible](../../ansible/) — inventory template, konfigurasi controller, dan playbook.

Dokumentasi di bawah `jobdesc/` selalu menautkan source utama tersebut dan tidak
menyalinnya.

## Kontribusi Alief yang terverifikasi

Provenance Git menunjukkan commit lokal `123434f` ditulis oleh Alief Athallah
Putra dengan subjek *Let one module create both halves of a lab*. Commit tersebut
mengubah `tofu/main.tf`, `tofu/outputs.tf`, `tofu/provider.tf`, dan
`tofu/variables.tf` agar modul yang sama dapat menempatkan victim pada `lab-env`
dan attacker pada `lab-atk`.

Pernyataan ini dibatasi pada commit yang dapat diverifikasi. Dokumentasi ini tidak
mengatribusikan seluruh branch, source Laravel, atau pekerjaan anggota lain kepada
Alief.
