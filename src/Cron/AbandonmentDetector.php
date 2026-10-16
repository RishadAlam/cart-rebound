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
 * Each row is marked before its event fires so it can never be selected twice.
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
				$this->abandon_if_still_idle( $row, $cutoff );
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
	 * Abandon a cart from the scan, but only while it is still idle and active.
	 *
	 * The flip re-checks idleness in its WHERE clause (compare-and-set), so a shopper who
	 * returned mid-batch is never marked abandoned.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row    The candidate cart row (as read).
	 * @param string               $cutoff The idle cutoff timestamp for this run.
	 * @return void
	 */
	private function abandon_if_still_idle( array $row, string $cutoff ): void {
		$id     = (int) ( $row['id'] ?? 0 );
		$fields = $this->abandoned_fields();

		$flipped = CartSession::query()
			->where( 'id', '=', $id )
			->where( 'status', '=', CartSession::STATUS_ACTIVE )
			->where( 'items_count', '>', 0 )
			->where( 'last_activity', '<', $cutoff )
			->update_where( $fields );

		if ( $flipped < 1 ) {
			return;
		}

		$this->notify( $row, $fields );
	}

	/**
	 * Flip a single row to abandoned unconditionally, then notify.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row The cart row.
	 * @return void
	 */
	private function mark_abandoned( array $row ): void {
		$fields = $this->abandoned_fields();

		CartSession::update( (int) ( $row['id'] ?? 0 ), $fields );

		$this->notify( $row, $fields );
	}

	/**
	 * The column changes that constitute an abandonment.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	private function abandoned_fields(): array {
		return array(
			'status'               => CartSession::STATUS_ABANDONED,
			'abandoned_at'         => gmdate( 'Y-m-d H:i:s' ),
			'abandonment_notified' => 1,
		);
	}

	/**
	 * Dispatch the abandonment event and queue the recovery email.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row    The cart row as read before the flip.
	 * @param array<string, mixed> $fields The abandonment columns that were written.
	 * @return void
	 */
	private function notify( array $row, array $fields ): void {
		$id      = (int) ( $row['id'] ?? 0 );
		$flipped = array_merge( $row, $fields );

		$this->events->status_changed( $flipped, (string) ( $row['status'] ?? '' ) );
		$this->events->abandoned( $flipped );

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
