<?php
/**
 * Abandonment detection job.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Cron;

defined( 'ABSPATH' ) || exit;

use CartRebound\Events\EventDispatcher;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Models\CartSession;
use CartRebound\Support\Settings;

/**
 * Flips idle active carts to `abandoned` and dispatches the abandonment event.
 *
 * The idle threshold lives in the WHERE clause (not the cron cadence), so a
 * changed threshold takes effect on the next scan with no rescheduling. Each row
 * is marked before its event fires so it can never be selected twice.
 *
 * @since 0.1.0
 */
final class AbandonmentDetector {

	/**
	 * Recurring action hook that triggers a scan.
	 *
	 * @var string
	 */
	public const HOOK = 'cart_rebound_scan_abandoned';

	/**
	 * Rows fetched per batch.
	 *
	 * @var int
	 */
	private const BATCH = 50;

	/**
	 * Maximum rows processed in a single run (backlog guard).
	 *
	 * @var int
	 */
	private const MAX_PER_RUN = 500;

	/**
	 * Settings store.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Event dispatcher.
	 *
	 * @since 0.1.0
	 * @var EventDispatcher
	 */
	private $events;

	/**
	 * Scheduler for follow-up emails.
	 *
	 * @since 0.1.0
	 * @var Scheduler
	 */
	private $scheduler;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Settings        $settings  Settings store.
	 * @param EventDispatcher $events    Event dispatcher.
	 * @param Scheduler       $scheduler Scheduler for follow-up emails.
	 */
	public function __construct( Settings $settings, EventDispatcher $events, Scheduler $scheduler ) {
		$this->settings  = $settings;
		$this->events    = $events;
		$this->scheduler = $scheduler;
	}

	/**
	 * Run a detection pass.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function run(): void {
		$threshold = max( 1, (int) $this->settings->get( 'abandonment_threshold' ) );
		$cutoff    = gmdate( 'Y-m-d H:i:s', time() - ( $threshold * MINUTE_IN_SECONDS ) );
		$processed = 0;

		do {
			$rows = CartSession::query()
				->where( 'status', '=', CartSession::STATUS_ACTIVE )
				->where( 'abandonment_notified', '=', 0 )
				->where( 'email', '!=', '' )
				->where( 'items_count', '>', 0 )
				->where( 'last_activity', '<', $cutoff )
				->order_by( 'last_activity', 'ASC' )
				->limit( self::BATCH )
				->get();

			$fetched = count( $rows );

			foreach ( $rows as $row ) {
				$this->mark_abandoned( $row );
				++$processed;

				if ( $processed >= self::MAX_PER_RUN ) {
					return;
				}
			}
		} while ( self::BATCH === $fetched );
	}

	/**
	 * Manually abandon a single cart by id (admin action).
	 *
	 * Reuses the exact detector transition so a hand-abandoned cart fires the
	 * abandonment event, bumps the lifetime counter, and queues the recovery
	 * email just like an auto-detected one. No-ops on an unknown row or one that
	 * is already abandoned (so the event can never double-fire).
	 *
	 * @since 0.1.0
	 *
	 * @param int $cart_id Cart id.
	 * @return bool True when the cart was abandoned by this call.
	 */
	public function abandon( int $cart_id ): bool {
		$row = CartSession::find( $cart_id );

		if ( ! is_array( $row ) ) {
			return false;
		}

		if ( CartSession::STATUS_ABANDONED === (string) ( $row['status'] ?? '' ) ) {
			return false;
		}

		$this->mark_abandoned( $row );

		return true;
	}

	/**
	 * Flip a single row to abandoned, dispatch the event, and queue an email.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row The active cart row.
	 * @return void
	 */
	private function mark_abandoned( array $row ): void {
		$id = (int) ( $row['id'] ?? 0 );

		CartSession::update(
			$id,
			array(
				'status'               => CartSession::STATUS_ABANDONED,
				'abandoned_at'         => gmdate( 'Y-m-d H:i:s' ),
				'abandonment_notified' => 1,
			)
		);

		$this->events->abandoned( $row );

		if ( $this->settings->get( 'recovery_email_enabled' ) && '' !== (string) ( $row['email'] ?? '' ) ) {
			$delay = max( 1, (int) $this->settings->get( 'email_delay_minutes' ) );
			$this->scheduler->schedule_single(
				time() + ( $delay * MINUTE_IN_SECONDS ),
				RecoveryMailer::HOOK,
				array( $id )
			);
		}
	}
}
