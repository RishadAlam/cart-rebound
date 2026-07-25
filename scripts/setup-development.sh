#!/usr/bin/env bash
#
# Configure Cart Rebound for development after cloning it into WordPress.
set -euo pipefail

PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORDPRESS_ROOT="$(cd "${PLUGIN_ROOT}/../../.." && pwd)"

for command in composer pnpm wp; do
	if ! command -v "${command}" >/dev/null 2>&1; then
		echo "Missing required command: ${command}" >&2
		exit 1
	fi
done

if [[ ! -f "${WORDPRESS_ROOT}/wp-load.php" ]]; then
	echo "WordPress was not found at ${WORDPRESS_ROOT}." >&2
	echo "Clone Cart Rebound into wp-content/plugins/cart-rebound." >&2
	exit 1
fi

cd "${PLUGIN_ROOT}"

echo "▶ Installing Composer development dependencies"
composer install --no-interaction

echo "▶ Installing locked pnpm dependencies"
pnpm install --frozen-lockfile

echo "▶ Building admin assets"
pnpm run build

echo "▶ Configuring local WordPress development constants"
wp config set WP_DEBUG true --raw --path="${WORDPRESS_ROOT}"
wp config set WP_DEBUG_LOG true --raw --path="${WORDPRESS_ROOT}"
wp config set WP_DEBUG_DISPLAY false --raw --path="${WORDPRESS_ROOT}"
wp config set WP_ENVIRONMENT_TYPE local --path="${WORDPRESS_ROOT}"
wp config set CART_REBOUND_ENABLE_HMR true --raw --path="${WORDPRESS_ROOT}"

if ! wp plugin is-installed woocommerce --path="${WORDPRESS_ROOT}"; then
	echo "▶ Installing WooCommerce"
	wp plugin install woocommerce --path="${WORDPRESS_ROOT}"
fi

echo "▶ Activating WooCommerce"
wp plugin activate woocommerce --path="${WORDPRESS_ROOT}"

echo "▶ Activating Cart Rebound"
wp plugin activate cart-rebound --path="${WORDPRESS_ROOT}"

echo "▶ Running Cart Rebound database migrations"
wp cart-rebound migrate --path="${WORDPRESS_ROOT}"

echo "✅ Cart Rebound development setup is complete."
echo "Run 'pnpm dev' to start the Vite HMR server."
