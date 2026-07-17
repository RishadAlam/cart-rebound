<?php
/**
 * Plugin Name:       Cart Rebound
 * Plugin URI:        https://github.com/RishadAlam/cart-rebound
 * Description:       Recover abandoned WooCommerce carts with secure links, optional emails, opt-in guest tracking, and accurate revenue attribution.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Rishad Alam
 * Author URI:        https://profiles.wordpress.org/rishadbitcode/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cart-rebound
 * Domain Path:       /languages
 *
 * @package CartRebound
 */

defined( 'ABSPATH' ) || exit;

define( 'CART_REBOUND_VERSION', '0.1.0' );
define( 'CART_REBOUND_FILE', __FILE__ );
define( 'CART_REBOUND_PATH', plugin_dir_path( __FILE__ ) );
define( 'CART_REBOUND_URL', plugin_dir_url( __FILE__ ) );

$cart_rebound_autoloader = CART_REBOUND_PATH . 'vendor/autoload.php';

if ( ! is_readable( $cart_rebound_autoloader ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Cart Rebound: run "composer install" to generate the autoloader before activating the plugin.', 'cart-rebound' );
			echo '</p></div>';
		}
	);

	return;
}

require_once $cart_rebound_autoloader;
require_once CART_REBOUND_PATH . 'src/Support/helpers.php';

/**
 * Boot the application once WordPress has loaded all active plugins.
 *
 * The Application is a singleton; service providers are registered and
 * booted in two phases so cross-provider dependencies resolve cleanly.
 */
$cart_rebound_app = CartRebound\Core\Application::get_instance( CART_REBOUND_PATH );

register_activation_hook( CART_REBOUND_FILE, array( CartRebound\Core\Plugin::class, 'activate' ) );
register_deactivation_hook( CART_REBOUND_FILE, array( CartRebound\Core\Plugin::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () use ( $cart_rebound_app ): void {
		if ( ! CartRebound\Support\Requirements::has_woocommerce() ) {
			add_action( 'admin_notices', array( CartRebound\Support\Requirements::class, 'render_admin_notice' ) );
		}

		// Privacy/export/erase integrations remain available even if WooCommerce
		// is deactivated after Cart Rebound was already installed.
		$cart_rebound_app->bootstrap();
	}
);
