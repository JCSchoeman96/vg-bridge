#!/usr/bin/env bash
set -euo pipefail

echo "Running PHP syntax lint..."

find plugins tests -name "*.php" -print0 | while IFS= read -r -d '' file; do
  php -l "$file" > /dev/null
  echo "OK: $file"
done

echo "PHP lint passed."
