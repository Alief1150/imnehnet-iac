# Ansible

Ansible configures guest operating systems and applications after infrastructure provisioning.

Expected roles include:

- common baseline/hardening
- Zabbix server/agent
- Wazuh manager/agent
- client configuration
- Windows/PWA workload configuration where required
- distributed node-local audit reporting

The current repository includes:

- `playbooks/imnehnet-audit.yml` and role `roles/imnehnet_audit` for Debian/
  Ubuntu audit deployment;
- `playbooks/imnehnet-audit-rollback.yml` for conservative removal of managed
  audit files without uninstalling packages;
- reviewed Tailscale APT keyring repair and rollback playbooks for the verified
  LAB-ENV empty-keyring failure.

Run audit deployment one node at a time with `--check --diff` before apply.
Actor mappings belong in a protected temporary vars file outside Git. Inventory,
become passwords, raw logs, private keys, and node-specific credentials must not
be committed.

Do not store vault passwords, plaintext credentials, or SSH private keys in this repository.
