#!/usr/bin/env bash
#
# Build a production-ready plugin archive at build/cart-rebound.zip.
#
# Steps: build the front-end assets, install production-only Composer
# dependencies, stage the runtime files (excluding all dev/source/config),
# zip them, then restore the development dependencies.
#
# QA is run separately by the `production-zip` npm script, which invokes this
# after `qa:all` passes. Run `bash scripts/build-zip.sh` directly to skip QA.
set -euo pipefail

SLUG="cart-rebound"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD="${ROOT}/build"
STAGE="${BUILD}/${SLUG}"

cd "${ROOT}"

echo "▶ Building front-end assets"
npx vite build

echo "▶ Installing production Composer dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --quiet

echo "▶ Staging plugin files"
rm -rf "${BUILD}"
mkdir -p "${STAGE}"

# Ship only WordPress runtime files: cart-rebound.php, uninstall.php,
# readme.txt, LICENSE, composer.json, assets/, config/, languages/,
# public/build/, routes/, src/, vendor/. Everything below is dev tooling,
# source, or repo metadata.
rsync -a \
	--exclude='.git' \
	--exclude='.github' \
	--exclude='.wordpress-org' \
	--exclude='.husky' \
	--exclude='.impeccable' \
	--exclude='node_modules' \
	--exclude='/build' \
	--exclude='/dist' \
	--exclude='coverage' \
	--exclude='tests' \
	--exclude='docs' \
	--exclude='scripts' \
	--exclude='/resources/js' \
	--exclude='.gitkeep' \
	--exclude='.editorconfig' \
	--exclude='.eslintignore' \
	--exclude='.eslintrc.cjs' \
	--exclude='.gitattributes' \
	--exclude='.gitignore' \
	--exclude='.npmrc' \
	--exclude='.nvmrc' \
	--exclude='.prettierignore' \
	--exclude='.prettierrc.js' \
	--exclude='.stylelintignore' \
	--exclude='.stylelintrc.js' \
	--exclude='.php-cs-fixer.dist.php' \
	--exclude='.php-cs-fixer.cache' \
	--exclude='.phpunit.result.cache' \
	--exclude='.eslintcache' \
	--exclude='.stylelintcache' \
	--exclude='*.dist' \
	--exclude='phpstan-baseline.neon' \
	--exclude='rector.php' \
	--exclude='commitlint.config.js' \
	--exclude='lint-staged.config.mjs' \
	--exclude='postcss.config.js' \
	--exclude='tailwind.config.js' \
	--exclude='vite.config.ts' \
	--exclude='tsconfig*.json' \
	--exclude='package.json' \
	--exclude='package-lock.json' \
	--exclude='pnpm-lock.yaml' \
	--exclude='composer.lock' \
	--exclude='README.md' \
	"${ROOT}/" "${STAGE}/"

echo "▶ Restoring development Composer dependencies"
composer install --no-interaction --quiet

echo "▶ Creating archive"
( cd "${BUILD}" && zip -rqX "${SLUG}.zip" "${SLUG}" )

echo "✅ Built ${BUILD}/${SLUG}.zip"
