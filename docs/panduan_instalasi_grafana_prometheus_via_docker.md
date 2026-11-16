# Panduan Instalasi & Konfigurasi Grafana & Prometheus via Docker

Dokumentasi ini berisi langkah-langkah lengkap untuk melakukan *deployment* Grafana dan Prometheus menggunakan Docker Compose, menghubungkan keduanya sebagai *datasource*, serta melakukan *import dashboard* monitoring server.

---

## 1. Membuat Direktori dan Konfigurasi Prometheus
Buat direktori kerja untuk sistem monitoring, lalu buat file konfigurasi dasar untuk Prometheus.

```bash
mkdir ~/monitoring && cd ~/monitoring
nano prometheus.yml
```

Masukkan konfigurasi dasar berikut ke dalam file `prometheus.yml`:

```yaml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']
```

*Contoh tampilan file konfigurasi:*
![Konfigurasi Prometheus](assets/img/7-buat-direktori-dan-buat-file-konfigurasi-dasar-monitoring-promethuis.PNG)

---

## 2. Membuat File `docker-compose.yml`
Di direktori yang sama (`~/monitoring`), buat file `docker-compose.yml` untuk mendefinisikan layanan Prometheus dan Grafana secara bersamaan.

```bash
nano docker-compose.yml
```

Isi file dengan konfigurasi layanan berikut:

```yaml
version: '3.8'

services:
  prometheus:
    image: prom/prometheus:latest
    container_name: prometheus
    restart: always
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus

  grafana:
    image: grafana/grafana:latest
    container_name: grafana
    restart: always
    ports:
      - "3000:3000"
    volumes:
      - grafana_data:/var/lib/grafana
    depends_on:
      - prometheus

volumes:
  prometheus_data:
  grafana_data:
```

*Contoh struktur docker-compose:*
![Docker Compose Konfigurasi](assets/img/8-docker-compose-grafana-dan-prometheus.PNG)

---

## 3. Menjalankan Stack Docker Compose
Jalankan perintah berikut untuk mengunduh image container dan menjalankannya secara *background* (daemon mode):

```bash
sudo docker-compose up -d
```

*Tampilan saat container berhasil dijalankan:*
![Menjalankan Docker Compose](assets/img/9-menjalankan-docker-compose-grafana-dan-prometheus.PNG)

---

## 4. Menghubungkan Prometheus ke Grafana (Datasource)
1. Buka browser dan akses antarmuka Grafana melalui IP server Anda pada port `3000` (contoh: `http://<IP-SERVER>:3000`).
2. Masuk ke menu sidebar kiri: **Connections** > **Data sources** > **Add data source**.
3. Pilih **Prometheus**.
4. Masukkan URL Prometheus pada kolom *Prometheus server URL* (contoh: `http://<IP-SERVER>:9090`).
5. Gulir ke bawah lalu klik tombol **Save & test**.

*Contoh pengaturan Datasource:*
![Koneksi Prometheus ke Grafana](assets/img/10-koneksi-prometheus-ke-grafana.PNG)

---

## 5. Import Dashboard (Node Exporter Full)
1. Di menu sidebar Grafana, pilih **Dashboards** > **Import**.
2. Masukkan ID template dashboard publik (misal: ID `1860` untuk Node Exporter Full) atau unggah file JSON dashboard.
3. Beri nama dashboard (misal: *Server Imnehnet-Node Exporter Full*), pilih folder penyimpanan, lalu klik **Import**.

*Contoh tampilan import dashboard:*
![Import Dashboard Grafana](assets/img/11-import-template-node-exporter-full.PNG)