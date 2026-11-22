# Pengujian 02 — Micro-Segmentation dan Isolasi Resource

## 1. Tujuan Pengujian

Pengujian ini dilakukan untuk memastikan penerapan micro-segmentation pada lingkungan praktikum IMNEHNET berjalan sesuai dengan kebijakan akses yang telah ditentukan. Pengujian mencakup komunikasi antar-container, pembatasan akses ke management, serta akses resource melalui portal mahasiswa.

## 2. Lingkup Pengujian

Pengujian difokuskan pada enam skenario untuk memastikan setiap kelompok resource memiliki batasan akses sesuai kebijakan jaringan.

| ID | Skenario Pengujian | Ekspektasi |
|---|---|---|
| P2-01 | Pengujian akses Attacker ke Victim yang diizinkan | ALLOW |
| P2-02 | Pengujian pembatasan akses Attacker ke Victim lain | DENY |
| P2-03 | Pengujian isolasi antar-container Attacker | DENY |
| P2-04 | Pengujian isolasi antar-container Defender | DENY |
| P2-05 | Pengujian pembatasan akses Student Lab ke Management | DENY |
| P2-06 | Pengujian akses Portal/Worker ke Resource | ALLOW |

## 3. Tools yang Digunakan

| Tools | Fungsi |
|---|---|
| Incus | Mengelola container dan konfigurasi jaringan container. |
| `curl` | Menguji koneksi HTTP antar-container. |
| `ping` | Menguji konektivitas jaringan menggunakan ICMP. |
| `nc` (Netcat) | Menguji koneksi ke port layanan tertentu. |
| Portal IMNEHNET | Mengakses terminal container melalui akun mahasiswa. |
| Terminal Linux | Menjalankan perintah pengujian dan memeriksa informasi container. |

---

## 4. Hasil Pengujian

### P2-01 — Pengujian Akses Attacker ke Victim yang Diizinkan

**Tujuan:** Memastikan container attacker dapat mengakses container victim yang telah diberikan izin.

**Skenario Pengujian:**
- Sumber: `attacker-user-33` (`10.30.0.108`)
- Tujuan: `victim-user-33` (`10.20.0.108`)
- Layanan yang diuji: HTTP TCP port `80` dan ICMP
- Ekspektasi: **ALLOW**

**Sintaks Pengujian**

Pengujian HTTP:

```bash
curl --connect-timeout 3 -v http://10.20.0.108:80/
```

Pengujian konektivitas:

```bash
ping -c 3 10.20.0.108
```

**Hasil Pengujian:**

1. Pengujian HTTP menggunakan `curl` berhasil mendapatkan respons `HTTP 200 OK`.
2. Pengujian konektivitas menggunakan `ping` berhasil menerima 3 paket balasan tanpa packet loss.

**Kesimpulan:** **PASS**. Container attacker berhasil mengakses victim sesuai kebijakan yang diizinkan.

**Bukti Pengujian:**

![P2-01 - Hasil pengujian HTTP](../images/P4-01-Curl-Allow.png)

![P2-01 - Hasil pengujian ping](../images/P4-01-Ping-Allow.png)

---

### P2-02 — Pengujian Pembatasan Akses Attacker ke Victim Lain

**Tujuan:** Memastikan container attacker tidak dapat mengakses container victim yang bukan menjadi target izinnya.

**Skenario Pengujian:**
- Sumber: `attacker-user-33` (`10.30.0.108`)
- Tujuan: `victim-user-29` (`10.20.0.110`)
- Layanan yang diuji: HTTP TCP port `80` dan ICMP
- Ekspektasi: **DENY**

**Sintaks Pengujian**

Pengujian HTTP:

```bash
curl --connect-timeout 3 -v http://10.20.0.110:80/
```

Pengujian konektivitas:

```bash
ping -c 3 10.20.0.110
```

**Hasil Pengujian:**

1. Pengujian HTTP menggunakan `curl` menghasilkan timeout saat mencoba mengakses port `80` pada container tujuan.
2. Pengujian konektivitas menggunakan `ping` menunjukkan 100% packet loss.

**Kesimpulan:** **PASS**. Akses HTTP dan ICMP dari attacker menuju victim lain berhasil diblokir.

**Bukti Pengujian:**

![P2-02 - Hasil pengujian HTTP](../images/P4-02-Curl-Deny.png)

![P2-02 - Hasil pengujian ping](../images/P4-02-Ping-Deny.png)

---

### P2-03 — Pengujian Isolasi Antar-Container Attacker

**Tujuan:** Memastikan container attacker tidak dapat saling mengakses tanpa izin.

**Skenario Pengujian:**
- Sumber: `attacker-user-33` (`10.30.0.108`)
- Tujuan: `attacker-user-25` (`10.30.0.109`)
- Layanan yang diuji: HTTP TCP port `80` dan ICMP
- Ekspektasi: **DENY**

**Sintaks Pengujian**

Pengujian HTTP:

```bash
curl --connect-timeout 3 -v http://10.30.0.109:80/
```

Pengujian konektivitas:

```bash
ping -c 3 10.30.0.109
```

**Hasil Pengujian:**

1. Pengujian HTTP menggunakan `curl` menghasilkan timeout saat mencoba mengakses port `80` pada container tujuan.
2. Pengujian konektivitas menggunakan `ping` menunjukkan 100% packet loss.

**Kesimpulan:** **PASS**. Akses HTTP dan ICMP antar-container attacker berhasil diblokir.

**Bukti Pengujian:**

![P2-03 - Hasil pengujian HTTP](../images/P4-03-Curl-Deny.png)

![P2-03 - Hasil pengujian ping](../images/P4-03-Ping-Deny.png)

---

### P2-04 — Pengujian Isolasi Antar-Container Defender

**Tujuan:** Memastikan container defender tidak dapat saling mengakses tanpa izin kebijakan jaringan.

**Skenario Pengujian:**
- Sumber: `victim-user-33` (`10.20.0.108`)
- Tujuan: `victim-user-25` (`10.20.0.109`)
- Layanan yang diuji: HTTP TCP port `80` dan ICMP
- Ekspektasi: **DENY**

**Sintaks Pengujian**

Pengujian HTTP:

```bash
curl --connect-timeout 3 -v http://10.20.0.109:80/
```

Pengujian konektivitas:

```bash
ping -c 3 10.20.0.109
```

**Hasil Pengujian:**

1. Pengujian HTTP menggunakan `curl` menghasilkan timeout saat mencoba mengakses port `80` pada container tujuan.
2. Pengujian konektivitas menggunakan `ping` menunjukkan 100% packet loss.

**Kesimpulan:** **PASS**. Akses HTTP dan ICMP dari Victim A menuju Victim B berhasil diblokir sesuai kebijakan isolasi jaringan.

**Bukti Pengujian:**

![P2-04 - Hasil pengujian HTTP](../images/P4-04-Curl-Deny.png)

![P2-04 - Hasil pengujian ping](../images/P4-04-Ping-Deny.png)

---

### P2-05 — Pengujian Pembatasan Akses Student Lab ke Management

**Tujuan:** Memastikan container student lab tidak dapat mengakses layanan administrasi pada VM IAAS-MGMT.

**Skenario Pengujian:**
- Sumber: `attacker-user-25` (`10.30.0.109`)
- Tujuan: `IAAS-MGMT` (`192.168.100.10`)
- Layanan yang diuji: SSH TCP port `22`
- Ekspektasi: **DENY**

**Sintaks Pengujian**

```bash
nc -vz -w 3 192.168.100.10 22
```

**Hasil Pengujian:**

Hasil pengujian menunjukkan koneksi mengalami timeout sehingga container attacker tidak berhasil mengakses layanan SSH pada IAAS-MGMT.

**Kesimpulan:** **PASS**. Akses SSH dari container student lab menuju IAAS-MGMT berhasil diblokir sesuai kebijakan isolasi jaringan.

**Bukti Pengujian:**

![P2-05 - Hasil pengujian SSH](../images/P4-05-SSH-Deny.png)

---

### P2-06 — Pengujian Akses Portal/Worker ke Resource

**Tujuan:** Memastikan mahasiswa dapat mengakses resource praktikum melalui jalur resmi yang disediakan portal.

**Skenario Pengujian:**
- Sumber: Portal mahasiswa
- Resource: Container Attacker dan Victim milik mahasiswa
- Ekspektasi: **ALLOW** melalui portal

**Sintaks Pengujian**

Pemeriksaan identitas container melalui terminal portal:

```bash
hostname
```

Pemeriksaan alamat IP container:

```bash
ip a
```

**Hasil Pengujian:**

Mahasiswa berhasil membuka terminal Attacker dan Victim melalui menu Terminal pada portal. Hasil pemeriksaan menunjukkan terminal terhubung ke container yang sesuai, salah satunya `attacker-user-33` dengan IP `10.30.0.108`.

Portal juga hanya menampilkan resource yang dimiliki oleh akun mahasiswa tersebut.

**Kesimpulan:** **PASS**. Portal berhasil menyediakan akses ke resource praktikum sesuai akun mahasiswa.

**Bukti Pengujian:**

![P2-06 - Halaman terminal portal mahasiswa](../images/P4-06-Portal-Terminal.png)

![P2-06 - Identitas dan IP container](../images/P4-06-Container-Identity.png)

---

## 5. Kesimpulan Pengujian

Berdasarkan hasil pengujian P2-01 hingga P2-06, penerapan micro-segmentation pada lingkungan praktikum IMNEHNET telah berjalan sesuai dengan kebijakan akses yang ditentukan. Setiap container dapat berkomunikasi sesuai izin, akses yang tidak diizinkan berhasil dibatasi, serta portal berhasil menyediakan akses ke resource praktikum sesuai akun mahasiswa. Seluruh skenario pengujian memperoleh hasil **PASS**.
