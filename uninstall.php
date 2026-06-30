<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package CartRebound
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$cart_rebound_autoloader = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $cart_rebound_autoloader ) ) {
	require_once $cart_rebound_autoloader;

	// The main plugin file is not loaded during uninstall, so initialise the
	// application with its base path before resolving services.
	CartRebound\Core\Application::get_instance( plugin_dir_path( __FILE__ ) );
	CartRebound\Core\Plugin::uninstall();
}
