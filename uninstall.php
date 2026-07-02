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
} else {
	// Fallback: the autoloader is missing (e.g. a truncated install), but we
	// still must honor the uninstall contract — leave no data behind. This
	// mirrors Plugin::uninstall() using direct $wpdb calls.
	global $wpdb;

	$cart_rebound_tables = array(
		$wpdb->prefix . 'cart_rebound_sessions',
		$wpdb->prefix . 'cart_rebound_logs',
	);

	foreach ( $cart_rebound_tables as $cart_rebound_table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $cart_rebound_table ) );
	}

	$cart_rebound_options = array(
		'cart_rebound_settings',
		'cart_rebound_email_templates',
		'cart_rebound_db_version',
		'cart_rebound_migrations',
		'cart_rebound_lifetime_abandoned',
		'cart_rebound_lifetime_recovered',
	);

	foreach ( $cart_rebound_options as $cart_rebound_option ) {
		delete_option( $cart_rebound_option );
	}
}
