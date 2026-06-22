#!/usr/bin/env bash
set -euo pipefail

echo "Voelgoed Course Bridge — dev environment check"
echo ""

missing=()

if ! command -v php >/dev/null 2>&1; then
  missing+=("php-cli")
fi

if ! command -v composer >/dev/null 2>&1; then
  missing+=("composer")
fi

if ! command -v zip >/dev/null 2>&1; then
  missing+=("zip")
fi

if ! command -v unzip >/dev/null 2>&1; then
  missing+=("unzip")
fi

if [ "${#missing[@]}" -gt 0 ]; then
  echo "Missing tools: ${missing[*]}"
  echo ""
  echo "On Ubuntu 24.04 / WSL, install with:"
  echo ""
  echo "  sudo apt update"
  echo "  sudo apt install -y php-cli php-xml php-mbstring php-zip unzip zip composer"
  echo ""
  echo "Plugins require PHP 8.2+. Check with: php -v"
  echo ""
  echo "If apt composer is too old, use the official installer:"
  echo "  curl -sS https://getcomposer.org/installer | php"
  echo "  sudo mv composer.phar /usr/local/bin/composer"
  echo ""
  exit 1
fi

PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')

if [ "$PHP_MAJOR" -lt 8 ] || { [ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 2 ]; }; then
  echo "PHP 8.2+ required; found $(php -r 'echo PHP_VERSION;')"
  exit 1
fi

echo "OK: php $(php -r 'echo PHP_VERSION;')"
echo "OK: composer $(composer --version 2>/dev/null | head -1)"
echo "OK: zip / unzip"
echo ""
echo "Run the full harness:"
echo "  composer install"
echo "  bash tools/run-tests.sh"
