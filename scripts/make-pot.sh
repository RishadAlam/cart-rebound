#!/usr/bin/env bash
#
# Generate the translation template at languages/cart-rebound.pot using WP-CLI.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "${ROOT}"

wp i18n make-pot . languages/cart-rebound.pot \
	--domain=cart-rebound \
	--exclude=node_modules,vendor,tests,public,dist,resources/js \
	--headers='{"Report-Msgid-Bugs-To":"https://example.com"}'

echo "✅ Generated languages/cart-rebound.pot"
