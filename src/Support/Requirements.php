<?php
/**
 * Runtime dependency checks.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the WooCommerce dependency safe on WordPress versions that do not
 * enforce the Requires Plugins header.
 *
 * @since 0.1.0
 */
final class Requirements {

	/**
	 * Determine whether WooCommerce has loaded.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public static function has_woocommerce(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Render the dependency notice for administrators.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function render_admin_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Cart Rebound requires WooCommerce to be installed and active.', 'cart-rebound' );
		echo '</p></div>';
	}
}
