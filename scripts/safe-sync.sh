#!/usr/bin/env bash
set -euo pipefail

msg="${1:-chore: synchronize project state}"
repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"
branch="$(git branch --show-current)"

if [[ -z "$branch" ]]; then
  echo "Refusing to sync from detached HEAD." >&2
  exit 1
fi

# Integrate remote changes before staging local work.
git pull --rebase --autostash origin "$branch"

# Stage all non-ignored changes. The pre-commit hook blocks sensitive paths.
git add -A

if git diff --cached --quiet; then
  echo "No changes to commit."
  exit 0
fi

# Basic syntax checks for versioned shell scripts/hooks. Files implemented in
# Python or another language may also live under scripts/, so only pass files
# with a shell extension or shell shebang to bash -n.
while IFS= read -r -d '' f; do
  first_line=""
  IFS= read -r first_line < "$f" || true
  if [[ "$f" == *.sh || "$first_line" =~ ^\#!.*[[:space:]/](ba|da|k|z)?sh([[:space:]]|$) ]]; then
    bash -n "$f"
  fi
done < <(find scripts .githooks -type f -print0 2>/dev/null || true)

git commit -m "$msg"

# post-commit normally pushes. This fallback handles cases where hooks were not enabled.
local_sha="$(git rev-parse HEAD)"
remote_sha="$(git ls-remote origin "refs/heads/$branch" | awk '{print $1}')"
if [[ "$local_sha" != "$remote_sha" ]]; then
  git push origin "$branch"
fi

echo "Synchronized $branch at $local_sha"
