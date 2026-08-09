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
use CartRebound\Followup\Runner;
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
	 * A row this pass transitioned to abandoned.
	 *
	 * @var string
	 */
	private const OUTCOME_ABANDONED = 'abandoned';

	/**
	 * A row an extension excluded; it stays active and stays selectable.
	 *
	 * @var string
	 */
	private const OUTCOME_SKIPPED = 'skipped';

	/**
	 * A row that stopped matching between the read and the write — the shopper
	 * came back, it converted, or it emptied. It has left the candidate set.
	 *
	 * @var string
	 */
	private const OUTCOME_GONE = 'gone';

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
	 * Follow-up plan runner.
	 *
	 * @since 1.1.0
	 * @var Runner
	 */
	private $followups;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Settings        $settings  Settings store.
	 * @param EventDispatcher $events    Event dispatcher.
	 * @param Runner          $followups Follow-up plan runner.
	 */
	public function __construct( Settings $settings, EventDispatcher $events, Runner $followups ) {
		$this->settings  = $settings;
		$this->events    = $events;
		$this->followups = $followups;
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

		/*
		 * A transitioned or vanished row drops out of the candidate query, so the
		 * next page naturally starts where this one ended. A row an extension
		 * excluded does not: nothing about it changed, so it would be re-read on
		 * every page forever and a full batch of them would starve every eligible
		 * cart queued behind it. Stepping the offset past exactly those rows —
		 * and only those — is what keeps the scan moving. Because the query is
		 * ordered oldest-first, the skipped survivors are always the leading rows
		 * of the next page, so the offset lands on them precisely.
		 */
		$skipped = 0;

		do {
			$rows = CartSession::query()
				->where( 'status', '=', CartSession::STATUS_ACTIVE )
				->where( 'abandonment_notified', '=', 0 )
				->where( 'email', '!=', '' )
				->where( 'items_count', '>', 0 )
				->where( 'last_activity', '<', $cutoff )
				->order_by( 'last_activity', 'ASC' )
				->limit( self::BATCH )
				->offset( $skipped )
				->get();

			$fetched = count( $rows );

			foreach ( $rows as $row ) {
				if ( self::OUTCOME_SKIPPED === $this->abandon_if_still_idle( $row, $cutoff ) ) {
					++$skipped;
				}

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
	 * Abandon a cart from the scan, but only while it is still idle and active.
	 *
	 * The batch is read first and flipped afterwards, so a shopper can return in
	 * between (their cart is re-touched, bumping last_activity, or it converts /
	 * empties). This re-checks those exact conditions in the WHERE clause of the
	 * flip itself — an atomic compare-and-set — so a cart that became active
	 * again is never force-marked abandoned, and the event/email only fire when a
	 * row was genuinely transitioned.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row    The candidate cart row (as read).
	 * @param string               $cutoff The idle cutoff timestamp for this run.
	 * @return string One of the OUTCOME_* constants.
	 */
	private function abandon_if_still_idle( array $row, string $cutoff ): string {
		$id = (int) ( $row['id'] ?? 0 );

		/**
		 * Filter whether an idle cart should enter the recovery funnel.
		 *
		 * Runs before the row is flipped, so returning false leaves the cart
		 * `active`: it is never abandoned, no event fires, and no follow-up is
		 * planned. This is where trigger rules belong — a minimum cart value, an
		 * excluded role, a product that should not be chased.
		 *
		 * The decision must be stable for a given cart. The scan pages past the
		 * carts this filter excludes, so a callback that answers differently on
		 * each call will make the scan step over carts it should have processed.
		 *
		 * @since 1.1.0
		 *
		 * @param bool                 $should_abandon Whether to abandon the cart.
		 * @param array<string, mixed> $row            The candidate cart row.
		 */
		if ( ! apply_filters( 'cart_rebound_should_abandon', true, $row ) ) {
			return self::OUTCOME_SKIPPED;
		}

		$fields = $this->abandoned_fields();

		$flipped = CartSession::query()
			->where( 'id', '=', $id )
			->where( 'status', '=', CartSession::STATUS_ACTIVE )
			->where( 'items_count', '>', 0 )
			->where( 'last_activity', '<', $cutoff )
			->update_where( $fields );

		if ( $flipped < 1 ) {
			return self::OUTCOME_GONE;
		}

		$this->notify( array_merge( $row, $fields ), $id );

		return self::OUTCOME_ABANDONED;
	}

	/**
	 * Flip a single row to abandoned unconditionally, then notify.
	 *
	 * Used by the manual admin action ({@see abandon()}), which intentionally
	 * overrides the idle check — an admin may abandon a cart on demand.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row The cart row.
	 * @return void
	 */
	private function mark_abandoned( array $row ): void {
		$id     = (int) ( $row['id'] ?? 0 );
		$fields = $this->abandoned_fields();

		CartSession::update( $id, $fields );

		$this->notify( array_merge( $row, $fields ), $id );
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
			// Open the follow-up cursor at the first step. A cart re-abandoned by
			// the admin action starts its plan again rather than resuming a
			// cursor left over from the cycle before.
			'followup_step'        => 0,
		);
	}

	/**
	 * Dispatch the abandonment event and open the cart's follow-up plan.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row The cart row, including the columns just written.
	 * @param int                  $id  The cart id.
	 * @return void
	 */
	private function notify( array $row, int $id ): void {
		$row['id'] = $id;

		$this->events->abandoned( $row );
		$this->followups->start( $row );
	}
}
