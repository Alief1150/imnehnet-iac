# Ansible

Ansible mengonfigurasi guest dan container setelah provisioning OpenTofu. Folder
ini merupakan source utama playbook IMNEHNET; dokumentasi `jobdesc/` hanya
menautkannya dan tidak menyimpan salinan kedua.

## Controller dan inventory

- [`ansible.cfg`](ansible.cfg) menetapkan inventory lokal, roles path, host-key checking, timeout, pipelining, dan SSH multiplexing.
- [`inventory/hosts.ini.example`](inventory/hosts.ini.example) adalah template tanpa credential untuk controller, `LAB-ENV`, `LAB-ATK`, dan student container.
- `inventory/hosts.ini` bersifat machine-local dan diabaikan Git.
- Private key, become password, vault password, token, dan raw inventory rahasia tidak boleh disimpan di repository.

Siapkan inventory lokal tanpa mengubah template:

```bash
cp ansible/inventory/hosts.ini.example ansible/inventory/hosts.ini
```

Isi placeholder hanya pada salinan lokal. Jangan menambahkan password atau private
key ke inventory.

## Enam playbook provisioning dan pemeriksaan

- [`playbooks/controller-check.yml`](playbooks/controller-check.yml) — pemeriksaan fakta controller dan Python.
- [`playbooks/lab-env-check.yml`](playbooks/lab-env-check.yml) — pemeriksaan `LAB-ENV` dan instalasi Incus.
- [`playbooks/container-check.yml`](playbooks/container-check.yml) — pemeriksaan konektivitas, identitas, alamat, dan uptime container pada `student_labs`.
- [`playbooks/student-bootstrap.yml`](playbooks/student-bootstrap.yml) — bootstrap user dan paket dasar setelah container tersedia.
- [`playbooks/install-incus-wrapper.yml`](playbooks/install-incus-wrapper.yml) — pemasangan wrapper Incus terbatas serta validasi sudoers.
- [`playbooks/ctf-lab-pair.yml`](playbooks/ctf-lab-pair.yml) — provisioning pasangan victim/attacker Guided CTF dengan input eksplisit.

Inventory aktif yang diaudit belum memiliki grup `lab_atk` dan `student_labs`.
Akibatnya syntax-check playbook terkait menghasilkan warning sampai inventory lokal
tersebut dilengkapi dan diverifikasi.

## Playbook operasional lain

Repository juga memuat playbook/role terpisah untuk distributed audit reporting dan
perbaikan keyring Tailscale yang telah terdokumentasi sebelumnya. Source tersebut
tidak digantikan oleh enam playbook di atas.

## Validasi aman

```bash
ansible-inventory --graph
ansible all -m ansible.builtin.ping
for playbook in ansible/playbooks/*.yml; do
  ansible-playbook --syntax-check "$playbook"
done
```

Untuk playbook yang dapat mengubah host/container, jalankan `--check --diff` satu
target per kali dan review `PLAY RECAP` sebelum apply. `ctf-lab-pair.yml` memakai
command yang memprovisioning container dan tidak boleh dijalankan sebagai bagian
dari audit read-only.
