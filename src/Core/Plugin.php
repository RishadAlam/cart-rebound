<?php
/**
 * Plugin lifecycle hooks (activation, deactivation, uninstall).
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Core;

defined( 'ABSPATH' ) || exit;

use CartRebound\Database\Migrator;

/**
 * Centralises activation, deactivation and uninstall behaviour.
 *
 * Registered from the bootstrap file via the WordPress lifecycle hooks. Each
 * method resolves its collaborators from the container rather than doing work
 * inline, keeping lifecycle logic testable.
 *
 * @since 0.1.0
 */
final class Plugin {

	/**
	 * Run on plugin activation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function activate(): void {
		$app = Application::get_instance();

		$app->make( Migrator::class )->run();

		/**
		 * Fires after CartRebound has finished its activation routine.
		 *
		 * @since 0.1.0
		 *
		 * @param Application $app The application instance.
		 */
		do_action( 'cart_rebound_activated', $app );
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		/**
		 * Fires after CartRebound has finished its deactivation routine.
		 *
		 * @since 0.1.0
		 *
		 * @param Application $app The application instance.
		 */
		do_action( 'cart_rebound_deactivated', Application::get_instance() );
	}

	/**
	 * Run on plugin uninstall.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		$app = Application::get_instance();

		// Drops the custom tables and deletes the cart_rebound_migrations option.
		$app->make( Migrator::class )->rollback();

		// Remove every remaining plugin option so uninstall leaves nothing behind.
		$options = array(
			'cart_rebound_settings',
			'cart_rebound_email_templates',
			'cart_rebound_db_version',
			'cart_rebound_lifetime_abandoned',
			'cart_rebound_lifetime_recovered',
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		/**
		 * Fires after CartRebound has finished its uninstall routine.
		 *
		 * @since 0.1.0
		 *
		 * @param Application $app The application instance.
		 */
		do_action( 'cart_rebound_uninstalled', $app );
	}
}
