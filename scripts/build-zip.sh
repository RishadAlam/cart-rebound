#!/usr/bin/env bash
# QA runs in the `production-zip` npm script, not here.
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

# Explicit allowlist so new repository tooling can never leak into the archive.
RUNTIME_PATHS=(
	"cart-rebound.php"
	"uninstall.php"
	"readme.txt"
	"LICENSE"
	"THIRD-PARTY-LICENSES.txt"
	"composer.json"
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
	rsync -a --relative --prune-empty-dirs \
		--exclude='.gitkeep' \
		--exclude='.DS_Store' \
		"./${runtime_path}" "${STAGE}/"
done

restore_development_dependencies
trap - EXIT

echo "▶ Creating archive"
( cd "${BUILD}" && zip -rqX "${SLUG}.zip" "${SLUG}" )

echo "✅ Built ${BUILD}/${SLUG}.zip"
