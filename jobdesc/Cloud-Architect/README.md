# Cloud Architect

## Ruang lingkup

Cloud Architect bertanggung jawab menjaga konsistensi rancangan arsitektur IMNEHNET, termasuk:

- batas keamanan antara host fisik, `IAAS-MGMT`, `LAB-ENV`, dan workload mahasiswa;
- pembagian tanggung jawab KVM/libvirt, Incus, OpenTofu, Ansible, dan portal;
- rancangan jaringan manajemen, jaringan internal Incus, serta micro-segmentation;
- keputusan kapasitas, ketersediaan, penyimpanan, dan jalur administrasi;
- pencatatan keputusan arsitektur dan perbedaan antara kondisi aktual dengan target.

## Batas dokumentasi

Folder ini hanya memuat keputusan, rancangan, dan progres yang memiliki bukti atau sumber yang dapat ditelusuri. Status implementasi bidang lain tidak boleh disimpulkan dari rancangan arsitektur. Kondisi runtime terverifikasi tetap dicatat pada `docs/status.md`, sedangkan keputusan arsitektur dicatat pada `docs/decisions.md`.
