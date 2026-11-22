## Dokumentasi Pengujian Security Monitoring & Penetration Testing

### 1. Network Reconnaissance & Port Scanning (Nmap)
* **Tujuan:** Mengidentifikasi permukaan serangan (*attack surface*), port yang terbuka, serta versi layanan yang berjalan pada domain target `imnehnet.web.id`.
* **Waktu Pengujian:** `2026-11-16 20:07 WIB`
* **Perintah Eksekusi:**
  ```bash
  nmap -sV imnehnet.web.id
  ```
* **Hasil Pengamatan:**
  * **Target Resolved:** `104.21.83.220` (dengan alamat alternatif IPv4 `172.67.182.52` dan IPv6 `2606:4700:3033::6815:53dc`, `2606:4700:3035::ac43:b634`).
  * **Status Port:** `996 filtered tcp ports (no-response)`.
  * **Port Terbuka:**

    | Port | State | Service | Version |
    | :--- | :--- | :--- | :--- |
    | `80/tcp` | open | `http` | Cloudflare http proxy |
    | `443/tcp` | open | `ssl/http` | Cloudflare http proxy |
    | `8080/tcp` | open | `http` | Cloudflare http proxy |
    | `8443/tcp` | open | `ssl/http` | Cloudflare http proxy |

* **Analisis Keamanan:** Alamat IP asli (*origin server*) berhasil disembunyikan di balik *reverse proxy* **Cloudflare**. Hanya port standar web HTTP/HTTPS proxy yang terekspos ke publik, sementara layanan internal seperti SSH atau database tidak dapat diakses langsung dari internet.

---

### 2. Pengujian Kerentanan Aplikasi Web (PHP2Shell Scanner)
* **Tujuan:** Menguji potensi kerentanan *Remote Code Execution* (RCE) / PHP2Shell pada endpoint aplikasi web target.
* **Perintah Eksekusi:**
  ```bash
  python3 scanner.py -u https://imnehnet.web.id/public/index.php
  python3 scanner.py -u https://imnehnet.web.id
  ```
* **Hasil Pengamatan:**
  * **Konfigurasi Scanner:** `10 thread(s)`, `Timeout: 60s`, `SAPI rules: auto`.
  * **Respons Target:** Kedua pengujian menghasilkan status `[INCONCLUSIVE]` dengan kode respons HTTP **`Status: 403`** (`Vulnerable: 0`, `Inconclusive/errors: 1`).
* **Analisis Keamanan:** *Web Application Firewall* (WAF) Cloudflare secara aktif mendeteksi dan memblokir *probe* pemindaian eksploitasi sebelum permintaan mencapai server backend (ditandai dengan respons HTTP `403 Forbidden`).

---

### 3. Verifikasi Patching Sistem & Runtime PHP pada Server
* **Tujuan:** Memastikan ketahanan *server-side* (*defense-in-depth*) terhadap kerentanan PHP2Shell meskipun lapisan WAF dilewati.
* **Target Host:** `iaas-mgmt`
* **Perintah Eksekusi:**
  ```bash
  php --version
  apt changelog php8.3-cli | grep -E "CVE-2026-109618|CVE-2026-109619"
  dpkg -l | grep php8.3
  ```
* **Hasil Pengamatan:**
  * **Versi Runtime:** `PHP 8.3.6 (cli) (built: Oct 19 2026 15:16:58) (NTS)` dengan `Zend Engine v4.3.6` dan `Zend OPcache v8.3.6`.
  * **Status Patching Paket:** Seluruh paket PHP (`libapache2-mod-php8.3`, `php8.3-cli`, `php8.3-fpm`, `php8.3-common`, dll.) berada pada versi `8.3.6-0ubuntu0.24.04.13`.
  * **Changelog Keamanan:** Tercatat patch resmi `debian/patches/CVE-2026-109618_109619.patch: Avoid state corruption via CVE-2026-109618 / CVE-2026-109619`.
* **Analisis Keamanan:** Sistem server telah ter-*patch* secara resmi terhadap celah keamanan PHP2Shell (`CVE-2026-109618` dan `CVE-2026-109619`), sehingga aman baik di level perimeter (WAF) maupun di level *host/runtime*.

---

### 4. Simulasi Serangan Brute-Force SSH & Deteksi SIEM Wazuh
* **Tujuan:** Menguji efektivitas deteksi insiden dan pengumpulan log pada sistem *Security Information and Event Management* (**Wazuh SIEM**) terhadap serangan autentikasi SSH.
* **Waktu Pengujian:** `2026-11-18 11:37:58 – 11:39:17 WIB`
* **Perintah Eksekusi (melalui jaringan VPN mesh Tailscale):**
  ```bash
  hydra -l users.txt -P pass.txt -t 4 ssh://100.77.79.18
  hydra -l users.txt -P pass.txt -t 4 ssh://100.108.204.76
  ```
* **Hasil Eksekusi & Deteksi Wazuh:**
  * **Hasil Hydra:** Melakukan 21 percobaan login per target dengan hasil `0 valid password found`.
  * **Alert pada Dashboard Wazuh SIEM (Agent `002` / `LAB-ATK`):**

    | Rule ID | Rule Level | Deskripsi Alert Wazuh |
    | :--- | :---: | :--- |
    | **`5712`** | **10** | `sshd: brute force trying to get access to the system` |
    | **`5710`** | **5** | `sshd: Attempt to login using a non-existent user` |
    | **`5715`** | **3** | `sshd: authentication success` |
    | **`5501` / `5502`** | **3** | `PAM: Login session opened / closed` |

* **Analisis Keamanan:** Agen Wazuh pada node target berhasil mengirimkan telemetri log autentikasi secara *real-time* ke server SIEM. Upaya *brute-force* SSH terdeteksi secara akurat dengan peningkatan eskalasi tingkat keparahan dari **Level 5** (percobaan user tidak valid, Rule `5710`) menjadi **Level 10** (serangan *brute-force* terkonfirmasi, Rule `5712`).

---

### 5. Matriks Rekapitulasi Pengujian Keamanan

| Skenario Pengujian | Tool / Metode | Target | Hasil Teknis | Status Pertahanan |
| :--- | :--- | :--- | :--- | :--- |
| **Port & Service Scan** | `nmap -sV` | `imnehnet.web.id` | Hanya port proxy Cloudflare (`80, 443, 8080, 8443`) terbuka; IP origin tersembunyi. | **Aman** |
| **Web Exploit Scan** | `php2shell-scanner` | `https://imnehnet.web.id` | Diblokir dengan HTTP `403 Forbidden` (`INCONCLUSIVE`, `0 Vulnerable`). | **Terproteksi (WAF)** |
| **Patch Verification** | `apt changelog` & `dpkg` | Server `iaas-mgmt` | PHP `8.3.6-0ubuntu0.24.04.13` memuat patch `CVE-2026-109618` & `CVE-2026-109619`. | **Ter-patch** |
| **SSH Brute-Force & SIEM** | `THC-Hydra` & `Wazuh` | `100.77.79.18` & `100.108.204.76` | 0 password ditemukan; terdeteksi otomatis oleh Wazuh (`Rule 5710` Lvl 5 & `Rule 5712` Lvl 10). | **Terdeteksi (SIEM Aktif)** |