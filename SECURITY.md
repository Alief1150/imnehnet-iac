# Security Policy

This repository may describe security-sensitive infrastructure, but it must not store live credentials or secret material.

Never commit:

- API keys or tokens
- Tailscale auth keys
- Cloudflare credentials
- SSH/VNC passwords or private keys
- `.env` files with real values
- OpenTofu/Terraform state or plan files containing sensitive values
- Wazuh/Zabbix administrative credentials

If a secret is committed, treat it as compromised: rotate the secret first, then remove it from Git history.

GitHub Actions are validation-only. Deployment from GitHub-hosted runners is intentionally disabled.
