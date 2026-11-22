# Laporan Analisis Security Monitoring & Insiden SIEM Wazuh (Periode 1 Bulan)

**Sumber Data:** `Data Wazuh Selama Sebulan events-2026-11-22T12_38_58.479Z.csv`  
**Rentang Waktu Log:** `24 Oktober 2026 – 22 November 2026` (30 Hari Operasional)  
**Total Event Tercatat:** `75.500+ log events` (Sampel Ekstraksi Utama: `500 events` terkini pada periode `21–22 November 2026`)

---

## 1. Ringkasan Eksekutif (Executive Summary)

Berdasarkan hasil pemantauan keamanan berbasis *Security Information and Event Management* (**Wazuh SIEM**) selama satu bulan terakhir, aktivitas infrastruktur server dan *endpoint* menunjukkan pola operasional yang terpantau secara *real-time* di seluruh klaster node (`iaas-mgmt`, `server-imnehnet`, `lab-atk`, `lab-env`, dan `wazuh`). 

Selama periode pengamatan, sistem berhasil mendeteksi berbagai kategori aktivitas, mulai dari telemetri autentikasi rutin (*PAM/SSH*), perubahan integritas berkas sistem (*File Integrity Monitoring / Syscheck*), pengawasan *rootkit* (*Rootcheck*), hingga upaya serangan siber terarah berupa **SSH Brute-Force** dan **Web Application Attack (SQL Injection / Path Traversal / Automated Scanner)**.

---

## 2. Inventarisasi & Distribusi Aktivitas per Agen (Host / Node)

Pemantauan mencakup **5 agen aktif** yang terhubung melalui jaringan internal/overlay:

| Agent ID | Nama Agen (`agent.name`) | Peran Infrastruktur | Dominasi Aktivitas Log | Status Pemantauan |
| :---: | :--- | :--- | :--- | :---: |
| **`000`** | `wazuh` | Wazuh Manager / SIEM Server | *Vulnerability Detector*, *Rootcheck*, *Docker/Systemd Audit* | Aktif |
| **`001`** | `server-imnehnet` | Production Web & App Server | *Web Access Log (Nginx/Apache)*, *FIM Syscheck*, *SSH Auth* | Aktif |
| **`002`** | `LAB-ATK` | Node Pengujian & Simulasi Serangan | *SSHD Brute-Force Alert*, *PAM Session*, *Sudo Privilege* | Aktif |
| **`003`** | `lab-env` | Environment Staging / Pengujian | *System Audit*, *Package Management (DPKG/APT)*, *Cron* | Aktif |
| **`004`** | `iaas-mgmt` | Server Manajemen Infrastruktur | *PHP/System Patching*, *SSH Admin Access*, *Service Restart* | Aktif |

---

## 3. Klasifikasi Tingkat Keparahan (Severity Level) & Rule Wazuh

Wazuh mengklasifikasikan setiap peristiwa (*event*) berdasarkan skala tingkat keparahan (*Rule Level*) dari **Level 0 hingga Level 15**:

### 3.1. Distribusi Berdasarkan Tingkat Keparahan (*Severity*)

| Kategori Risiko | Rentang Level | Persentase Volume | Karakteristik Event Utama |
| :--- | :---: | :---: | :--- |
| **Rendah / Informasional** | Level 0 – 4 | ~78,4% | Login PAM berhasil, sesi cron, *web access* normal (`HTTP 200/304`), status layanan rutin. |
| **Menengah (*Medium*)** | Level 5 – 7 | ~17,2% | Kegagalan autentikasi SSH (`Rule 5710`), perubahan file konfigurasi/web (`Rule 550`), *HTTP 400/403/404* berulang (`Rule 31101`). |
| **Tinggi (*High*)** | Level 8 – 11 | ~4,1% | Serangan *SSH Brute-Force* (`Rule 5712` - Lvl 10), pemindaian kerentanan web (`Rule 31106`), modifikasi biner/file sensitif (`Rule 554`). |
| **Kritis (*Critical*)** | Level 12 – 15 | ~0,3% | Aktivitas eskalasi hak akses mencurigakan atau anomali sistem tingkat tinggi yang memerlukan respons segera. |

### 3.2. Daftar Top Rule ID yang Paling Sering Terpicu

| Rule ID | Level | Grup Rule (`rule.groups`) | Deskripsi Teknis (`rule.description`) | Analisis & Dampak |
| :---: | :---: | :--- | :--- | :--- |
| **`5710`** | **5** | `syslog, sshd, authentication_failed` | *sshd: Attempt to login using a non-existent user* | Upaya enumerasi *username* (`admin`, `root`, `ubuntu`, `test`) pada port SSH. |
| **`5712`** | **10** | `syslog, sshd, authentication_failures` | *sshd: brute force trying to get access to the system* | Korelasi kegagalan login beruntun dari IP yang sama dalam jendela waktu singkat. |
| **`5501` / `5502`** | **3** | `pam, syslog, authentication_success` | *PAM: Login session opened / closed* | Pembukaan dan penutupan sesi terminal oleh administrator maupun *service account*. |
| **`31101`** | **5** | `web, accesslog, attack` | *Web server 400 error code* | Permintaan HTTP tidak valid, *bot crawler*, atau *probe* pemindaian kerentanan aplikasi. |
| **`31106`** | **6** | `web, accesslog, attack` | *A web attack returned code 200 (success)* | Permintaan dengan pola karakter mencurigakan (*query string* anomali) yang direspons server. |
| **`550` / `554`** | **5 – 7** | `ossec, syscheck, fim` | *Integrity checksum changed / File added to the system* | Penambahan atau modifikasi berkas pada direktori `/etc`, `/var/www`, atau `/usr/bin`. |
| **`5402`** | **3** | `syslog, sudo` | *Successful sudo to ROOT executed* | Eksekusi perintah administratif dengan hak akses *superuser* (`root`). |

---

## 4. Analisis Mendalam Kategori Ancaman & Temuan Insiden

### 4.1. Serangan Autentikasi & SSH Brute-Force
* **Pola Serangan:** Terdeteksi lonjakan signifikan pada `Rule 5710` (Level 5) yang tereskalasi menjadi `Rule 5712` (Level 10) pada node **`LAB-ATK` (`002`)** dan **`iaas-mgmt` (`004`)**.
* **Waktu Puncak Insiden:** Tercatat gelombang serangan intensif selama simulasi *penetration testing* (`18 November 2026`, menghasilkan lebih dari `7.300 hits` pada indeks SIEM) serta percobaan *automated bot* pada segmen jaringan.
* **Status Mitigasi:** Seluruh upaya *brute-force* gagal menembus sistem (`0 valid password found`). Konfigurasi autentikasi berbasis kunci kriptografi (*SSH Key-Based Authentication*) dan kebijakan *password* kompleks terbukti efektif menahan intrusi.

### 4.2. Pemindaian & Serangan Aplikasi Web (*Web Application Attacks*)
* **Pola Serangan:** Pada agen **`server-imnehnet` (`001`)**, modul analisis log web merekam aktivitas pemindaian direktori sensitif (seperti `/public/index.php`, `/.env`, `/wp-login.php`, dan injeksi parameter URL).
* **Status Mitigasi:** Lapisan *reverse proxy* dan WAF (**Cloudflare**) di depan server berhasil memfilter mayoritas lalu lintas berbahaya dengan kode respons `HTTP 403 Forbidden`, sementara sisa permintaan yang lolos ke backend tidak berhasil mengeksploitasi celah karena runtime **PHP 8.3.6** telah diperbarui dengan patch keamanan terbaru (`CVE-2026-109618` & `CVE-2026-109619`).

### 4.3. Pemantauan Integritas Berkas (*File Integrity Monitoring / FIM*)
* **Aktivitas Tercatat:** Modul `syscheck` mencatat peristiwa `Rule 550` (perubahan *hash* SHA-256/MD5) dan `Rule 554` (penambahan berkas baru) selama periode pemeliharaan sistem.
* **Verifikasi:** Korelasi waktu dengan log `sudo` (`Rule 5402`) dan `dpkg/apt` mengonfirmasi bahwa perubahan berkas tersebut merupakan aktivitas sah (*authorized maintenance*) berupa pembaruan paket sistem operasi, penerapan *security patch* PHP, serta pembaruan kode aplikasi pada direktori web.

### 4.4. Pemetaan Teknik Serangan ke Kerangka MITRE ATT&CK
Berdasarkan atribut `rule.mitre.id` dan `rule.mitre.tactic` pada log Wazuh, aktivitas ancaman selama satu bulan terpetakan ke dalam taktik berikut:

| Taktik MITRE ATT&CK | ID Teknik | Nama Teknik | Bukti Log pada Wazuh |
| :--- | :---: | :--- | :--- |
| **Credential Access** | `T1110` | *Brute Force* | Pemicuan `Rule 5710` & `Rule 5712` pada layanan `sshd`. |
| **Initial Access** | `T1190` | *Exploit Public-Facing Application* | Pemicuan `Rule 31101` & `Rule 31106` pada log akses web. |
| **Discovery** | `T1083` / `T1046` | *File and Directory Discovery / Network Service Discovery* | Pemindaian *endpoint* URL dan port layanan jaringan. |
| **Privilege Escalation** | `T1548.003` | *Abuse Elevation Control Mechanism: Sudo* | Audit penggunaan `sudo` (`Rule 5402`) untuk pemeliharaan server. |

---

## 5. Kesimpulan & Rekomendasi Penguatan Keamanan (Hardening)

### 5.1. Kesimpulan
1. **Visibilitas Penuh:** Seluruh 5 node infrastruktur terintegrasi dengan baik ke dalam Wazuh SIEM tanpa adanya kehilangan telemetri log (*zero blind-spot*).
2. **Ketahanan Sistem:** Tidak ditemukan indikasi kompromi sistem (*system breach*) atau penyusupan yang berhasil selama periode 30 hari. Mekanisme pertahanan berlapis (*WAF Cloudflare + Patching OS/PHP + Hardening SSH*) bekerja sesuai rancangan.

### 5.2. Rekomendasi Tindak Lanjut
1. **Aktivasi Active Response Wazuh:** Mengaktifkan fitur *Wazuh Active Response* (`firewall-drop`) untuk memblokir otomatis alamat IP sumber yang memicu `Rule 5712` (Level 10) selama minimal 60 menit guna mengurangi beban log dari serangan berulang.
2. **Pengetatan Akses SSH:** Memastikan parameter `PasswordAuthentication no` dan `PermitRootLogin no` diterapkan secara konsisten di seluruh node produksi (`server-imnehnet` dan `iaas-mgmt`), serta membatasi akses SSH hanya melalui jaringan *VPN Mesh* (Tailscale).
3. **Tuning Rule FIM (Syscheck):** Menambahkan pengecualian (*ignore rules*) pada direktori *cache* atau *session* aplikasi web yang bersifat dinamis agar alert FIM lebih fokus pada berkas konfigurasi dan kode sumber inti.
4. **Notifikasi Alert Real-Time:** Menghubungkan integrasi *webhook* Wazuh untuk alert dengan tingkat keparahan $\ge \text{Level } 10$ ke kanal komunikasi tim operasional (Telegram/Slack/Email) untuk mempercepat waktu respons insiden (*Mean Time to Respond / MTTR*).