<?php
/**
 * Plugin lifecycle hooks (activation, deactivation, uninstall).
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Core;

defined( 'ABSPATH' ) || exit;

use CartRebound\Cron\AbandonmentDetector;
use CartRebound\Cron\Janitor;
use CartRebound\Cron\Scheduler;
use CartRebound\Database\Migrator;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Support\Requirements;

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
		if ( ! Requirements::has_woocommerce() ) {
			wp_die(
				esc_html__( 'Cart Rebound requires WooCommerce to be installed and active.', 'cart-rebound' ),
				esc_html__( 'Plugin dependency missing', 'cart-rebound' ),
				array( 'back_link' => true )
			);
		}

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
		$app = Application::get_instance();

		self::clear_scheduled_actions( $app );

		/**
		 * Fires after CartRebound has finished its deactivation routine.
		 *
		 * @since 0.1.0
		 *
		 * @param Application $app The application instance.
		 */
		do_action( 'cart_rebound_deactivated', $app );
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

		self::clear_scheduled_actions( $app );

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

	/**
	 * Remove every recurring and one-off job owned by the plugin.
	 *
	 * This runs directly from the lifecycle hooks because service providers may
	 * not have booted during activation, deactivation, or uninstall requests.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app Application container.
	 * @return void
	 */
	private static function clear_scheduled_actions( Application $app ): void {
		$scheduler = $app->make( Scheduler::class );

		$scheduler->clear( AbandonmentDetector::HOOK );
		$scheduler->clear( Janitor::HOOK );
		$scheduler->clear( RecoveryMailer::HOOK );
	}
}
