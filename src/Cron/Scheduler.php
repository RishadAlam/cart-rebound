<?php
/**
 * Scheduler abstraction over Action Scheduler / wp-cron.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper that prefers WooCommerce's bundled Action Scheduler and falls
 * back to native wp-cron when it is unavailable.
 *
 * Action Scheduler self-heals on low-traffic sites where wp-cron stalls, so it
 * is the production-grade choice; the fallback keeps the plugin functional if
 * WooCommerce (and thus Action Scheduler) is somehow absent.
 *
 * @since 0.1.0
 */
final class Scheduler {

	/**
	 * Action Scheduler group for this plugin's jobs.
	 *
	 * @var string
	 */
	public const GROUP = 'cart-rebound';

	/**
	 * Ensure a recurring job is scheduled (idempotent).
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook                Action hook name.
	 * @param int    $interval_seconds    Interval between runs, in seconds.
	 * @param string $fallback_recurrence Named wp-cron recurrence used only in the fallback.
	 * @return void
	 */
	public function ensure_recurring( string $hook, int $interval_seconds, string $fallback_recurrence ): void {
		if ( $this->uses_action_scheduler() ) {
			if ( false === as_next_scheduled_action( $hook, array(), self::GROUP ) ) {
				as_schedule_recurring_action( time(), $interval_seconds, $hook, array(), self::GROUP );
			}

			return;
		}

		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), $fallback_recurrence, $hook );
		}
	}

	/**
	 * Schedule a one-off job.
	 *
	 * @since 0.1.0
	 *
	 * @param int               $timestamp Unix timestamp to run at.
	 * @param string            $hook      Action hook name.
	 * @param array<int, mixed> $args      Positional arguments passed to the hook.
	 * @return void
	 */
	public function schedule_single( int $timestamp, string $hook, array $args = array() ): void {
		if ( $this->uses_action_scheduler() ) {
			as_schedule_single_action( $timestamp, $hook, $args, self::GROUP );

			return;
		}

		wp_schedule_single_event( $timestamp, $hook, array_values( $args ) );
	}

	/**
	 * Get the next scheduled timestamp for a hook (0 when none).
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook Action hook name.
	 * @return int
	 */
	public function next_scheduled( string $hook ): int {
		if ( $this->uses_action_scheduler() ) {
			$timestamp = as_next_scheduled_action( $hook, array(), self::GROUP );

			return is_int( $timestamp ) ? $timestamp : 0;
		}

		$timestamp = wp_next_scheduled( $hook );

		return is_int( $timestamp ) ? $timestamp : 0;
	}

	/**
	 * Remove all scheduled runs of a hook.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook Action hook name.
	 * @return void
	 */
	public function clear( string $hook ): void {
		if ( $this->uses_action_scheduler() ) {
			as_unschedule_all_actions( $hook, array(), self::GROUP );

			return;
		}

		$timestamp = wp_next_scheduled( $hook );

		while ( is_int( $timestamp ) ) {
			wp_unschedule_event( $timestamp, $hook );
			$timestamp = wp_next_scheduled( $hook );
		}
	}

	/**
	 * Whether Action Scheduler's full API is available.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private function uses_action_scheduler(): bool {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_next_scheduled_action' )
			&& function_exists( 'as_schedule_single_action' )
			&& function_exists( 'as_unschedule_all_actions' );
	}
}
