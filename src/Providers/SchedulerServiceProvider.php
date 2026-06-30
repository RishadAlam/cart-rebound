<?php
/**
 * Scheduler service provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\ServiceProvider;
use CartRebound\Cron\AbandonmentDetector;
use CartRebound\Cron\Janitor;
use CartRebound\Cron\Scheduler;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Support\Settings;

/**
 * Registers the recurring/one-off job handlers and keeps the schedule in sync
 * with the plugin's enabled state.
 *
 * @since 0.1.0
 */
final class SchedulerServiceProvider extends ServiceProvider {

	/**
	 * Named wp-cron recurrence used by the detection-scan fallback.
	 *
	 * @var string
	 */
	private const FALLBACK_RECURRENCE = 'cart_rebound_scan_interval';

	/**
	 * Wire job handlers + schedule reconciliation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( AbandonmentDetector::HOOK, array( $this->app->make( AbandonmentDetector::class ), 'run' ) );
		add_action(
			Janitor::HOOK,
			function (): void {
				$this->app->make( Janitor::class )->run();
			}
		);
		add_action( RecoveryMailer::HOOK, array( $this->app->make( RecoveryMailer::class ), 'send' ), 10, 1 );

		// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- intentional 5-minute scan fallback; Action Scheduler is the primary scheduler when WooCommerce is active.
		add_filter( 'cron_schedules', array( $this, 'register_fallback_schedule' ) );

		add_action( 'init', array( $this, 'sync_schedule' ) );
		add_action( 'cart_rebound_activated', array( $this, 'sync_schedule' ) );
		add_action( 'cart_rebound_settings_updated', array( $this, 'sync_schedule' ) );
		add_action( 'cart_rebound_deactivated', array( $this, 'clear_schedule' ) );
	}

	/**
	 * Reconcile both jobs against the current settings (idempotent).
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function sync_schedule(): void {
		$settings = $this->app->make( Settings::class );

		if ( ! $settings->get( 'enabled' ) ) {
			$this->clear_schedule();

			return;
		}

		$scheduler = $this->app->make( Scheduler::class );
		$interval  = max( 60, (int) $settings->get( 'scan_interval' ) * MINUTE_IN_SECONDS );

		$scheduler->ensure_recurring( AbandonmentDetector::HOOK, $interval, self::FALLBACK_RECURRENCE );
		$scheduler->ensure_recurring( Janitor::HOOK, DAY_IN_SECONDS, 'daily' );
	}

	/**
	 * Remove both scheduled jobs.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function clear_schedule(): void {
		$scheduler = $this->app->make( Scheduler::class );
		$scheduler->clear( AbandonmentDetector::HOOK );
		$scheduler->clear( Janitor::HOOK );
	}

	/**
	 * Register the fallback wp-cron recurrence for detection scans.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_fallback_schedule( $schedules ): array {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}

		$schedules[ self::FALLBACK_RECURRENCE ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes (Cart Rebound)', 'cart-rebound' ),
		);

		return $schedules;
	}
}
