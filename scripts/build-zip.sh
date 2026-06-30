#!/usr/bin/env bash
#
# Build a production-ready plugin archive at dist/cart-rebound.zip.
#
# Steps: build the front-end assets, install production-only Composer
# dependencies, stage the runtime files (excluding all dev/source/config),
# zip them, then restore the development dependencies.
set -euo pipefail

SLUG="cart-rebound"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${ROOT}/dist"
STAGE="${DIST}/${SLUG}"

cd "${ROOT}"

echo "▶ Building front-end assets"
npx vite build

echo "▶ Installing production Composer dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --quiet

echo "▶ Staging plugin files"
rm -rf "${DIST}"
mkdir -p "${STAGE}"

rsync -a \
	--exclude='.git' \
	--exclude='.github' \
	--exclude='.husky' \
	--exclude='node_modules' \
	--exclude='tests' \
	--exclude='docs' \
	--exclude='scripts' \
	--exclude='dist' \
	--exclude='coverage' \
	--exclude='resources/js' \
		--exclude='.impeccable' \
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
( cd "${DIST}" && zip -rqX "${SLUG}.zip" "${SLUG}" )

echo "✅ Built ${DIST}/${SLUG}.zip"
