<?php
/**
 * Cleanup job for stale cart rows.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Cron;

defined( 'ABSPATH' ) || exit;

use CartRebound\Data\CartDataCleaner;
use CartRebound\Models\CartSession;
use CartRebound\Models\QueryBuilder;
use CartRebound\Support\Settings;

/**
 * Purges stale cart data after the configured retention windows.
 *
 * @since 0.1.0
 */
final class Janitor {

	/**
	 * Rows deleted per database batch.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 100;

	/**
	 * Daily cleanup action hook.
	 *
	 * @var string
	 */
	public const HOOK = 'cart_rebound_cleanup_sessions';

	/**
	 * Settings store.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Privacy-aware cart deletion service.
	 *
	 * @since 0.1.0
	 * @var CartDataCleaner
	 */
	private $cleaner;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Settings        $settings Settings store.
	 * @param CartDataCleaner $cleaner  Privacy-aware cart deletion service.
	 */
	public function __construct( Settings $settings, CartDataCleaner $cleaner ) {
		$this->settings = $settings;
		$this->cleaner  = $cleaner;
	}

	/**
	 * Delete stale carts and associated logs after their retention windows.
	 *
	 * @since 0.1.0
	 *
	 * @return int Number of rows deleted.
	 */
	public function run(): int {
		$unrecovered_days   = max( 1, (int) $this->settings->get( 'cleanup_days' ) );
		$converted_days     = max( 1, (int) $this->settings->get( 'converted_cleanup_days' ) );
		$unrecovered_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $unrecovered_days * DAY_IN_SECONDS ) );
		$converted_cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $converted_days * DAY_IN_SECONDS ) );

		$deleted = $this->purge(
			CartSession::query()
			->where_in( 'status', array( CartSession::STATUS_ABANDONED, CartSession::STATUS_LOST ) )
			->where( 'order_id', '=', 0 )
			->where( 'abandoned_at', '<', $unrecovered_cutoff )
		);

		// Carts that never captured an email are never flipped to abandoned, so
		// purge dead 'active' sessions on last_activity to keep the table bounded.
		$deleted += $this->purge(
			CartSession::query()
			->where( 'status', '=', CartSession::STATUS_ACTIVE )
			->where( 'order_id', '=', 0 )
			->where( 'last_activity', '<', $unrecovered_cutoff )
		);

		$deleted += $this->purge(
			CartSession::query()
				->where( 'status', '=', CartSession::STATUS_RECOVERED )
				->where( 'recovered_at', '<', $converted_cutoff )
		);

		$deleted += $this->purge(
			CartSession::query()
				->where( 'status', '=', CartSession::STATUS_COMPLETED )
				->where( 'completed_at', '<', $converted_cutoff )
		);

		// Orders placed but never paid (e.g. cheque / bank transfer left unpaid)
		// stay in pending payment; retire them on the converted-retention window
		// so the table stays bounded.
		$deleted += $this->purge(
			CartSession::query()
				->where( 'status', '=', CartSession::STATUS_PENDING_PAYMENT )
				->where( 'last_activity', '<', $converted_cutoff )
		);

		// A paid conversion that was later reversed (refund/cancel) drops to `lost`
		// with no abandonment clock, so the abandoned/lost sweep above (keyed on
		// abandoned_at) can miss it; retire lost carts on last activity as well.
		$deleted += $this->purge(
			CartSession::query()
				->where( 'status', '=', CartSession::STATUS_LOST )
				->where( 'last_activity', '<', $unrecovered_cutoff )
		);

		return $deleted;
	}

	/**
	 * Delete all rows matched by a query in bounded batches.
	 *
	 * @since 0.1.0
	 *
	 * @param QueryBuilder $query Constrained cart query.
	 * @return int Number of cart-session rows deleted.
	 */
	private function purge( QueryBuilder $query ): int {
		$deleted = 0;

		do {
			$rows       = $query
				->order_by( 'id', 'ASC' )
				->limit( self::BATCH_SIZE )
				->get();
			$batch_size = count( $rows );

			$ids = array_map(
				static function ( array $row ): int {
					return (int) ( $row['id'] ?? 0 );
				},
				$rows
			);

			if ( array() === $ids ) {
				break;
			}

			$result   = $this->cleaner->delete( $ids );
			$deleted += $result['sessions_deleted'];

			if ( ! $result['complete'] || 0 === $result['sessions_deleted'] ) {
				break;
			}
		} while ( self::BATCH_SIZE === $batch_size );

		return $deleted;
	}
}
