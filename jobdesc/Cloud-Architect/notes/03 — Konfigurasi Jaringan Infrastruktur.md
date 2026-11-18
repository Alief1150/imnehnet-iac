# Dokumentasi 03 — Konfigurasi Jaringan Infrastruktur

## Tujuan

Tahap ini bertujuan untuk mengonfigurasi jaringan pada virtual machine agar seluruh VM dapat saling terhubung dalam satu jaringan infrastruktur. Konfigurasi dilakukan dengan memberikan IP address statis pada masing-masing VM, sehingga komunikasi antar-VM dapat digunakan untuk mendukung pengelolaan infrastruktur dan lingkungan praktikum.

## Kondisi Awal

Virtual machine IAAS-MGMT, LAB-ENV, dan LAB-ATK telah berhasil dibuat dan menjalankan sistem operasi masing-masing. Ketiga VM menggunakan network MGMT pada libvirt dengan subnet 192.168.100.0/24.

## Langkah Pengerjaan

### 1. Menentukan Alokasi IP Address

Menentukan IP address untuk masing-masing VM agar berada dalam satu subnet jaringan.

| Virtual Machine | IP Address | Gateway |
|---|---|---|
| IAAS-MGMT | 192.168.100.10/24 | 192.168.100.1 |
| LAB-ENV | 192.168.100.20/24 | 192.168.100.1 |
| LAB-ATK | 192.168.100.30/24 | 192.168.100.1 |

### 2. Konfigurasi IP Address IAAS-MGMT

Melakukan konfigurasi IP address statis pada VM IAAS-MGMT menggunakan Netplan.

File konfigurasi:

\`\`\`bash
nano /etc/netplan/50-cloud-init.yaml
\`\`\`

Konfigurasi:

\`\`\`yaml
network:
  version: 2
  ethernets:
    enp1s0:
      dhcp4: false
      addresses:
        - 192.168.100.10/24
      routes:
        - to: default
          via: 192.168.100.1
      nameservers:
        addresses:
          - 8.8.8.8
          - 1.1.1.1
\`\`\`

### 3. Konfigurasi IP Address LAB-ENV

Melakukan konfigurasi IP address statis pada VM LAB-ENV menggunakan Netplan.

File konfigurasi:

\`\`\`bash
nano /etc/netplan/50-cloud-init.yaml
\`\`\`

Konfigurasi:

\`\`\`yaml
network:
  version: 2
  ethernets:
    enp1s0:
      dhcp4: false
      addresses:
        - 192.168.100.20/24
      routes:
        - to: default
          via: 192.168.100.1
      nameservers:
        addresses:
          - 8.8.8.8
          - 1.1.1.1
\`\`\`

### 4. Konfigurasi IP Address LAB-ATK

Melakukan konfigurasi IP address statis pada VM LAB-ATK menggunakan systemd-networkd.

File konfigurasi:

\`\`\`bash
nano /etc/systemd/network/20-eth0.network
\`\`\`

Konfigurasi:

\`\`\`ini
[Match]
Name=eth0

[Network]
Address=192.168.100.30/24
Gateway=192.168.100.1
DNS=192.168.100.1
\`\`\`

### 5. Verifikasi IP Address

Memeriksa IP address yang telah dikonfigurasi pada masing-masing VM menggunakan perintah:

\`\`\`bash
ip addr
\`\`\`

### 6. Pengujian Konektivitas Antar-VM

Melakukan pengujian konektivitas antar-VM menggunakan perintah ping.

Contoh pengujian dari IAAS-MGMT menuju LAB-ENV:

\`\`\`bash
ping -c 4 192.168.100.20
\`\`\`

Pengujian juga dilakukan menuju LAB-ATK:

\`\`\`bash
ping -c 4 192.168.100.30
\`\`\`

Pengujian dilakukan untuk memastikan komunikasi antar-VM berjalan sesuai konfigurasi jaringan.

## Dokumentasi

1. Konfigurasi IP address IAAS-MGMT.
2. Konfigurasi IP address LAB-ENV.
3. Konfigurasi IP address LAB-ATK.
4. Hasil verifikasi IP address masing-masing VM.
5. Hasil pengujian konektivitas antar-VM.

## Hasil Tahap

Konfigurasi IP address statis berhasil diterapkan pada ketiga VM. IAAS-MGMT menggunakan IP 192.168.100.10, LAB-ENV menggunakan IP 192.168.100.20, dan LAB-ATK menggunakan IP 192.168.100.30. Ketiga VM berada dalam subnet 192.168.100.0/24 dan dapat berkomunikasi satu sama lain.

## Kesimpulan

Konfigurasi jaringan infrastruktur telah berhasil dilakukan menggunakan IP address statis. Jaringan ini memungkinkan komunikasi antar-VM dan menjadi dasar untuk pengelolaan infrastruktur serta implementasi lingkungan praktikum.

## Tahap Berikutnya

Melakukan implementasi Incus pada VM LAB-ENV dan LAB-ATK sebagai platform pengelolaan container untuk lingkungan praktikum.
