#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

chmod +x scripts/*.sh .githooks/*
git config core.hooksPath .githooks

if ! git config user.name >/dev/null; then
  echo "Git user.name is not configured. Set it before committing:" >&2
  echo '  git config user.name "YOUR_GITHUB_NAME"' >&2
fi

if ! git config user.email >/dev/null; then
  echo "Git user.email is not configured. Use an email associated with your GitHub account" >&2
  echo "(or your GitHub noreply address) so eligible commits can be credited to your profile." >&2
fi

if command -v gh >/dev/null 2>&1; then
  gh auth status || true
else
  echo "GitHub CLI (gh) is not installed. Git push can still work via SSH/HTTPS credentials." >&2
fi

cat <<'EOF'
Hermes project bootstrap complete.
Run Hermes from this directory so AGENTS.md is automatically loaded:

  hermes

Then paste the operator prompt from docs/hermes-prompt.md once if desired.
EOF
