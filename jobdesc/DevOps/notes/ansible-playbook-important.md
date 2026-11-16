# ADA 6 PLAYBOOK PENTING ANSIBLE IMNEHNET

## PLAYBOOK
1. controller-check.yml
Fungsi: Memeriksa hostname, sistem operasi, IP, dan Python pada IAAS-MGMT 
Kategori: Diagnostic

2. lab-env-check.yml
Fungsi: Memeriksa keberadaan dan konfigurasi Incus pada lab-env
Kategori: Diagnostic

3. container-check.yml
Fungsi: Memeriksa konektivitas, hostname, IP, dan uptime pada container
Kategori: Diagnostic

4. install-incus-wrapper.yml
Fungsi: Menginstal wrapper Incus pada controller
Kategori: Setup Infrastructure

5. student-bootstrap.yml
Fungsi: Menginstal dan konfigurasi student bootstrap pada controller
Kategori: Provisioning

6. ctf-lab-pair.yml
Fungsi: Menyiapkan container attacker dan defender serta layanan CTF
Kategori: Provisioning CTF

Playbook Ansible: 6
File OpenTofu: 5 (.tf)
Laravel automation services: 2
