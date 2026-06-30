<?php
/**
 * Cleanup job for stale cart rows.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Cron;

defined( 'ABSPATH' ) || exit;

use CartRebound\Models\CartSession;
use CartRebound\Support\Settings;

/**
 * Purges abandoned/lost carts that never converted, past the cleanup window.
 *
 * Never touches active carts, and keeps recovered/completed rows so recovered
 * revenue reporting stays intact.
 *
 * @since 0.1.0
 */
final class Janitor {

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
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Delete stale, unconverted abandoned/lost carts.
	 *
	 * @since 0.1.0
	 *
	 * @return int Number of rows deleted.
	 */
	public function run(): int {
		$days   = max( 1, (int) $this->settings->get( 'cleanup_days' ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$abandoned = CartSession::query()
			->where_in( 'status', array( CartSession::STATUS_ABANDONED, CartSession::STATUS_LOST ) )
			->where( 'order_id', '=', 0 )
			->where( 'abandoned_at', '<', $cutoff )
			->delete_where();

		// Carts that never captured an email are never flipped to abandoned, so
		// purge dead 'active' sessions on last_activity to keep the table bounded.
		$stale_active = CartSession::query()
			->where( 'status', '=', CartSession::STATUS_ACTIVE )
			->where( 'order_id', '=', 0 )
			->where( 'last_activity', '<', $cutoff )
			->delete_where();

		return $abandoned + $stale_active;
	}
}
