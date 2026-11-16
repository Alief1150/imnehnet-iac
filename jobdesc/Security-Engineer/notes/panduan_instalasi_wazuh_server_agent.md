# Panduan Instalasi Wazuh Server & Wazuh Agent

Dokumentasi ini khusus memuat langkah-langkah instalasi Wazuh Server (Single Node menggunakan Docker) di mesin manajemen (*iaas-mgmt*) serta instalasi Wazuh Agent di mesin target (*lab-env*).

---

## BAGIAN 1: Instalasi Wazuh Server (Single Node via Docker)

Langkah-langkah ini dijalankan di server manajemen untuk menghidupkan core sistem Wazuh (Indexer, Manager, dan Dashboard).

### 1. Clone Repository Wazuh Docker
Unduh repository resmi Wazuh Docker spesifik versi `4.9.0`:
```bash
git clone https://github.com/wazuh/wazuh-docker.git -b v4.9.0
cd wazuh-docker/single-node
```
![Clone Wazuh Docker](../images/15%20Install%20wazuh%20single%20node.PNG)

### 2. Generate Sertifikat SSL Indexer
Buat sertifikat keamanan terenkripsi untuk komunikasi komponen Wazuh:
```bash
sudo docker-compose -f generate-indexer-certs.yml run --rm generator
```
![Generate Sertifikat Indexer](../images/16%20pembuatan%20sertifikat%20indexer.PNG)

### 3. Menyalakan Wazuh Indexer & Manager
Jalankan container utama terlebih dahulu:
```bash
sudo docker-compose up -d wazuh.indexer wazuh.manager
```
![Menyalakan Indexer dan Manager](../images/17%20menyalakan%20wazuh%20indexer%20dan%20manager.PNG)

### 4. Konfigurasi & Menyalakan Wazuh Dashboard
Tambahkan konfigurasi port binding dan environment pada `docker-compose.yml` untuk bagian `wazuh.dashboard`:

![Konfigurasi Dashboard Docker Compose](../images/25%20penambahan%20baris%20wazuh%20dashboard%20pada%20docker%20compose.PNG)

Setelah disesuaikan, jalankan seluruh container *single-node*:
```bash
sudo docker compose up -d
```
![Menyalakan Wazuh Dashboard](../images/26%20install%20wazuh%20dashboard%20pada%20docker%20compose.PNG)

---

## BAGIAN 2: Instalasi & Konfigurasi Wazuh Agent

Langkah-langkah ini dilakukan pada mesin *target* (*lab-env*) agar dapat mengirimkan log keamanan ke Wazuh Manager.

### 1. Menambahkan GPG Key & Repository Wazuh
```bash
sudo gpg --keyserver keyserver.ubuntu.com --recv-keys 96B3EE5F2911145
sudo gpg --export 96B3EE5F2911145 | sudo tee /usr/share/keyrings/wazuh.gpg >/dev/null
sudo apt update
```
![Tambah GPG Key Wazuh](../images/18%20installasi%20wazuh%20agen.PNG)

### 2. Instalasi Paket Wazuh Agent
Instal agent dengan mendefinisikan alamat IP Wazuh Manager (misal menggunakan IP Tailscale server `100.77.79.18`):
```bash
WAZUH_MANAGER="100.77.79.18" sudo apt install wazuh-agent -y
```

### 3. Konfigurasi Alamat Server di `ossec.conf`
Buka file konfigurasi agent:
```bash
sudo nano /var/ossec/etc/ossec.conf
```
Sesuaikan tag `<server>` dengan IP manager Anda:
```xml
<client>
  <server>
    <address>100.77.79.18</address>
    <port>1514</port>
    <protocol>tcp</protocol>
  </server>
</client>
```
![Konfigurasi IP Server di ossec.conf](../images/23%20ubah%20address%20menggunakan%20ip%20tailscale%20iaasmgmt.PNG)

### 4. Menambahkan Konfigurasi Docker Listener & Log (Opsional)
Jika mesin target menjalankan container Docker, tambahkan konfigurasi ini di bagian bawah file `/var/ossec/etc/ossec.conf`:
```xml
<wodle name="docker-listener">
  <interval>10m</interval>
  <attempts>5</attempts>
  <run_on_start>yes</run_on_start>
  <disabled>no</disabled>
</wodle>

<localfile>
  <log_format>json</log_format>
  <location>/var/lib/docker/containers/*/*.log</location>
</localfile>
```
![Penambahan Docker Listener](../images/24%20Penambahan%20baris%20agar%20docker%20listen%20di%20lab%20env.PNG)

### 5. Mengaktifkan dan Menjalankan Service Wazuh Agent
```bash
sudo systemctl daemon-reload
sudo systemctl enable wazuh-agent
sudo systemctl start wazuh-agent
sudo systemctl status wazuh-agent
```
![Menjalankan Wazuh Agent](../images/19%20menjalankan%20wazuh%20agen.PNG)