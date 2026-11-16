iaas@iaas-mgmt:~$ cd /home/iaas/projects/webportal-iaas

echo "========== VERSI AUTOMATION =========="
tofu version | head -n 3
ansible --version | head -n 3
incus version

echo
echo "========== LOKASI KONFIGURASI LARAVEL =========="
grep -nE \
  "'directory'|'playbook'|'inventory_group'|'lock_file'|'protected_workspaces'|'protected_containers'" \
  config/infrastructure.php

echo
echo "========== FILE OPENTOFU DAN ANSIBLE =========="

for DIR in \
  /home/iaas/imnehnet-iac/tofu \
  /home/iaas/imnehnet-iac/ansible \
  /home/iaas/imnehnet-ansible
do
  echo
  echo "DIRECTORY: $DIR"

  if [ -d "$DIR" ]; then
    find "$DIR" -maxdepth 4 -type f \
      \( -name '*.tf' -o -name '*.yml' \
         -o -name '*.yaml' -o -name 'ansible.cfg' \) \
      ! -name '*.tfvars' \
      ! -name '*vault*' \
      ! -name '*secret*' \
doneecho "Directory not found" \
========== VERSI AUTOMATION ==========
OpenTofu v1.12.6
on linux_amd64
ansible [core 2.16.3]
  config file = None
  configured module search path = ['/home/iaas/.ansible/plugins/modules', '/usr/share/ansible/plugins/modules']
Client version: 6.0.0
Server version: unreachable

========== LOKASI KONFIGURASI LARAVEL ==========
31:    'protected_workspaces' => ['default'],
33:    'protected_containers' => ['lab-tofu-test'],
45:        'directory' => env('TOFU_DIRECTORY', '/home/iaas/imnehnet-iac/tofu'),
47:        'lock_file' => env('TOFU_LOCK_FILE', storage_path('framework/tofu.lock')),
60:        'directory' => env('ANSIBLE_DIRECTORY', '/home/iaas/imnehnet-iac/ansible'),
66:        'playbook' => env('ANSIBLE_PLAYBOOK', 'playbooks/student-bootstrap.yml'),
67:        'inventory_group' => env('ANSIBLE_INVENTORY_GROUP', 'lab_env'),

========== FILE OPENTOFU DAN ANSIBLE ==========

DIRECTORY: /home/iaas/imnehnet-iac/tofu
/home/iaas/imnehnet-iac/tofu/main.tf
/home/iaas/imnehnet-iac/tofu/outputs.tf
/home/iaas/imnehnet-iac/tofu/provider.tf
/home/iaas/imnehnet-iac/tofu/variables.tf
/home/iaas/imnehnet-iac/tofu/version.tf

DIRECTORY: /home/iaas/imnehnet-iac/ansible
/home/iaas/imnehnet-iac/ansible/ansible.cfg
/home/iaas/imnehnet-iac/ansible/playbooks/container-check.yml
/home/iaas/imnehnet-iac/ansible/playbooks/controller-check.yml
/home/iaas/imnehnet-iac/ansible/playbooks/ctf-lab-pair.yml
/home/iaas/imnehnet-iac/ansible/playbooks/install-incus-wrapper.yml
/home/iaas/imnehnet-iac/ansible/playbooks/lab-env-check.yml
/home/iaas/imnehnet-iac/ansible/playbooks/student-bootstrap.yml

DIRECTORY: /home/iaas/imnehnet-ansible
Directory not found
