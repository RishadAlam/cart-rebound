#!/usr/bin/env bash
#
# Generate the translation template at languages/cart-rebound.pot using WP-CLI.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "${ROOT}"

wp i18n make-pot . languages/cart-rebound.pot \
	--domain=cart-rebound \
	--include=cart-rebound.php,uninstall.php,src,routes,config,resources/views,public/build \
	--exclude=build,vendor,node_modules,tests \
	--headers='{"Report-Msgid-Bugs-To":"https://github.com/RishadAlam/cart-rebound/issues"}'

echo "✅ Generated languages/cart-rebound.pot"
