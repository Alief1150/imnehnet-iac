# Dokumentasi DevOps 01 — Verifikasi IAAS-MGMT sebagai Ansible Controller

## Tujuan

Tahap awal jobdesk **DevOps Engineer** adalah memastikan VM `IAAS-MGMT` dapat berfungsi sebagai **Ansible Controller** sebelum digunakan untuk provisioning dan konfigurasi otomatis VM lab.

Pada arsitektur IMNEHNET, `IAAS-MGMT` merupakan VM management yang bersifat permanen dan menjadi tempat menjalankan Ansible serta automation layer.

---

## Kondisi Awal

- Host fisik: `server-imnehnet`
- Hypervisor: KVM/QEMU + libvirt
- VM management: `IAAS-MGMT`
- Network MGMT: `192.168.100.0/24`
- IP `IAAS-MGMT`: `192.168.100.10`
- Sistem operasi `IAAS-MGMT`: Ubuntu Server 24.04 LTS
- Ansible: sudah terpasang

---

## 1. Memastikan IAAS-MGMT Berjalan

Jalankan pada host fisik `server-imnehnet`:

```bash
virsh domstate IAAS-MGMT
```

Hasil yang diharapkan:

```text
running
```

Alternatif untuk melihat seluruh VM:

```bash
virsh list --all
```

### Dokumentasi

Ambil screenshot output `virsh domstate IAAS-MGMT` atau `virsh list --all`.

**Keterangan gambar:**

> Verifikasi status VM IAAS-MGMT pada host KVM/QEMU menggunakan libvirt.

---

## 2. Masuk ke IAAS-MGMT

Masuk ke VM `IAAS-MGMT` menggunakan metode SSH yang saat ini sudah digunakan oleh tim.

Contoh apabila akses langsung ke jaringan MGMT tersedia:

```bash
ssh <user>@192.168.100.10
```

Setelah masuk, cek hostname:

```bash
hostname
```

Hasil yang diharapkan:

```text
iaas-mgmt
```

---

## 3. Memastikan Ansible Terpasang

Jalankan di `IAAS-MGMT`:

```bash
ansible --version
```

Pastikan informasi versi Ansible muncul tanpa error.

### Dokumentasi

Ambil screenshot output:

```bash
ansible --version
```

**Keterangan gambar:**

> Verifikasi instalasi Ansible pada IAAS-MGMT sebagai controller untuk proses otomatisasi infrastruktur.

---

## 4. Membuat Struktur Workspace Ansible

Buat direktori kerja sederhana:

```bash
mkdir -p ~/imnehnet-ansible/{inventory,playbooks,roles,group_vars}
cd ~/imnehnet-ansible
```

Cek struktur:

```bash
find . -maxdepth 2 -type d | sort
```

Struktur yang diharapkan:

```text
.
./group_vars
./inventory
./playbooks
./roles
```

---

## 5. Membuat Inventory Awal

Untuk tahap pertama, Ansible diuji terhadap `IAAS-MGMT` sendiri agar belum bergantung pada VM LAB-BLUE.

Buat file:

```bash
nano ~/imnehnet-ansible/inventory/hosts.ini
```

Isi:

```ini
[management]
localhost ansible_connection=local
```

Simpan file, lalu cek:

```bash
cat ~/imnehnet-ansible/inventory/hosts.ini
```

---

## 6. Menguji Koneksi Ansible

Masih di direktori:

```bash
cd ~/imnehnet-ansible
```

Jalankan:

```bash
ansible -i inventory/hosts.ini management -m ping
```

Hasil yang diharapkan kurang lebih:

```text
localhost | SUCCESS => {
    "changed": false,
    "ping": "pong"
}
```

### Dokumentasi

Ambil screenshot output `SUCCESS` dan `pong`.

**Keterangan gambar:**

> Pengujian koneksi Ansible pada IAAS-MGMT berhasil, yang menunjukkan controller siap menjalankan playbook automation.

---

## 7. Membuat Playbook Verifikasi Controller

Buat file:

```bash
nano ~/imnehnet-ansible/playbooks/check-controller.yml
```

Isi:

```yaml
---
- name: Verifikasi IAAS-MGMT sebagai Ansible Controller
  hosts: management
  gather_facts: true

  tasks:
    - name: Menampilkan hostname
      ansible.builtin.debug:
        msg: "Hostname controller: {{ ansible_hostname }}"

    - name: Menampilkan sistem operasi
      ansible.builtin.debug:
        msg: "OS: {{ ansible_distribution }} {{ ansible_distribution_version }}"

    - name: Menampilkan jumlah RAM
      ansible.builtin.debug:
        msg: "RAM: {{ ansible_memtotal_mb }} MB"
```

Jalankan:

```bash
cd ~/imnehnet-ansible

ansible-playbook   -i inventory/hosts.ini   playbooks/check-controller.yml
```

Pastikan bagian akhir menampilkan:

```text
failed=0
```

### Dokumentasi

Ambil screenshot bagian `PLAY RECAP`.

**Keterangan gambar:**

> Pengujian playbook Ansible pada IAAS-MGMT berhasil dijalankan tanpa kegagalan.

---

## 8. Hasil Tahap Awal DevOps

Setelah tahap ini selesai, kondisi DevOps adalah:

```text
IAAS-MGMT
│
├── Ubuntu Server
├── Ansible Controller
│
├── inventory/
├── playbooks/
├── roles/
└── group_vars/
```

Dengan demikian, `IAAS-MGMT` sudah siap digunakan sebagai pusat automation untuk tahap berikutnya:

```text
IAAS-MGMT
    │
    │ Ansible / Automation
    ▼
LAB-BLUE-TEMPLATE
    │
    ▼
LAB-U01 / LAB-U02 / ...
```

---

## Kesimpulan untuk Laporan

Pada tahap awal implementasi DevOps, VM `IAAS-MGMT` diverifikasi sebagai management node dan Ansible Controller. Pengujian dilakukan dengan memastikan status VM aktif, memverifikasi instalasi Ansible, membuat struktur workspace Ansible, membuat inventory awal, serta menjalankan Ansible ad-hoc command dan playbook sederhana. Hasil pengujian menunjukkan Ansible dapat berjalan dengan baik tanpa kegagalan sehingga `IAAS-MGMT` siap digunakan sebagai controller untuk proses provisioning dan konfigurasi otomatis environment praktikum pada tahap berikutnya.

---

## Tahap Berikutnya

Setelah `LAB-BLUE-TEMPLATE` tersedia dari sisi infrastruktur, pekerjaan DevOps berikutnya adalah:

1. menambahkan LAB-BLUE ke inventory Ansible;
2. menguji koneksi SSH dari IAAS-MGMT ke LAB-BLUE;
3. membuat playbook konfigurasi LAB-BLUE;
4. membuat automation clone/provisioning;
5. membuat lifecycle `create`, `start`, `stop`, `reset`, dan `delete`;
6. menghubungkan automation dengan portal Laravel.
