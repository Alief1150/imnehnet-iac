# Runbook

## Read-only health snapshot

Run these before making infrastructure assumptions:

```bash
hostname
lscpu
free -h
lsblk
grep -E '(vmx|svm)' /proc/cpuinfo | head
warp-cli status
tailscale status
tailscale ip -4
ip addr
ip route
```

## Repository sync

```bash
cd ~/imnehnet-iac
git pull --rebase --autostash
```

After a documented change:

```bash
./scripts/safe-sync.sh "docs: update verified host state"
```

## Tailscale remote access

Use MagicDNS where available:

```bash
ssh imnehnet@imnehnet
```

For VNC, use a VNC client over the Tailscale path. Browser access requires a noVNC/WebSocket gateway and is not provided directly by x11vnc.

## Change management

Before live changes:

1. Capture current state.
2. Generate a plan/diff where possible.
3. Explain impact and rollback.
4. Obtain explicit approval for destructive or connectivity-affecting actions.
5. Apply one change at a time.
6. Verify health.
7. Update `docs/status.md` and, if needed, `docs/decisions.md`.
8. Commit and push repository changes.
