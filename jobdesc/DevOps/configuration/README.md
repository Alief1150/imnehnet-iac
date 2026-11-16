# Konfigurasi DevOps

Dokumen ini berdasarkan audit read-only pada `IAAS-MGMT` tanggal 16 November 2026. Tidak ada `apply`, `destroy`, provisioning, playbook mutasi, restart layanan, atau perubahan jaringan yang dijalankan.

## Lokasi source kanonik

- OpenTofu: `/home/iaas/imnehnet-iac/tofu`
- Ansible: `/home/iaas/imnehnet-iac/ansible`
- Laravel: `/home/iaas/projects/webportal-iaas`

Source tersebut tidak disalin ke folder `jobdesc/`. Dokumentasi ini hanya menjelaskan struktur dan hubungan antarkomponen.

## OpenTofu dan provider Incus

Konfigurasi aktif menggunakan OpenTofu `v1.12.6` dengan provider `registry.opentofu.org/lxc/incus` versi `1.2.0`; batas versi pada konfigurasi adalah `~> 1.2`.

Provider mendaftarkan dua Incus remote:

- `lab-env` pada management plane `LAB-ENV`, sebagai remote default dan lokasi container victim;
- `lab-atk` pada attacker plane `LAB-ATK`, sebagai lokasi container attacker.

Resource utama adalah `incus_instance.student_lab`. Modul menerima nama dan image container, remote Incus, network, alamat IPv4 statis opsional, ACL opsional, batas CPU/memori, dan desired runtime state. Konfigurasi instance mengaktifkan boot autostart dan menunggu alamat IPv4 pada `eth0`. ACL hanya diteruskan ketika nilainya tidak kosong.

### File `.tf` aktif

- [`main.tf`](../../../tofu/main.tf) — mendefinisikan `incus_instance.student_lab`, resource limit, NIC, alamat statis opsional, dan ACL opsional.
- [`provider.tf`](../../../tofu/provider.tf) — mengonfigurasi provider Incus serta remote `lab-env` dan `lab-atk`.
- [`variables.tf`](../../../tofu/variables.tf) — mendefinisikan input container, remote, network, alamat, ACL, limit, dan desired running state.
- [`outputs.tf`](../../../tofu/outputs.tf) — mengeluarkan nama, IPv4, status, dan remote container.
- [`version.tf`](../../../tofu/version.tf) — mendeklarasikan source dan batas versi provider Incus.

[`tofu/.terraform.lock.hcl`](../../../tofu/.terraform.lock.hcl) mengunci versi provider yang dipakai, tetapi bukan file konfigurasi `.tf`. File state dan cache OpenTofu tidak boleh dimasukkan ke Git.

## Workspace dan state

Model yang teramati menggunakan satu workspace untuk setiap environment/container. Portal:

1. memvalidasi nama container dan workspace;
2. menolak workspace/container yang dilindungi seperti `default` dan `lab-tofu-test`;
3. mengambil lock eksklusif pada satu working directory;
4. memilih atau membuat workspace;
5. menjalankan plan sebelum apply;
6. membaca output machine-readable yang tidak ditandai sensitif;
7. memindahkan workspace aktif sebelum menghapus workspace lain.

Lock diperlukan karena satu working directory hanya memiliki satu workspace yang sedang dipilih. Tanpa lock, dua queue job dapat saling menimpa pilihan workspace dan menjalankan perubahan pada environment yang salah.

Saat audit, workspace aktif adalah `ctf-attacker-104`. Resource address yang tercatat adalah `incus_instance.student_lab`; output non-sensitif menunjukkan `attacker-user-31` berstatus `Running` pada remote `lab-atk` dengan alamat `10.30.0.104`. Isi file state tidak dibaca, disalin, atau didokumentasikan.

## Ansible

Ansible Core yang teramati adalah `2.16.3`.
[`ansible.cfg`](../../../ansible/ansible.cfg) menetapkan:

- inventory `./inventory/hosts.ini`;
- roles path `./roles`;
- host-key checking aktif;
- retry file nonaktif;
- interpreter Python otomatis;
- timeout 15 detik;
- SSH multiplexing dengan `ControlPersist=60s`;
- pipelining aktif.

Inventory produksi tidak dipublikasikan. Repository menyediakan
[`hosts.ini.example`](../../../ansible/inventory/hosts.ini.example), sedangkan
inventory aktif yang diaudit hanya mendefinisikan:

- grup `controller`: `iaas-mgmt` melalui koneksi lokal;
- grup `lab_env`: `lab-env` pada management network menggunakan user dan path key lokal controller.

File inventory hanya menyimpan path public operasional menuju key lokal, bukan material private key. Grup `lab_atk` dan `student_labs` belum ada pada inventory aktif.

### Enam playbook

1. [`controller-check.yml`](../../../ansible/playbooks/controller-check.yml) — mengumpulkan fakta controller serta memeriksa hostname, distribusi, alamat, dan Python.
2. [`lab-env-check.yml`](../../../ansible/playbooks/lab-env-check.yml) — memeriksa konektivitas dan fakta `LAB-ENV` serta versi Incus; playbook membutuhkan privilege escalation.
3. [`container-check.yml`](../../../ansible/playbooks/container-check.yml) — memeriksa konektivitas, hostname, alamat, dan uptime host dalam grup `student_labs`.
4. [`student-bootstrap.yml`](../../../ansible/playbooks/student-bootstrap.yml) — mengonfigurasi container yang sudah dibuat: memverifikasi wrapper, menunggu container, membuat user, memasang paket dasar, dan memeriksa hasil.
5. [`install-incus-wrapper.yml`](../../../ansible/playbooks/install-incus-wrapper.yml) — memasang wrapper Incus terbatas serta aturan sudoers khusus pada `LAB-ENV`, lalu memvalidasi syntax sudoers.
6. [`ctf-lab-pair.yml`](../../../ansible/playbooks/ctf-lab-pair.yml) — memprovisioning pasangan victim pada `lab_env` dan attacker pada `lab_atk` untuk Guided CTF; parameter lab, alamat, dan flag wajib diberikan eksplisit.

Playbook yang dapat mengubah host/container hanya boleh dijalankan setelah review diff/check mode dan persetujuan operator.

## Integrasi Laravel dengan OpenTofu dan Ansible

[`config/infrastructure.php`](../../../webportal/config/infrastructure.php) mengarahkan portal ke working directory OpenTofu dan Ansible di `/home/iaas/imnehnet-iac`. Nilai dapat dioverride melalui environment, tetapi `.env` tidak dibaca dalam audit dan tidak boleh disimpan ke Git.

Alur integrasi yang ditemukan:

1. `AppServiceProvider` mendaftarkan `OpenTofuService`, `AnsibleService`, dan `LiveLifecycleDriver` sebagai singleton/contract.
2. `EnvironmentLifecycleService` memvalidasi transisi, mencatat status in-flight, dan mengirim queue job tanpa menyentuh infrastruktur dari request web.
3. `ProvisionEnvironment` menjalankan dua fase: OpenTofu membuat container, lalu Ansible mengonfigurasinya.
4. `OpenTofuService` menjalankan command sebagai array tanpa shell, memakai timeout dan lock, memvalidasi workspace, serta mengabaikan output yang ditandai sensitif.
5. `AnsibleService` menjalankan `student-bootstrap.yml` terhadap grup `lab_env` dengan satu fork dan meneruskan nama container sebagai extra variable tervalidasi.
6. Hanya setelah kedua fase berhasil, lifecycle diselesaikan sebagai keadaan settled; kegagalan disimpan sebagai pesan yang disanitasi.

Integrasi tersebut memisahkan tanggung jawab: OpenTofu mengelola objek/lifecycle Incus, sedangkan Ansible mengelola konfigurasi sistem operasi dan aplikasi setelah container tersedia.
