#!/usr/bin/env bash
set -euo pipefail

OWNER="${GITHUB_OWNER:-Alief1150}"
REPO="${GITHUB_REPO:-imnehnet-iac}"

if ! command -v git >/dev/null 2>&1; then
  echo "git is required." >&2
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "GitHub CLI is required. On Ubuntu/Linux Mint:" >&2
  echo "  sudo apt update && sudo apt install -y gh" >&2
  exit 1
fi

gh auth status || gh auth login

if [[ ! -d .git ]]; then
  git init -b main
fi

chmod +x scripts/*.sh .githooks/* CREATE_REPO.sh
git config core.hooksPath .githooks

git add -A
if ! git diff --cached --quiet; then
  git commit -m "chore: initialize IMNEHNET shared knowledge repository"
fi

# Create a private repository and push the current main branch.
gh repo create "$OWNER/$REPO" --private --source=. --remote=origin --push

echo "Created and pushed private repository: $OWNER/$REPO"
