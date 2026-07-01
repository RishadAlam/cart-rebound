<?php
/**
 * Activity log service provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\ServiceProvider;
use CartRebound\Database\Migrator;
use CartRebound\Events\LogSubscriber;

/**
 * Wires the activity-log subscriber to the plugin's public events, and runs any
 * pending migrations after a version bump (so new tables — like the log table —
 * appear on update without a manual re-activation).
 *
 * @since 0.2.0
 */
final class LogServiceProvider extends ServiceProvider {

	/**
	 * Option storing the last-migrated plugin version.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'cart_rebound_db_version';

	/**
	 * Wire log hooks.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function boot(): void {
		$subscriber = $this->app->make( LogSubscriber::class );

		add_action( 'cart_rebound_abandoned', array( $subscriber, 'on_abandoned' ), 10, 1 );
		add_action( 'cart_rebound_recovered', array( $subscriber, 'on_recovered' ), 10, 1 );
		add_action( 'cart_rebound_email_sent', array( $subscriber, 'on_email_sent' ), 10, 2 );

		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Run pending migrations when the installed version changed.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		if ( ! defined( 'CART_REBOUND_VERSION' ) ) {
			return;
		}

		if ( CART_REBOUND_VERSION === get_option( self::VERSION_OPTION, '' ) ) {
			return;
		}

		$this->app->make( Migrator::class )->run();
		update_option( self::VERSION_OPTION, CART_REBOUND_VERSION );
	}
}
