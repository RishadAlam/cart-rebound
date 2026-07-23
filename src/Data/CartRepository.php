<?php
/**
 * Read/reporting API over cart sessions.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Data;

defined( 'ABSPATH' ) || exit;

use CartRebound\Events\EventDispatcher;
use CartRebound\Models\CartSession;
use CartRebound\Models\QueryBuilder;
use WC_Order;

/**
 * Public read API used by the admin UI and integrations.
 *
 * @since 0.1.0
 */
final class CartRepository {

	/**
	 * Columns the admin list may be sorted by.
	 *
	 * @since 0.1.0
	 * @var array<int, string>
	 */
	private const SORTABLE = array(
		'id',
		'email',
		'items_count',
		'cart_total',
		'status',
		'last_activity',
		'created_at',
		'order_id',
	);

	/**
	 * Maximum session rows scanned when tallying the product report.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	private const REPORT_SCAN_LIMIT = 2000;

	/**
	 * Event dispatcher.
	 *
	 * @since 0.1.0
	 * @var EventDispatcher
	 */
	private $events;

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
	 * @param EventDispatcher $events  Event dispatcher.
	 * @param CartDataCleaner $cleaner Privacy-aware cart deletion service.
	 */
	public function __construct( EventDispatcher $events, CartDataCleaner $cleaner ) {
		$this->events  = $events;
		$this->cleaner = $cleaner;
	}

	/**
	 * Get a filtered, paginated list of carts.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string|array<int, string> $status   Status filter.
	 *     @type string                    $email    Email substring filter.
	 *     @type int                       $page     1-based page number.
	 *     @type int                       $per_page Page size (max 100).
	 *     @type string                    $orderby  Sort column (whitelisted).
	 *     @type string                    $order    Sort direction (ASC|DESC).
	 * }
	 * @return array<string, mixed>
	 */
	public function get_carts( array $args ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = (int) ( $args['per_page'] ?? 0 );
		$per_page = $per_page > 0 ? min( 100, $per_page ) : 20;
		$offset   = ( $page - 1 ) * $per_page;

		$orderby = (string) ( $args['orderby'] ?? '' );
		$orderby = in_array( $orderby, self::SORTABLE, true ) ? $orderby : 'last_activity';
		$order   = 'ASC' === strtoupper( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';

		$rows = $this->apply_filters( CartSession::query(), $args )
			->order_by( $orderby, $order )
			->limit( $per_page )
			->offset( $offset )
			->get();

		$total = $this->apply_filters( CartSession::query(), $args )->count();

		return array(
			'items'    => array_map( array( $this, 'present' ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Get a single cart by id.
	 *
	 * @since 0.1.0
	 *
	 * @param int $id Cart id.
	 * @return array<string, mixed>|null
	 */
	public function get_cart( int $id ) {
		$row = CartSession::find( $id );

		return is_array( $row ) ? $this->present( $row ) : null;
	}

	/**
	 * Aggregate dashboard statistics.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_stats(): array {
		$counts = array_fill_keys( CartSession::STATUSES, 0 );

		global $wpdb;
		$table = ( new CartSession() )->get_table();

		// Single GROUP BY query instead of one COUNT(*) per status.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT status, COUNT(*) AS cnt FROM %i GROUP BY status', $table ),
			ARRAY_A
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status = (string) ( $row['status'] ?? '' );

				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ] = (int) ( $row['cnt'] ?? 0 );
				}
			}
		}

		$revenue = CartSession::query()->where( 'status', '=', CartSession::STATUS_RECOVERED )->sum( 'recovered_amount' );

		// Still-open money: what the carts sitting in `abandoned` are worth.
		$recoverable = CartSession::query()->where( 'status', '=', CartSession::STATUS_ABANDONED )->sum( 'cart_total' );

		// Use purge-immune lifetime counters: the Janitor deletes unrecovered
		// abandoned carts, so live status counts would inflate the rate over time.
		$lifetime_abandoned = (int) get_option( EventDispatcher::OPTION_ABANDONED, 0 );
		$lifetime_recovered = (int) get_option( EventDispatcher::OPTION_RECOVERED, 0 );
		$rate               = $lifetime_abandoned > 0
			? round( ( $lifetime_recovered / $lifetime_abandoned ) * 100, 1 )
			: 0.0;

		return array(
			'counts'              => $counts,
			'recovered_revenue'   => $revenue,
			'recoverable_revenue' => $recoverable,
			'recovery_rate'       => $rate,
			'currency'            => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
		);
	}

	/**
	 * Daily recovery time series for the dashboard chart.
	 *
	 * Points are bucketed on the UTC date a cart was abandoned (recoverable) or
	 * recovered (recovered) — the same UTC basis the rest of the plugin stores
	 * and renders. The window is zero-filled so quiet days still produce a point
	 * and the chart keeps an even time axis.
	 *
	 * @since 0.1.0
	 *
	 * @param int $days Window length in days, including today.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_timeseries( int $days ): array {
		$days  = max( 1, min( 180, $days ) );
		$start = gmdate( 'Y-m-d', (int) strtotime( '-' . ( $days - 1 ) . ' days' ) );
		$table = ( new CartSession() )->get_table();

		$abandoned = $this->daily_totals( $table, 'abandoned_at', 'cart_total', $start );
		$recovered = $this->daily_totals( $table, 'recovered_at', 'recovered_amount', $start );

		$series = array();

		for ( $offset = 0; $offset < $days; $offset++ ) {
			$date = gmdate( 'Y-m-d', (int) strtotime( $start . ' +' . $offset . ' days' ) );

			$series[] = array(
				'date'                => $date,
				'recoverable_revenue' => (float) ( $abandoned[ $date ]['sum'] ?? 0 ),
				'recovered_revenue'   => (float) ( $recovered[ $date ]['sum'] ?? 0 ),
				'abandoned'           => (int) ( $abandoned[ $date ]['count'] ?? 0 ),
				'recovered'           => (int) ( $recovered[ $date ]['count'] ?? 0 ),
			);
		}

		return $series;
	}

	/**
	 * Per-product abandonment/recovery tallies for the dashboard report.
	 *
	 * Line items live in the JSON cart snapshot rather than their own table, so
	 * the tally runs in PHP over a bounded slice of rows. Each cart counts at
	 * most once per distinct product, and the scan stops at
	 * {@see self::REPORT_SCAN_LIMIT} rows so a busy store cannot blow the
	 * request budget.
	 *
	 * @since 0.1.0
	 *
	 * @param int $days  Window length in days, including today.
	 * @param int $limit Maximum number of products returned.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_product_report( int $days, int $limit ): array {
		$days  = max( 1, min( 180, $days ) );
		$limit = max( 1, min( 50, $limit ) );
		$since = gmdate( 'Y-m-d', (int) strtotime( '-' . ( $days - 1 ) . ' days' ) ) . ' 00:00:00';
		$table = ( new CartSession() )->get_table();

		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT cart_contents, abandoned_at, recovered_at FROM %i WHERE abandoned_at >= %s OR recovered_at >= %s ORDER BY id DESC LIMIT %d',
				$table,
				$since,
				$since,
				self::REPORT_SCAN_LIMIT
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$tally = array();

		foreach ( $rows as $row ) {
			$abandoned_at = (string) ( $row['abandoned_at'] ?? '' );
			$recovered_at = (string) ( $row['recovered_at'] ?? '' );

			// Datetimes are stored as 'Y-m-d H:i:s', so a string compare is a
			// chronological compare — no parsing needed per row.
			$was_abandoned = '' !== $abandoned_at && $abandoned_at >= $since;
			$was_recovered = '' !== $recovered_at && $recovered_at >= $since;

			if ( ! $was_abandoned && ! $was_recovered ) {
				continue;
			}

			$seen = array();

			foreach ( $this->decode_products( $row ) as $product ) {
				$id = (int) ( $product['product_id'] ?? 0 );

				if ( 0 === $id || isset( $seen[ $id ] ) ) {
					continue;
				}

				$seen[ $id ] = true;

				if ( ! isset( $tally[ $id ] ) ) {
					$tally[ $id ] = array(
						'product_id' => $id,
						'name'       => (string) ( $product['name'] ?? '' ),
						'abandoned'  => 0,
						'recovered'  => 0,
					);
				}

				if ( $was_abandoned ) {
					++$tally[ $id ]['abandoned'];
				}

				if ( $was_recovered ) {
					++$tally[ $id ]['recovered'];
				}
			}
		}

		$products = array_values( $tally );

		usort(
			$products,
			static function ( array $a, array $b ): int {
				return ( (int) $b['abandoned'] + (int) $b['recovered'] ) <=> ( (int) $a['abandoned'] + (int) $a['recovered'] );
			}
		);

		return array_slice( $products, 0, $limit );
	}

	/**
	 * Group one timestamp column into per-day count/sum buckets.
	 *
	 * @since 0.1.0
	 *
	 * @param string $table  Sessions table name.
	 * @param string $column Datetime column to bucket on.
	 * @param string $amount Numeric column to sum.
	 * @param string $start  Inclusive start date (Y-m-d, UTC).
	 * @return array<string, array<string, float>>
	 */
	private function daily_totals( string $table, string $column, string $amount, string $start ): array {
		global $wpdb;

		// A NULL timestamp never satisfies `>=`, so unabandoned/unrecovered rows
		// drop out without an extra IS NOT NULL clause.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(%i) AS bucket, COUNT(*) AS cnt, SUM(%i) AS total FROM %i WHERE %i >= %s GROUP BY DATE(%i)',
				$column,
				$amount,
				$table,
				$column,
				$start . ' 00:00:00',
				$column
			),
			ARRAY_A
		);

		$buckets = array();

		if ( ! is_array( $rows ) ) {
			return $buckets;
		}

		foreach ( $rows as $row ) {
			$bucket = (string) ( $row['bucket'] ?? '' );

			if ( '' === $bucket ) {
				continue;
			}

			$buckets[ $bucket ] = array(
				'count' => (float) ( $row['cnt'] ?? 0 ),
				'sum'   => (float) ( $row['total'] ?? 0 ),
			);
		}

		return $buckets;
	}

	/**
	 * Manually mark a cart as recovered against an order.
	 *
	 * @since 0.1.0
	 *
	 * @param int $cart_id  Cart id.
	 * @param int $order_id Order id.
	 * @return bool
	 */
	public function mark_recovered( int $cart_id, int $order_id ): bool {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$row = CartSession::find( $cart_id );

		// Idempotent: never re-attribute a cart that is already linked to an order.
		if ( ! is_array( $row ) || (int) ( $row['order_id'] ?? 0 ) > 0 ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$updated = CartSession::update(
			$cart_id,
			array(
				'status'           => CartSession::STATUS_RECOVERED,
				'order_id'         => $order_id,
				'recovered_amount' => (float) $order->get_total(),
				'currency'         => $order->get_currency(),
				'recovered_at'     => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		if ( $updated ) {
			$fresh = CartSession::find( $cart_id );
			$this->events->recovered( is_array( $fresh ) ? $fresh : $row, $order, 'direct' );
		}

		return $updated;
	}

	/**
	 * Manually set a cart's lifecycle status, applying the matching timestamps.
	 *
	 * This is the pure-data transition used for every status except a manual
	 * abandon that should notify (that path runs through
	 * {@see \CartRebound\Cron\AbandonmentDetector::abandon()} so the event fires
	 * and the follow-up email is queued). No integration event is dispatched here.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $cart_id Cart id.
	 * @param string $status  Target status (must be a known lifecycle status).
	 * @return bool True when the row exists and the status is valid.
	 */
	public function update_status( int $cart_id, string $status ): bool {
		if ( ! in_array( $status, CartSession::STATUSES, true ) ) {
			return false;
		}

		$row = CartSession::find( $cart_id );

		if ( ! is_array( $row ) ) {
			return false;
		}

		if ( (string) ( $row['status'] ?? '' ) === $status ) {
			return true;
		}

		$now  = gmdate( 'Y-m-d H:i:s' );
		$data = array( 'status' => $status );

		switch ( $status ) {
			case CartSession::STATUS_ACTIVE:
				// Return the row to the live funnel so the detector can re-fire.
				$data['abandoned_at']         = null;
				$data['abandonment_notified'] = 0;
				break;
			case CartSession::STATUS_ABANDONED:
				if ( '' === (string) ( $row['abandoned_at'] ?? '' ) ) {
					$data['abandoned_at'] = $now;
				}
				$data['abandonment_notified'] = 1;
				break;
			case CartSession::STATUS_LOST:
				// Janitor uses abandoned_at as the unrecovered-retention clock.
				// A manually lost active cart may not have one yet.
				if ( '' === (string) ( $row['abandoned_at'] ?? '' ) ) {
					$data['abandoned_at'] = $now;
				}
				break;
			case CartSession::STATUS_RECOVERED:
				if ( '' === (string) ( $row['recovered_at'] ?? '' ) ) {
					$data['recovered_at'] = $now;
				}
				break;
			case CartSession::STATUS_COMPLETED:
				if ( '' === (string) ( $row['completed_at'] ?? '' ) ) {
					$data['completed_at'] = $now;
				}
				break;
		}

		return CartSession::update( $cart_id, $data );
	}

	/**
	 * Delete a cart row.
	 *
	 * @since 0.1.0
	 *
	 * @param int $id Cart id.
	 * @return bool
	 */
	public function delete_cart( int $id ): bool {
		$result = $this->cleaner->delete( array( $id ) );

		return $result['complete'] && $result['sessions_deleted'] > 0;
	}

	/**
	 * Delete many cart rows by id.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, int> $ids Cart ids.
	 * @return int Number of rows deleted.
	 */
	public function delete_carts( array $ids ): int {
		$result = $this->cleaner->delete( $ids );

		return $result['sessions_deleted'];
	}

	/**
	 * Apply status/email filters to a query builder.
	 *
	 * @since 0.1.0
	 *
	 * @param QueryBuilder         $query The builder to constrain.
	 * @param array<string, mixed> $args  Query arguments.
	 * @return QueryBuilder
	 */
	private function apply_filters( QueryBuilder $query, array $args ): QueryBuilder {
		$status = $args['status'] ?? '';

		if ( is_array( $status ) && array() !== $status ) {
			$query->where_in( 'status', $status );
		} elseif ( is_string( $status ) && '' !== $status ) {
			$query->where( 'status', '=', $status );
		}

		$email = isset( $args['email'] ) ? (string) $args['email'] : '';

		if ( '' !== $email ) {
			$query->where( 'email', 'LIKE', '%' . $email . '%' );
		}

		return $query;
	}

	/**
	 * Normalise a raw DB row into a typed API shape.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private function present( array $row ): array {
		return array(
			'id'               => (int) ( $row['id'] ?? 0 ),
			'session_key'      => (string) ( $row['session_key'] ?? '' ),
			'user_id'          => (int) ( $row['user_id'] ?? 0 ),
			'email'            => (string) ( $row['email'] ?? '' ),
			'first_name'       => (string) ( $row['first_name'] ?? '' ),
			'last_name'        => (string) ( $row['last_name'] ?? '' ),
			'phone'            => (string) ( $row['phone'] ?? '' ),
			'cart_total'       => (float) ( $row['cart_total'] ?? 0 ),
			'currency'         => (string) ( $row['currency'] ?? '' ),
			'items_count'      => (int) ( $row['items_count'] ?? 0 ),
			'status'           => (string) ( $row['status'] ?? '' ),
			'order_id'         => (int) ( $row['order_id'] ?? 0 ),
			'recovered_amount' => (float) ( $row['recovered_amount'] ?? 0 ),
			'created_at'       => (string) ( $row['created_at'] ?? '' ),
			'last_activity'    => (string) ( $row['last_activity'] ?? '' ),
			'abandoned_at'     => (string) ( $row['abandoned_at'] ?? '' ),
			'recovered_at'     => (string) ( $row['recovered_at'] ?? '' ),
			'completed_at'     => (string) ( $row['completed_at'] ?? '' ),
			'products'         => $this->decode_products( $row ),
			'coupons'          => $this->decode_coupons( $row ),
		);
	}

	/**
	 * Decode the stored cart snapshot into a flat product list.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<int, array<string, mixed>>
	 */
	private function decode_products( array $row ): array {
		$raw = ( isset( $row['cart_contents'] ) && is_string( $row['cart_contents'] ) )
			? json_decode( $row['cart_contents'], true )
			: array();

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$products = array();

		foreach ( $raw as $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}

			$products[] = array(
				'product_id' => (int) ( $line['product_id'] ?? 0 ),
				'name'       => (string) ( $line['name'] ?? '' ),
				'qty'        => (int) ( $line['quantity'] ?? 0 ),
				'total'      => (float) ( $line['line_total'] ?? 0 ),
			);
		}

		return $products;
	}

	/**
	 * Decode the stored coupon list.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<int, string>
	 */
	private function decode_coupons( array $row ): array {
		$raw = ( isset( $row['coupons'] ) && is_string( $row['coupons'] ) )
			? json_decode( $row['coupons'], true )
			: array();

		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_values( array_map( 'strval', $raw ) );
	}
}
