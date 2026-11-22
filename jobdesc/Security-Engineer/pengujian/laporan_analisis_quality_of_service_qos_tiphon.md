# Laporan Analisis Quality of Service (QoS) Jaringan Internet Berdasarkan Standar TIPHON

**Studi Evaluasi Kinerja Trafik Data Multi-Waktu (Sesi Pagi, Siang, Sore, dan Malam) Menggunakan Wireshark**

---

## BAB 1. Pendahuluan & Metodologi Pengukuran

### 1.1. Latar Belakang & Tujuan Pengujian
Evaluasi kinerja jaringan internet secara komprehensif memerlukan pengujian parameter *Quality of Service* (QoS) yang terstruktur dan objektif. Pengujian ini bertujuan untuk mengukur, menganalisis, serta mengklasifikasikan performa trafik data pada empat rentang waktu berbeda dalam satu siklus harian (**Sesi Pagi**, **Sesi Siang**, **Sesi Sore**, dan **Sesi Malam**). Pendekatan multi-waktu ini digunakan untuk menangkap variasi dinamika beban jaringan akibat fluktuasi aktivitas pengguna.

Metodologi evaluasi dan ambang batas klasifikasi dalam analisis ini merujuk pada kerangka kerja *Telecommunications and Internet Protocol Harmonisation Over Networks* (**TIPHON**) yang diterbitkan oleh ETSI (**ETSI TR 101 329**). Selain itu, penyusunan struktur analisis, teknik ekstraksi parameter deskriptif, dan interpretasi data mengacu pada referensi ilmiah Ardiansyah & Pamuji (*JATI*, Vol. 9 No. 5, Oktober 2025).

Melalui pengambilan sampel paket data (*packet capture*) berbasis perangkat lunak Wireshark, parameter utama QoS—meliputi **Throughput**, **Packet Loss**, **Delay** (Waktu Tunda), dan **Jitter** (Variasi Waktu Tunda)—dihitung secara matematis untuk menentukan indeks kuantitatif serta kategori kualitas jaringan secara keseluruhan.

### 1.2. Lingkungan & Konfigurasi Pengujian
Pengujian dilakukan pada tautan nirkabel (Wi-Fi) menggunakan lingkungan perangkat keras dan perangkat lunak yang disetel konstan sepanjang seluruh sesi pengukuran guna menjamin validitas dan komparabilitas data:

* **Perangkat Keras (*Hardware*):** AMD Ryzen 3 5300U with Radeon Graphics
* **Sistem Operasi (*OS*):** 64-bit Windows 11 Home Single Language (24H2), build 26100
* **Perangkat Lunak *Capture*:** Dumpcap / Wireshark 4.6.10 (`v4.6.10-0-geac916f07299`)
* **Antarmuka Jaringan:** Wi-Fi (`\Device\NPF_{8DDA1CF6-CE82-4661-8364-8DE493D98985}`), Link type: Ethernet, Snaplen: `262144 bytes`
* **Capture Filter (Seragam Seluruh Sesi):**
  ```text
  host 2606:4700:3033::6815:53dc or host 2606:4700:3035::ac43:b634 or host 104.21.83.220 or host 172.67.182.52
  ```

### 1.3. Spesifikasi Teknis Capture Seluruh Sesi

#### Tabel 1.1. Ringkasan Spesifikasi Teknis Pengambilan Data Paket (*Capture Summary*) Empat Sesi

| Parameter Capture | Sesi Pagi | Sesi Siang | Sesi Sore | Sesi Malam |
| :--- | :--- | :--- | :--- | :--- |
| **Nama Berkas PCAPNG** | `wireshark_Wi-FiDBXAZ3.pcapng` | `UJI QOS SIANG.pcapng` | `wireshark_Wi-FiC5J6Y3.pcapng` | `uji qos malam.pcapng` |
| **Nama Berkas CSV** | `Uji test pagi.csv` | `UJi QOS SIANG.csv` | `record 3 wireshark full.csv` | `tes mmalam.csv` |
| **Tanggal & Waktu Pengujian** | 2026-11-22<br>09:06:27 – 09:14:53 WIB | 2026-11-22<br>13:30:03 – 13:41:26 WIB | 2026-11-21<br>16:12:06 – 16:23:23 WIB | 2026-11-21<br>22:08:42 – 22:17:31 WIB |
| **Durasi (*Time Span / Elapsed*)** | 00:08:25 (`505,982 s`) | 00:11:22 (`682,927 s`) | 00:11:16 (`676,406 s`) | 00:08:49 (`529,112 s`) |
| **Total Paket *Captured & Displayed*** | 4.930 paket (100%) | 4.617 paket (100%) | 5.782 paket (100%) | 4.413 paket (100%) |
| ***Dropped Packets* (OS/Hardware)** | 0 (0,0%) | 0 (0,0%) | 0 (0,0%) | 0 (0,0%) |
| **Total Ukuran Data Transaksi** | 3.279.444 Bytes | 2.919.422 Bytes | 3.678.919 Bytes | 3.018.300 Bytes |
| **Rata-rata Ukuran Paket Data** | 665 Bytes | 632 Bytes | 636 Bytes | 684 Bytes |
| **Rata-rata Laju Paket (PPS)** | 9,7 pps | 6,8 pps | 8,5 pps | 8,3 pps |
| **Rata-rata Bitrate (Wireshark)** | 51 k bits/s | 34 k bits/s | 43 k bits/s | 45 k bits/s |

---

## BAB 2. Landasan Teori & Tabel Standar Klasifikasi TIPHON

Standardisasi TIPHON (**ETSI TR 101 329**) menetapkan kriteria kuantitatif untuk mengukur parameter performa jaringan telekomunikasi berbasis IP. Setiap parameter diukur dan diklasifikasikan ke dalam skala indeks bernilai **1 hingga 4**, yang mencerminkan mutu dari kriteria **Buruk** hingga **Sangat Bagus**.

#### Tabel 2.1. Standar Nilai Quality of Service Keseluruhan TIPHON

| Nilai Indeks | Persentase Keberhasilan | Kategori Kualitas | Deskripsi Operasional |
| :---: | :---: | :---: | :--- |
| **3,8 – 4,0** | 95% – 100% | **Sangat Bagus** | Sangat memuaskan, seluruh layanan berjalan optimal tanpa gangguan. |
| **3,0 – 3,79** | 75% – 94,9% | **Bagus** | Memuaskan, jaringan beroperasi normal dengan fluktuasi minimal. |
| **2,0 – 2,99** | 50% – 74,9% | **Sedang** | Cukup, ketersediaan layanan memadai namun terdapat penurunan performa. |
| **1,0 – 1,99** | 25% – 49,9% | **Buruk** | Kurang memuaskan, kualitas jaringan terdegradasi secara signifikan. |

#### Tabel 2.2. Klasifikasi Parameter Throughput Menurut Standar TIPHON

| Kecepatan Throughput | Nilai Indeks | Kategori TIPHON |
| :--- | :---: | :--- |
| $> 2,1\text{ Mbps}$ | **4** | Sangat Bagus |
| $1.200\text{ Kbps} - 2,1\text{ Mbps}$ | **3** | Bagus |
| $338\text{ Kbps} - 1.200\text{ Kbps}$ | **2** | Sedang |
| $0\text{ Kbps} - 388\text{ Kbps}$ | **1** | Buruk |

#### Tabel 2.3. Klasifikasi Parameter Packet Loss Menurut Standar TIPHON

| Persentase Packet Loss | Nilai Indeks | Kategori TIPHON |
| :--- | :---: | :--- |
| $0\% - 2\%$ | **4** | Sangat Bagus |
| $3\% - 14\%$ | **3** | Bagus |
| $15\% - 24\%$ | **2** | Sedang |
| $\ge 25\%$ | **1** | Buruk |

#### Tabel 2.4. Klasifikasi Parameter Delay (Waktu Tunda) Menurut Standar TIPHON

| Besar Latensi / Delay | Nilai Indeks | Kategori TIPHON |
| :--- | :---: | :--- |
| $< 150\text{ ms}$ | **4** | Sangat Bagus |
| $150\text{ ms s/d }300\text{ ms}$ | **3** | Bagus |
| $300\text{ ms s/d }450\text{ ms}$ | **2** | Sedang |
| $> 450\text{ ms}$ | **1** | Buruk |

#### Tabel 2.5. Klasifikasi Parameter Jitter (Variasi Waktu Tunda) Menurut Standar TIPHON

| Variasi Latensi / Jitter | Nilai Indeks | Kategori TIPHON |
| :--- | :---: | :--- |
| $0\text{ ms}$ | **4** | Sangat Bagus |
| $0\text{ ms s/d }75\text{ ms}$ | **3** | Bagus |
| $75\text{ ms s/d }125\text{ ms}$ | **2** | Sedang |
| $125\text{ ms s/d }225\text{ ms}$ / $> 225\text{ ms}$ | **1** | Buruk |

---

## BAB 3. Hasil Perhitungan, Klasifikasi & Analisis Sesi Pagi (09:06:27 – 09:14:53 WIB)

### 3.1. Komposisi Trafik & Distribusi Protokol Sesi Pagi
Pengujian Sesi Pagi berlangsung selama `505,982 detik` dengan total transaksi `4.930 paket` dan volume data sebesar `3.279.444 Bytes`. Aliran komunikasi didominasi oleh enkapsulasi UDP berbasis **QUIC (HTTP/3)** menuju infrastruktur *Edge* Cloudflare IPv6 (`2606:4700:3033::6815:53dc`).

#### Tabel 3.1. Distribusi Protokol Trafik Sesi Pagi

| Protokol | Jumlah Paket | Persentase Paket | Volume Data (Bytes) | Persentase Volume |
| :--- | :---: | :---: | :---: | :---: |
| **QUIC** | 4.395 | 89,15% | 3.174.598 | 96,80% |
| **TLSv1.3** | 285 | 5,78% | 65.718 | 2,00% |
| **TCP** | 250 | 5,07% | 39.128 | 1,19% |
| **Total** | **4.930** | **100,00%** | **3.279.444** | **100,00%** |

* **Arah Trafik:** *Downstream* (Server $\rightarrow$ Klien) mencakup `3.379 paket` (`2.678.616 Bytes`); *Upstream* (Klien $\rightarrow$ Server) mencakup `1.551 paket` (`600.828 Bytes`). Klien menggunakan alamat IPv6 `2402:8780:1052:6d17:34e7:93c4:af40:b783`.

### 3.2. Perhitungan Rinci Parameter QoS Sesi Pagi

1. **Throughput**
   $$\text{Throughput} = \frac{\text{Total Ukuran Data (bit)}}{\text{Durasi Pengamatan (detik)}} = \frac{3.279.444 \times 8}{505,982} = \frac{26.235.552}{505,982} = 51.850,7615\text{ bps} = \mathbf{51,851\text{ Kbps}}$$
   * **Klasifikasi TIPHON (Tabel 2.2):** Rentang $0 - 388\text{ Kbps}$ $\rightarrow$ **Indeks 1 (Buruk)**.

2. **Packet Loss**
   $$\text{Packet Loss} = \frac{\text{Paket Dikirim} - \text{Paket Diterima}}{\text{Paket Dikirim}} \times 100\% = \frac{4.930 - 4.930}{4.930} \times 100\% = \mathbf{0,00\%}$$
   * *Catatan CSV:* Tercatat 2 paket *TCP Retransmission* ($0,04\%$), namun tidak ada paket yang hilang di tingkat antarmuka.
   * **Klasifikasi TIPHON (Tabel 2.3):** Rentang $0\% - 2\%$ $\rightarrow$ **Indeks 4 (Sangat Bagus)**.

3. **Delay (Waktu Tunda / Latensi Rata-rata)**
   $$\text{Delay Rata-rata} = \frac{\text{Total Waktu Tunda Seluruh Paket}}{\text{Total Paket} - 1} = \frac{505,982058\text{ s}}{4.929} = 0,102654\text{ s} = \mathbf{102,65\text{ ms}}$$
   * *Statistik Deskriptif Delay:* Min = `0,000 ms`; P25 = `0,003 ms`; Median (P50) = `0,155 ms`; P75 = `1,488 ms`; Mean = `102,654 ms`; Max = `12.493,330 ms`; Standar Deviasi = `560,269 ms`.
   * **Klasifikasi TIPHON (Tabel 2.4):** Nilai $< 150\text{ ms}$ $\rightarrow$ **Indeks 4 (Sangat Bagus)**.

4. **Jitter (Variasi Waktu Tunda)**
   $$\text{Jitter Rata-rata} = \frac{\sum |\text{Delay}_i - \text{Delay}_{i-1}|}{N - 2} = \frac{989,526124\text{ s}}{4.928} = 0,200797\text{ s} = \mathbf{200,80\text{ ms}}$$
   * *Statistik Deskriptif Jitter:* Min = `0,000 ms`; P25 = `0,050 ms`; Median (P50) = `0,362 ms`; P75 = `19,421 ms`; Mean = `200,797 ms`; Max = `12.493,328 ms`; Standar Deviasi = `778,186 ms`.
   * **Klasifikasi TIPHON (Tabel 2.5):** Rentang $125 - 225\text{ ms}$ $\rightarrow$ **Indeks 1 (Buruk)**.

### 3.3. Sampel Data Paket Awal & Akhir Sesi Pagi

#### Tabel 3.2. Ekstraksi Deret Waktu Paket Sesi Pagi

| No. Paket | Waktu Baku (s) | Ukuran Paket (Bytes) | Delay Inter-Arrival (s) | Jitter Absolut (s) |
| :---: | :---: | :---: | :---: | :---: |
| 1 | 0,000000 | 1.294 | — | — |
| 2 | 0,000079 | 1.294 | 0,000079 | — |
| 3 | 0,025191 | 1.262 | 0,025112 | 0,025033 |
| 4 | 0,033638 | 1.262 | 0,008447 | 0,016665 |
| 5 | 0,035851 | 1.262 | 0,002213 | 0,006234 |
| ... | ... | ... | ... | ... |
| 4926 | 505,907222 | 89 | 0,001547 | 0,019539 |
| 4927 | 505,981379 | 1.262 | 0,074157 | 0,072610 |
| 4928 | 505,981390 | 101 | 0,000011 | 0,074145 |
| 4929 | 505,981391 | 92 | 0,000001 | 0,000010 |
| 4930 | 505,982058 | 106 | 0,000667 | 0,000666 |
| **Total** | **505,982058 s** | **3.279.444 Bytes** | **505,982058 s** | **989,526124 s** |
| **Rata-Rata** | **—** | **665,20 Bytes** | **0,102654 s (102,65 ms)** | **0,200797 s (200,80 ms)** |

### 3.4. Rekapitulasi Klasifikasi TIPHON Sesi Pagi

#### Tabel 3.3. Penilaian Kinerja QoS Sesi Pagi

| Parameter QoS | Nilai Terukur | Nilai Indeks TIPHON | Kategori TIPHON |
| :--- | :---: | :---: | :--- |
| **Throughput** | 51,85 Kbps | **1** | Buruk |
| **Packet Loss** | 0,00% | **4** | Sangat Bagus |
| **Delay** | 102,65 ms | **4** | Sangat Bagus |
| **Jitter** | 200,80 ms | **1** | Buruk |
| **Rata-Rata Indeks** | **—** | **2,50 (62,50%)** | **Sedang** |

### 3.5. Analisis Teknis Sesi Pagi
Sesi Pagi mencatatkan performa terbaik di antara keempat sesi pengamatan. Hal ini ditunjukkan oleh nilai *throughput* paling tinggi (`51,85 Kbps`), laju paket tertinggi (`9,74 pps`), *delay* terendah (`102,65 ms`; median `0,155 ms`), dan *jitter* terendah (`200,80 ms`). Jitter pada sesi pagi menjadi satu-satunya nilai variasi tunda yang berada pada batas rentang `125–225 ms` sebelum melonjak di atas `225 ms` pada sesi-sesi berikutnya. Beban saluran jaringan pada pukul `09:06 WIB` relatif renggang, sehingga responsivitas paket *streaming* QUIC berjalan sangat cepat meskipun nilai rata-rata *throughput* dipengaruhi oleh pola transmisi *bursty*.

---

## BAB 4. Hasil Perhitungan, Klasifikasi & Analisis Sesi Siang (13:30:03 – 13:41:26 WIB)

### 4.1. Komposisi Trafik & Distribusi Protokol Sesi Siang
Sesi Siang berlangsung selama `682,927 detik` dengan total `4.617 paket` dan ukuran total `2.919.422 Bytes`. Selain kanal IPv6 Cloudflare (`2606:4700:3035::ac43:b634`), terdapat segmen kecil trafik IPv4 lokal (`192.168.1.14` ke `172.67.182.52`).

#### Tabel 4.1. Distribusi Protokol Trafik Sesi Siang

| Protokol | Jumlah Paket | Persentase Paket | Volume Data (Bytes) | Persentase Volume |
| :--- | :---: | :---: | :---: | :---: |
| **QUIC** | 4.033 | 87,35% | 2.808.753 | 96,21% |
| **TLSv1.3** | 300 | 6,50% | 62.697 | 2,15% |
| **TCP** | 271 | 5,87% | 43.550 | 1,49% |
| **TLSv1.2** | 13 | 0,28% | 4.422 | 0,15% |
| **Total** | **4.617** | **100,00%** | **2.919.422** | **100,00%** |

* **Arah Trafik:** *Downstream* sebesar `3.056 paket` (`2.325.093 Bytes`) dan *Upstream* sebesar `1.561 paket` (`594.329 Bytes`). Sesi ini menjadi satu-satunya sesi yang merekam penggunaan protokol **TLSv1.2**.

### 4.2. Perhitungan Rinci Parameter QoS Sesi Siang

1. **Throughput**
   $$\text{Throughput} = \frac{2.919.422 \times 8}{682,927} = \frac{23.355.376}{682,927} = 34.198,9349\text{ bps} = \mathbf{34,199\text{ Kbps}}$$
   * **Klasifikasi TIPHON (Tabel 2.2):** **Indeks 1 (Buruk)**.

2. **Packet Loss**
   $$\text{Packet Loss} = \frac{4.617 - 4.617}{4.617} \times 100\% = \mathbf{0,00\%}$$
   * *Catatan CSV:* Terdapat 7 paket retransmisi TCP ($0,15\%$), namun seluruhnya berhasil terkirim ulang.
   * **Klasifikasi TIPHON (Tabel 2.3):** **Indeks 4 (Sangat Bagus)**.

3. **Delay (Waktu Tunda / Latensi Rata-rata)**
   $$\text{Delay Rata-rata} = \frac{682,927107\text{ s}}{4.616} = 0,147948\text{ s} = \mathbf{147,95\text{ ms}}$$
   * *Statistik Deskriptif Delay:* Min = `0,000 ms`; P25 = `0,005 ms`; Median (P50) = `0,229 ms`; P75 = `6,021 ms`; Mean = `147,948 ms`; Max = `13.250,095 ms`; Standar Deviasi = `682,711 ms`.
   * **Klasifikasi TIPHON (Tabel 2.4):** Nilai `147,95 ms` ($< 150\text{ ms}$) $\rightarrow$ **Indeks 4 (Sangat Bagus)**.

4. **Jitter (Variasi Waktu Tunda)**
   $$\text{Jitter Rata-rata} = \frac{1.338,874683\text{ s}}{4.615} = 0,290114\text{ s} = \mathbf{290,11\text{ ms}}$$
   * *Statistik Deskriptif Jitter:* Min = `0,000 ms`; P25 = `0,111 ms`; Median (P50) = `0,789 ms`; P75 = `27,498 ms`; Mean = `290,114 ms`; Max = `13.250,092 ms`; Standar Deviasi = `942,084 ms`.
   * **Klasifikasi TIPHON (Tabel 2.5):** Nilai `290,11 ms` ($> 225\text{ ms}$) $\rightarrow$ **Indeks 1 (Buruk)**.

### 4.3. Sampel Data Paket Awal & Akhir Sesi Siang

#### Tabel 4.2. Ekstraksi Deret Waktu Paket Sesi Siang

| No. Paket | Waktu Baku (s) | Ukuran Paket (Bytes) | Delay Inter-Arrival (s) | Jitter Absolut (s) |
| :---: | :---: | :---: | :---: | :---: |
| 1 | 0,000000 | 1.294 | — | — |
| 2 | 0,000136 | 1.294 | 0,000136 | — |
| 3 | 0,031986 | 1.262 | 0,031850 | 0,031713 |
| 4 | 0,032865 | 1.262 | 0,000879 | 0,030971 |
| 5 | 0,046783 | 1.262 | 0,013918 | 0,013039 |
| ... | ... | ... | ... | ... |
| 4613 | 681,876700 | 89 | 0,072594 | 0,072484 |
| 4614 | 681,884693 | 89 | 0,007993 | 0,064602 |
| 4615 | 682,926508 | 1.262 | 1,041816 | 1,033823 |
| 4616 | 682,926510 | 103 | 0,000001 | 1,041815 |
| 4617 | 682,927107 | 107 | 0,000598 | 0,000597 |
| **Total** | **682,927107 s** | **2.919.422 Bytes** | **682,927107 s** | **1.338,874683 s** |
| **Rata-Rata** | **—** | **632,32 Bytes** | **0,147948 s (147,95 ms)** | **0,290114 s (290,11 ms)** |

### 4.4. Rekapitulasi Klasifikasi TIPHON Sesi Siang

#### Tabel 4.3. Penilaian Kinerja QoS Sesi Siang

| Parameter QoS | Nilai Terukur | Nilai Indeks TIPHON | Kategori TIPHON |
| :--- | :---: | :---: | :--- |
| **Throughput** | 34,20 Kbps | **1** | Buruk |
| **Packet Loss** | 0,00% | **4** | Sangat Bagus |
| **Delay** | 147,95 ms | **4** | Sangat Bagus |
| **Jitter** | 290,11 ms | **1** | Buruk |
| **Rata-Rata Indeks** | **—** | **2,50 (62,50%)** | **Sedang** |

### 4.5. Analisis Teknis Sesi Siang
Sesi Siang (`13:30 WIB`) merupakan periode dengan beban degradasi jaringan tertinggi. *Throughput* menyusut hingga titik terendah (`34,20 Kbps`), laju transmisi paket turun ke `6,76 pps`, latensi membengkak mendekati batas atas kelas Sangat Bagus (`147,95 ms`), serta *jitter* memuncak hingga `290,11 ms`. Peningkatan latensi dan *jitter* pada jam kerja siang ini disebabkan oleh tingginya kongesti pada antrean router ISP tempat titik akses terhubung, yang juga memicu *fallback* koneksi ke IPv4 dan protokol TLSv1.2.

---

## BAB 5. Hasil Perhitungan, Klasifikasi & Analisis Sesi Sore (16:12:06 – 16:23:23 WIB)

### 5.1. Komposisi Trafik & Distribusi Protokol Sesi Sore
Sesi Sore merekam transaksi volume terbanyak selama `676,406 detik`, mencapai `5.782 paket` dengan total data `3.678.919 Bytes` antara IP klien `2402:8780:1052:6d17:75a6:b47:ce5b:6487` dan server Cloudflare.

#### Tabel 5.1. Distribusi Protokol Trafik Sesi Sore

| Protokol | Jumlah Paket | Persentase Paket | Volume Data (Bytes) | Persentase Volume |
| :--- | :---: | :---: | :---: | :---: |
| **QUIC** | 5.000 | 86,48% | 3.515.553 | 95,56% |
| **TLSv1.3** | 401 | 6,94% | 100.836 | 2,74% |
| **TCP** | 381 | 6,59% | 62.530 | 1,70% |
| **Total** | **5.782** | **100,00%** | **3.678.919** | **100,00%** |

* **Arah Trafik:** *Downstream* mencakup `3.848 paket` (`2.981.870 Bytes`) dan *Upstream* mencakup `1.934 paket` (`697.049 Bytes`).

### 5.2. Perhitungan Rinci Parameter QoS Sesi Sore

1. **Throughput**
   $$\text{Throughput} = \frac{3.678.919 \times 8}{676,406} = \frac{29.431.352}{676,406} = 43.511,3704\text{ bps} = \mathbf{43,511\text{ Kbps}}$$
   * **Klasifikasi TIPHON (Tabel 2.2):** **Indeks 1 (Buruk)**.

2. **Packet Loss**
   $$\text{Packet Loss} = \frac{5.782 - 5.782}{5.782} \times 100\% = \mathbf{0,00\%}$$
   * *Catatan CSV:* Log merekam 4 paket retransmisi TCP ($0,07\%$), tanpa adanya kehilangan paket permanen.
   * **Klasifikasi TIPHON (Tabel 2.3):** **Indeks 4 (Sangat Bagus)**.

3. **Delay (Waktu Tunda / Latensi Rata-rata)**
   $$\text{Delay Rata-rata} = \frac{676,406267\text{ s}}{5.781} = 0,117005\text{ s} = \mathbf{117,01\text{ ms}}$$
   * *Statistik Deskriptif Delay:* Min = `0,000 ms`; P25 = `0,004 ms`; Median (P50) = `0,221 ms`; P75 = `3,133 ms`; Mean = `117,005 ms`; Max = `14.243,789 ms`; Standar Deviasi = `635,415 ms`.
   * **Klasifikasi TIPHON (Tabel 2.4):** Nilai `117,01 ms` ($< 150\text{ ms}$) $\rightarrow$ **Indeks 4 (Sangat Bagus)**.

4. **Jitter (Variasi Waktu Tunda)**
   $$\text{Jitter Rata-rata} = \frac{1.329,131757\text{ s}}{5.780} = 0,229954\text{ s} = \mathbf{229,95\text{ ms}}$$
   * *Statistik Deskriptif Jitter:* Min = `0,000 ms`; P25 = `0,105 ms`; Median (P50) = `0,519 ms`; P75 = `19,171 ms`; Mean = `229,954 ms`; Max = `14.243,785 ms`; Standar Deviasi = `882,679 ms`.
   * **Klasifikasi TIPHON (Tabel 2.5):** Nilai `229,95 ms` ($> 225\text{ ms}$) $\rightarrow$ **Indeks 1 (Buruk)**.

### 5.3. Sampel Data Paket Awal & Akhir Sesi Sore

#### Tabel 5.2. Ekstraksi Deret Waktu Paket Sesi Sore

| No. Paket | Waktu Baku (s) | Ukuran Paket (Bytes) | Delay Inter-Arrival (s) | Jitter Absolut (s) |
| :---: | :---: | :---: | :---: | :---: |
| 1 | 0,000000 | 1.294 | — | — |
| 2 | 0,000088 | 1.294 | 0,000088 | — |
| 3 | 0,029909 | 1.262 | 0,029821 | 0,029732 |
| 4 | 0,031628 | 1.262 | 0,001719 | 0,028102 |
| 5 | 0,039332 | 1.262 | 0,007704 | 0,005986 |
| ... | ... | ... | ... | ... |
| 5778 | 674,175224 | 89 | 0,001066 | 0,026433 |
| 5779 | 676,405291 | 1.262 | 2,230068 | 2,229002 |
| 5780 | 676,405295 | 94 | 0,000004 | 2,230064 |
| 5781 | 676,405296 | 92 | 0,000001 | 0,000003 |
| 5782 | 676,406267 | 107 | 0,000971 | 0,000970 |
| **Total** | **676,406267 s** | **3.678.919 Bytes** | **676,406267 s** | **1.329,131757 s** |
| **Rata-Rata** | **—** | **636,27 Bytes** | **0,117005 s (117,01 ms)** | **0,229954 s (229,95 ms)** |

### 5.4. Rekapitulasi Klasifikasi TIPHON Sesi Sore

#### Tabel 5.3. Penilaian Kinerja QoS Sesi Sore

| Parameter QoS | Nilai Terukur | Nilai Indeks TIPHON | Kategori TIPHON |
| :--- | :---: | :---: | :--- |
| **Throughput** | 43,51 Kbps | **1** | Buruk |
| **Packet Loss** | 0,00% | **4** | Sangat Bagus |
| **Delay** | 117,01 ms | **4** | Sangat Bagus |
| **Jitter** | 229,95 ms | **1** | Buruk |
| **Rata-Rata Indeks** | **—** | **2,50 (62,50%)** | **Sedang** |

### 5.5. Analisis Teknis Sesi Sore
Kinerja pada Sesi Sore (`16:12 WIB`) menunjukkan pemulihan signifikan jika dibandingkan dengan Sesi Siang. *Delay* membaik sebesar $20,9\%$ menjadi `117,01 ms`, *jitter* turun ke `229,95 ms`, dan *throughput* meningkat ke `43,51 Kbps`. Meskipun tercatat jeda diam (*idle gap*) tunggal terbesar sepanjang pengujian (`14,24 detik`), responsivitas lalu lintas aktif tetap terjaga rapat dengan median latensi *inter-arrival* sebesar `0,221 ms`.

---

## BAB 6. Hasil Perhitungan, Klasifikasi & Analisis Sesi Malam (22:08:42 – 22:17:31 WIB)

### 6.1. Komposisi Trafik & Distribusi Protokol Sesi Malam
Sesi Malam berlangsung selama `529,112 detik` dengan total `4.413 paket` dan volume data `3.018.300 Bytes`.

#### Tabel 6.1. Distribusi Protokol Trafik Sesi Malam

| Protokol | Jumlah Paket | Persentase Paket | Volume Data (Bytes) | Persentase Volume |
| :--- | :---: | :---: | :---: | :---: |
| **QUIC** | 3.628 | 82,21% | 2.879.521 | 95,40% |
| **TLSv1.3** | 403 | 9,13% | 83.519 | 2,77% |
| **TCP** | 382 | 8,66% | 55.260 | 1,83% |
| **Total** | **4.413** | **100,00%** | **3.018.300** | **100,00%** |

* **Arah Trafik:** *Downstream* sebesar `2.963 paket` (`2.469.400 Bytes`) dan *Upstream* sebesar `1.450 paket` (`548.900 Bytes`).

### 6.2. Perhitungan Rinci Parameter QoS Sesi Malam

1. **Throughput**
   $$\text{Throughput} = \frac{3.018.300 \times 8}{529,112} = \frac{24.146.400}{529,112} = 45.635,7066\text{ bps} = \mathbf{45,636\text{ Kbps}}$$
   * **Klasifikasi TIPHON (Tabel 2.2):** **Indeks 1 (Buruk)**.

2. **Packet Loss**
   $$\text{Packet Loss} = \frac{4.413 - 4.413}{4.413} \times 100\% = \mathbf{0,00\%}$$
   * *Catatan CSV:* Log merekam 11 paket retransmisi TCP ($0,25\%$), namun tingkat kegagalan paket tetap $0,00\%$.
   * **Klasifikasi TIPHON (Tabel 2.3):** **Indeks 4 (Sangat Bagus)**.

3. **Delay (Waktu Tunda / Latensi Rata-rata)**
   $$\text{Delay Rata-rata} = \frac{529,111720\text{ s}}{4.412} = 0,119926\text{ s} = \mathbf{119,93\text{ ms}}$$
   * *Statistik Deskriptif Delay:* Min = `0,000 ms`; P25 = `0,003 ms`; Median (P50) = `0,186 ms`; P75 = `4,190 ms`; Mean = `119,926 ms`; Max = `13.644,292 ms`; Standar Deviasi = `622,910 ms`.
   * **Klasifikasi TIPHON (Tabel 2.4):** Nilai `119,93 ms` ($< 150\text{ ms}$) $\rightarrow$ **Indeks 4 (Sangat Bagus)**.

4. **Jitter (Variasi Waktu Tunda)**
   $$\text{Jitter Rata-rata} = \frac{1.029,268868\text{ s}}{4.411} = 0,233341\text{ s} = \mathbf{233,34\text{ ms}}$$
   * *Statistik Deskriptif Jitter:* Min = `0,000 ms`; P25 = `0,067 ms`; Median (P50) = `0,507 ms`; P75 = `22,253 ms`; Mean = `233,341 ms`; Max = `13.644,290 ms`; Standar Deviasi = `864,286 ms`.
   * **Klasifikasi TIPHON (Tabel 2.5):** Nilai `233,34 ms` ($> 225\text{ ms}$) $\rightarrow$ **Indeks 1 (Buruk)**.

### 6.3. Sampel Data Paket Awal & Akhir Sesi Malam

#### Tabel 6.2. Ekstraksi Deret Waktu Paket Sesi Malam

| No. Paket | Waktu Baku (s) | Ukuran Paket (Bytes) | Delay Inter-Arrival (s) | Jitter Absolut (s) |
| :---: | :---: | :---: | :---: | :---: |
| 1 | 0,000000 | 1.294 | — | — |
| 2 | 0,000083 | 1.294 | 0,000083 | — |
| 3 | 0,026302 | 1.262 | 0,026219 | 0,026135 |
| 4 | 0,033287 | 1.262 | 0,006985 | 0,019234 |
| 5 | 0,033489 | 1.294 | 0,000202 | 0,006783 |
| ... | ... | ... | ... | ... |
| 4409 | 528,409508 | 246 | 0,000113 | 0,077338 |
| 4410 | 528,434636 | 89 | 0,025128 | 0,025015 |
| 4411 | 529,110870 | 1.262 | 0,676235 | 0,651107 |
| 4412 | 529,110873 | 106 | 0,000003 | 0,676232 |
| 4413 | 529,111720 | 106 | 0,000846 | 0,000843 |
| **Total** | **529,111720 s** | **3.018.300 Bytes** | **529,111720 s** | **1.029,268868 s** |
| **Rata-Rata** | **—** | **683,96 Bytes** | **0,119926 s (119,93 ms)** | **0,233341 s (233,34 ms)** |

### 6.4. Rekapitulasi Klasifikasi TIPHON Sesi Malam

#### Tabel 6.3. Penilaian Kinerja QoS Sesi Malam

| Parameter QoS | Nilai Terukur | Nilai Indeks TIPHON | Kategori TIPHON |
| :--- | :---: | :---: | :--- |
| **Throughput** | 45,64 Kbps | **1** | Buruk |
| **Packet Loss** | 0,00% | **4** | Sangat Bagus |
| **Delay** | 119,93 ms | **4** | Sangat Bagus |
| **Jitter** | 233,34 ms | **1** | Buruk |
| **Rata-Rata Indeks** | **—** | **2,50 (62,50%)** | **Sedang** |

### 6.5. Analisis Teknis Sesi Malam
Sesi Malam (`22:08 WIB`) menunjukkan karakteristik performa yang stabil dan konsisten dengan Sesi Sore. *Throughput* rata-rata berada pada kisaran `45,64 Kbps` dengan rata-rata ukuran paket terbesar di antara semua sesi (`683,96 Bytes`). Proporsi gabungan protokol TCP dan TLSv1.3 meningkat menjadi `17,79%`. Meskipun terjadi 11 kali retransmisi TCP akibat fluktuasi kanal nirkabel malam hari, mekanisme pemulihan paket menjaga *Packet Loss* pada tingkat `0,00%` dan *Delay* pada nilai `119,93 ms`.

---

## BAB 7. Rekapitulasi Perbandingan Antar Waktu & Klasifikasi Akhir TIPHON

### 7.1. Tabel Perbandingan Komprehensif 4 Waktu Pengamatan & Klasifikasi Akhir

#### Tabel 7.1. Matriks Rekapitulasi Perbandingan QoS Seluruh Sesi dan Klasifikasi TIPHON

| Parameter QoS | Sesi Pagi (09:06) | Sesi Siang (13:30) | Sesi Sore (16:12) | Sesi Malam (22:08) | Rata-Rata Gabungan | Indeks Akhir | Kategori Akhir TIPHON |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| **Throughput** | 51,85 Kbps (1) | 34,20 Kbps (1) | 43,51 Kbps (1) | 45,64 Kbps (1) | **43,80 Kbps** | **1** | **Buruk** |
| **Packet Loss** | 0,00% (4) | 0,00% (4) | 0,00% (4) | 0,00% (4) | **0,00%** | **4** | **Sangat Bagus** |
| **Delay** | 102,65 ms (4) | 147,95 ms (4) | 117,01 ms (4) | 119,93 ms (4) | **121,88 ms** | **4** | **Sangat Bagus** |
| **Jitter** | 200,80 ms (1) | 290,11 ms (1) | 229,95 ms (1) | 233,34 ms (1) | **238,55 ms** | **1** | **Buruk** |
| **Rata-Rata Indeks** | **2,50 (Sedang)** | **2,50 (Sedang)** | **2,50 (Sedang)** | **2,50 (Sedang)** | **—** | **2,50 (62,50%)** | **SEDANG** |

* **Metrik Kumulatif Volume Total:** Pengujian selama total durasi `2.394,427 detik` menghasilkan perekaman `19.742 paket` dengan ukuran data `12.896.085 Bytes`, yang memberikan rata-rata *Throughput* tertimbang waktu sebesar `43,09 Kbps`.

### 7.2. Analisis Komparatif Antar Waktu & Kesimpulan Akhir

1. **Dinamika Kinerja Diurnal (Pagi vs Siang vs Sore vs Malam)**
   Urutan kualitas kinerja jaringan dari yang paling optimal hingga yang paling terbebani adalah:
   $$\text{Sesi Pagi} \rightarrow \text{Sesi Malam} \rightarrow \text{Sesi Sore} \rightarrow \text{Sesi Siang}$$
   Terjadi penurunan *throughput* sebesar **$34,0\%$** dan peningkatan *delay* sebesar **$44,1\%$** dari sesi pagi ke sesi siang. Kenaikan latensi dan penurunan laju bit pada siang hari mencerminkan lonjakan beban trafik pada infrastruktur penyedia jasa internet (ISP) selama jam sibuk.

2. **Stabilitas Keandalan & Dominasi Protokol Modern (QUIC / HTTP/3)**
   Di seluruh periode pengamatan, nilai *Packet Loss* konsisten sebesar `0,00%`, dan rata-rata *delay* di seluruh sesi berada di bawah ambang batas `150 ms` (kategori **Sangat Bagus**). Keandalan ini didukung oleh dominasi protokol modern **QUIC** berbasis UDP ($> 82\%$ paket dan $> 95\%$ volume data) melalui alamat IPv6 menuju server *Content Delivery Network* (CDN) Cloudflare (`2606:4700::`). Penggunaan QUIC mengurangi akumulasi latensi akibat *handshake* dan *head-of-line blocking* pada lapisan transpor.

3. **Interpretasi Teknis Kategori "Buruk" pada Throughput & Jitter**
   Meskipun *Throughput* (rata-rata `43,80 Kbps`) dan *Jitter* (rata-rata `238,55 ms`) tergolong ke dalam kategori **Buruk (Indeks 1)** menurut skala TIPHON, kondisi ini bukan disebabkan oleh kegagalan fisik jaringan (*link failure*), melainkan dipengaruhi oleh dua faktor metodologis:
   * **Capture Filter Spesifik:** Pengisolasian filter pengambilan paket pada 4 IP target membatasi pengukuran hanya pada lalu lintas aplikasi tertentu.
   * **Karakteristik *Intermittent* (*Bursty Traffic*):** Adanya jeda diam (*idle gap*) antar-permintaan paket yang mencapai `12,49` hingga `14,24 detik` menyebabkan pembagi waktu (*elapsed time*) membesar, sehingga menurunkan *bitrate* rata-rata matematis dan memperlebar variasi interval *inter-arrival*. Pada saat aliran data aktif berlangsung, median *delay inter-arrival* berada pada kisaran `0,155 ms` hingga `0,229 ms` dan median *jitter* berada pada kisaran `0,362 ms` hingga `0,789 ms`.

4. **Klasifikasi Akhir TIPHON**
   Berdasarkan pembobotan rata-rata dari keempat parameter pengujian di seluruh sesi ($\text{Throughput} = 1$, $\text{Packet Loss} = 4$, $\text{Delay} = 4$, $\text{Jitter} = 1$), diperoleh skor indeks kumulatif sebesar **2,50** (atau persentase tingkat keberhasilan **62,50%**). Dengan demikian, berdasarkan acuan kualitatif standar **TIPHON (ETSI TR 101 329)**, performa layanan jaringan internet pada lokasi pengujian secara keseluruhan berada dalam kategori **SEDANG**.