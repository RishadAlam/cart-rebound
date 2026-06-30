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
	 * Event dispatcher.
	 *
	 * @since 0.1.0
	 * @var EventDispatcher
	 */
	private $events;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param EventDispatcher $events Event dispatcher.
	 */
	public function __construct( EventDispatcher $events ) {
		$this->events = $events;
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
	 * }
	 * @return array<string, mixed>
	 */
	public function get_carts( array $args ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = (int) ( $args['per_page'] ?? 0 );
		$per_page = $per_page > 0 ? min( 100, $per_page ) : 20;
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $this->apply_filters( CartSession::query(), $args )
			->order_by( 'last_activity', 'DESC' )
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
		$statuses = array(
			CartSession::STATUS_ACTIVE,
			CartSession::STATUS_ABANDONED,
			CartSession::STATUS_RECOVERED,
			CartSession::STATUS_COMPLETED,
			CartSession::STATUS_LOST,
		);

		$counts = array();

		foreach ( $statuses as $status ) {
			$counts[ $status ] = CartSession::query()->where( 'status', '=', $status )->count();
		}

		$revenue = CartSession::query()->where( 'status', '=', CartSession::STATUS_RECOVERED )->sum( 'recovered_amount' );

		// Use purge-immune lifetime counters: the Janitor deletes unrecovered
		// abandoned carts, so live status counts would inflate the rate over time.
		$lifetime_abandoned = (int) get_option( EventDispatcher::OPTION_ABANDONED, 0 );
		$lifetime_recovered = (int) get_option( EventDispatcher::OPTION_RECOVERED, 0 );
		$rate               = $lifetime_abandoned > 0
			? round( ( $lifetime_recovered / $lifetime_abandoned ) * 100, 1 )
			: 0.0;

		return array(
			'counts'            => $counts,
			'recovered_revenue' => $revenue,
			'recovery_rate'     => $rate,
			'currency'          => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
		);
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
	 * Delete a cart row.
	 *
	 * @since 0.1.0
	 *
	 * @param int $id Cart id.
	 * @return bool
	 */
	public function delete_cart( int $id ): bool {
		return CartSession::delete( $id );
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
