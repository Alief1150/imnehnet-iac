iaas@iaas-mgmt:~/imnehnet-iac/tofu$ cd /home/iaas/imnehnet-iac/ansible

echo "========== PLAYBOOK DAN TASK =========="

find . -maxdepth 4 -type f \
  \( -name '*.yml' -o -name '*.yaml' \) \
  ! -name '*vault*' \
  ! -name '*secret*' \
  -print0 | xargs -0 -r grep -nE \
  '^[[:space:]]*(- name:|hosts:|roles:|tasks:|become:)'
========== PLAYBOOK DAN TASK ==========
./playbooks/install-incus-wrapper.yml:2:- name: Install restricted Incus automation wrapper
./playbooks/install-incus-wrapper.yml:3:  hosts: lab_env
./playbooks/install-incus-wrapper.yml:4:  become: true
./playbooks/install-incus-wrapper.yml:7:  tasks:
./playbooks/install-incus-wrapper.yml:8:    - name: Install IMNEHNET Incus wrapper
./playbooks/install-incus-wrapper.yml:73:    - name: Allow lab user to run only IMNEHNET wrapper without password
./playbooks/install-incus-wrapper.yml:82:    - name: Validate sudoers
./playbooks/lab-env-check.yml:2:- name: Check LAB-ENV
./playbooks/lab-env-check.yml:3:  hosts: lab_env
./playbooks/lab-env-check.yml:5:  become: true
./playbooks/lab-env-check.yml:7:  tasks:
./playbooks/lab-env-check.yml:8:    - name: Test connectivity
./playbooks/lab-env-check.yml:11:    - name: Display hostname
./playbooks/lab-env-check.yml:15:    - name: Display operating system
./playbooks/lab-env-check.yml:21:    - name: Check Incus installation
./playbooks/lab-env-check.yml:27:    - name: Display Incus version
./playbooks/student-bootstrap.yml:2:- name: Bootstrap student environment
./playbooks/student-bootstrap.yml:3:  hosts: lab_env
./playbooks/student-bootstrap.yml:16:  tasks:
./playbooks/student-bootstrap.yml:17:    - name: Verify restricted Incus wrapper is callable without a password
./playbooks/student-bootstrap.yml:22:    - name: Verify student container exists
./playbooks/student-bootstrap.yml:27:    - name: Wait until container is running
./playbooks/student-bootstrap.yml:36:    - name: Create student user if not exists
./playbooks/student-bootstrap.yml:46:    - name: Install base packages
./playbooks/student-bootstrap.yml:63:    - name: Add student user to sudo group
./playbooks/student-bootstrap.yml:70:    - name: Verify environment
./playbooks/student-bootstrap.yml:80:    - name: Show environment status
./playbooks/controller-check.yml:2:- name: Check IAAS-MGMT Ansible Controller
./playbooks/controller-check.yml:3:  hosts: controller
./playbooks/controller-check.yml:6:  tasks:
./playbooks/controller-check.yml:7:    - name: Display hostname
./playbooks/controller-check.yml:11:    - name: Display distribution
./playbooks/controller-check.yml:17:    - name: Display IP information
./playbooks/controller-check.yml:21:    - name: Verify Python
./playbooks/controller-check.yml:27:    - name: Display Python version
./playbooks/container-check.yml:2:- name: Check IMNEHNET Student Containers
./playbooks/container-check.yml:3:  hosts: student_labs
./playbooks/container-check.yml:6:  tasks:
./playbooks/container-check.yml:7:    - name: Test Ansible connectivity
./playbooks/container-check.yml:10:    - name: Display container hostname
./playbooks/container-check.yml:14:    - name: Display container IP
./playbooks/container-check.yml:18:    - name: Check uptime
./playbooks/container-check.yml:24:    - name: Display uptime
./playbooks/ctf-lab-pair.yml:21:- name: Provision CTF victim on lab-env
./playbooks/ctf-lab-pair.yml:22:  hosts: lab_env
./playbooks/ctf-lab-pair.yml:26:  tasks:
./playbooks/ctf-lab-pair.yml:27:    - name: Verify lab_id is set
./playbooks/ctf-lab-pair.yml:34:    - name: Launch victim container with static IP
./playbooks/ctf-lab-pair.yml:39:    - name: Wait until container is running
./playbooks/ctf-lab-pair.yml:48:    - name: Create CTF web root
./playbooks/ctf-lab-pair.yml:53:    - name: Deploy CTF index page
./playbooks/ctf-lab-pair.yml:59:    - name: Deploy robots.txt hint
./playbooks/ctf-lab-pair.yml:66:    - name: Deploy flag page
./playbooks/ctf-lab-pair.yml:73:    - name: Install CTF web service unit
./playbooks/ctf-lab-pair.yml:79:    - name: Enable and start CTF web service
./playbooks/ctf-lab-pair.yml:84:- name: Provision CTF attacker on lab-atk
./playbooks/ctf-lab-pair.yml:85:  hosts: lab_atk
./playbooks/ctf-lab-pair.yml:89:  tasks:
./playbooks/ctf-lab-pair.yml:90:    - name: Verify lab_id is set
./playbooks/ctf-lab-pair.yml:96:    - name: Launch attacker container from reproducible baseline image
./playbooks/ctf-lab-pair.yml:101:    - name: Wait until attacker container is running
./playbooks/ctf-lab-pair.yml:110:    - name: Verify baseline tools are present
./playbooks/ctf-lab-pair.yml:119:    - name: Grant network capabilities to recon tools
