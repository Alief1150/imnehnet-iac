#!/usr/bin/env bash
set -euo pipefail
PROJECT="/home/iaas/projects/webportal-iaas"
TARGET="$PROJECT/public/imnehnet-login/posters"
[ -f "$PROJECT/artisan" ] || { echo 'Laravel project not found'; exit 1; }
mkdir -p "$TARGET"
chmod 755 "$TARGET"
cat > "$TARGET/README.txt" <<'TXT'
IMNEHNET Login Carousel Posters
01-cybersecurity-lab.webp - Learn. Explore. Secure.
02-isolated-lab.webp - Isolated Lab Environment
03-learning-progress.webp - Track Your Progress
Suggested size: 1200x1500 (4:5), WebP, optimized below 400 KB if possible.
Place key artwork in the upper half; leave lower region uncluttered for HTML text.
TXT
chmod 644 "$TARGET/README.txt"
echo "Created: $TARGET"
ls -lah "$TARGET"
