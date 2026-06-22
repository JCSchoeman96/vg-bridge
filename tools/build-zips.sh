#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASES_DIR="$ROOT_DIR/releases"
VERSION="1.0.0"

SENDER_DIR="$ROOT_DIR/plugins/voelgoed-course-bridge-sender"
RECEIVER_DIR="$ROOT_DIR/plugins/voelgoed-course-bridge-receiver"

mkdir -p "$RELEASES_DIR"

build_plugin_zip() {
  local plugin_dir="$1"
  local plugin_slug="$2"
  local output="$RELEASES_DIR/${plugin_slug}-v${VERSION}.zip"

  echo "Building $output"
  rm -f "$output"

  (
    cd "$(dirname "$plugin_dir")"
    zip -r "$output" "$(basename "$plugin_dir")" \
      -x "*.git*" \
      -x "*/node_modules/*" \
      -x "*/vendor/*" \
      -x "*/tests/*" \
      -x "*/docs/*" \
      -x "*/releases/*" \
      -x "*/.env" \
      -x "*/wp-config.php" \
      -x "*/composer.json" \
      -x "*/composer.lock"
  )
}

build_plugin_zip "$SENDER_DIR" "voelgoed-course-bridge-sender"
build_plugin_zip "$RECEIVER_DIR" "voelgoed-course-bridge-receiver"

echo "ZIP build complete."
