# Voelgoed Course Bridge

Local test harness and WordPress plugins for bridging WooCommerce paid orders on **winkel.voelgoed.co.za** to LearnDash access on **leer.voelgoed.co.za**.

## Plugins

- `plugins/voelgoed-course-bridge-sender/` — WooCommerce sender (winkel)
- `plugins/voelgoed-course-bridge-receiver/` — LearnDash receiver (leer)

## Prerequisites (Ubuntu / WSL / Kubuntu)

The harness needs **PHP 8.2+**, **Composer**, **zip**, and **unzip**.

Check what is missing:

```bash
bash tools/setup-dev.sh
```

Install on Ubuntu 24.04 / WSL:

```bash
sudo apt update
sudo apt install -y php-cli php-xml php-mbstring php-zip unzip zip composer
php -v
composer -V
```

If `apt` Composer is unavailable or too old:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer -V
```

ZIP build/check can work without PHP; **lint and PHPUnit require PHP + Composer**.

## Continuous integration

GitHub Actions runs on every pull request and on pushes to `main` and `test/**` / `feature/**` / `fix/**` / `chore/**` branches:

- PHP 8.2 and 8.3 matrix
- Composer install, lint, PHPUnit, ZIP build/check
- Plugin ZIP artifacts uploaded per PHP version

See [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

## Local development

```bash
composer install
bash tools/lint-php.sh
composer test
bash tools/build-zips.sh
bash tools/check-zips.sh
```

Or run everything:

```bash
bash tools/run-tests.sh
```

## Release ZIPs

Built artifacts land in `releases/` (gitignored):

- `voelgoed-course-bridge-sender-v1.0.0.zip`
- `voelgoed-course-bridge-receiver-v1.0.0.zip`

## Documentation

- [Testing strategy](docs/testing-strategy.md)
- [Staging test checklist](docs/staging-test-checklist.md)
- [Security model](docs/security-model.md)
- [Production readiness checklist](docs/production-readiness-checklist.md)

## Configuration

Secrets and bridge constants belong in `wp-config.php` on each site — never in this repository. See each plugin's `README-INSTALLATION.txt`.
