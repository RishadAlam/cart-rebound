#!/usr/bin/env bash
#
# Build a production-ready plugin archive at build/cart-rebound.zip.
#
# Steps: build the front-end assets and translation template, install
# production-only Composer dependencies, stage the explicit runtime allowlist,
# zip it, then restore the development dependencies.
#
# QA is run separately by the `production-zip` npm script, which invokes this
# after `qa:all` passes. Run `bash scripts/build-zip.sh` directly to skip QA.
set -euo pipefail

SLUG="cart-rebound"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD="${ROOT}/build"
STAGE="${BUILD}/${SLUG}"

cd "${ROOT}"

restore_development_dependencies() {
	echo "▶ Restoring development Composer dependencies"
	composer install --no-interaction --quiet
}

echo "▶ Building front-end assets"
pnpm exec vite build

echo "▶ Generating translation template"
bash scripts/make-pot.sh

echo "▶ Installing production Composer dependencies"
trap restore_development_dependencies EXIT
composer install --no-dev --optimize-autoloader --no-interaction --quiet

echo "▶ Staging plugin files"
rm -rf "${BUILD}"
mkdir -p "${STAGE}"

# Copy an explicit runtime allowlist so new repository tooling can never leak
# into the submission archive. resources/views is required by recovery emails.
RUNTIME_PATHS=(
	"cart-rebound.php"
	"uninstall.php"
	"readme.txt"
	"LICENSE"
	"THIRD-PARTY-LICENSES.txt"
	"assets"
	"config"
	"languages"
	"public/build"
	"resources/views"
	"routes"
	"src"
	"vendor"
)

for runtime_path in "${RUNTIME_PATHS[@]}"; do
	rsync -a --relative --exclude='.gitkeep' \
		"./${runtime_path}" "${STAGE}/"
done

restore_development_dependencies
trap - EXIT

echo "▶ Creating archive"
( cd "${BUILD}" && zip -rqX "${SLUG}.zip" "${SLUG}" )

echo "✅ Built ${BUILD}/${SLUG}.zip"
