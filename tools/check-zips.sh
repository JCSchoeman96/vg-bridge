#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASES_DIR="$ROOT_DIR/releases"

check_zip() {
  local zip_file="$1"
  local expected_root="$2"

  echo "Checking $zip_file"

  test -f "$zip_file"

  unzip -l "$zip_file" | grep -q "$expected_root/"
  ! unzip -l "$zip_file" | grep -q ".git/"
  ! unzip -l "$zip_file" | grep -q "tests/"
  ! unzip -l "$zip_file" | grep -q "docs/"
  ! unzip -l "$zip_file" | grep -q "releases/"
  ! unzip -l "$zip_file" | grep -q "wp-config.php"
  ! unzip -l "$zip_file" | grep -q ".env"
  ! unzip -l "$zip_file" | grep -q "vendor/"
  ! unzip -l "$zip_file" | grep -q "node_modules/"
}

check_zip "$RELEASES_DIR/voelgoed-course-bridge-sender-v1.0.0.zip" "voelgoed-course-bridge-sender"
check_zip "$RELEASES_DIR/voelgoed-course-bridge-receiver-v1.0.0.zip" "voelgoed-course-bridge-receiver"

echo "ZIP checks passed."
