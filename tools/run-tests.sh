#!/usr/bin/env bash
set -euo pipefail

composer install
composer lint
composer test
composer build
composer check-zips
