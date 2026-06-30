<?php
/**
 * Order to cart linking + recovery attribution.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Recovery;

defined( 'ABSPATH' ) || exit;

use CartRebound\Events\EventDispatcher;
use CartRebound\Models\CartSession;
use CartRebound\Tracking\SessionManager;
use WC_Order;

/**
 * Links an order to its originating cart by explicit meta — never fuzzy totals.
 *
 * Stamps the resolved cart id onto the order at creation, then resolves the cart
 * to `recovered` (abandoned cart or arrived via a recovery link) or `completed`
 * (a cart that converted without ever being abandoned).
 *
 * @since 0.1.0
 */
final class OrderLinker {

	/**
	 * Order meta key holding the linked cart row id.
	 *
	 * @var string
	 */
	public const META_CART = '_cart_rebound_session_id';

	/**
	 * Order meta flag set when the order came through a recovery link.
	 *
	 * @var string
	 */
	public const META_RECOVERED = '_cart_rebound_recovered';

	/**
	 * Event dispatcher.
	 *
	 * @since 0.1.0
	 * @var EventDispatcher
	 */
	private $events;

	/**
	 * Session key resolver.
	 *
	 * @since 0.1.0
	 * @var SessionManager
	 */
	private $sessions;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param EventDispatcher $events   Event dispatcher.
	 * @param SessionManager  $sessions Session key resolver.
	 */
	public function __construct( EventDispatcher $events, SessionManager $sessions ) {
		$this->events   = $events;
		$this->sessions = $sessions;
	}

	/**
	 * Stamp + link a newly created order.
	 *
	 * @since 0.1.0
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function on_order_created( int $order_id ): void {
		$order = $this->order( $order_id );

		if ( null === $order ) {
			return;
		}

		if ( '' === (string) $order->get_meta( self::META_CART ) ) {
			$resolved = $this->resolve_cart( $order );

			if ( $resolved['id'] > 0 ) {
				$order->update_meta_data( self::META_CART, (string) $resolved['id'] );

				if ( $resolved['via_link'] ) {
					$order->update_meta_data( self::META_RECOVERED, '1' );
				}

				$order->save();
			}

			// The recovery binding is single-use; drop it so a later unrelated
			// order can't be mis-attributed to this recovery cart.
			$this->clear_recovery_binding();
		}

		// Only transition the cart once the order is actually paid — a pending or
		// never-paid order must not prematurely complete/recover the cart.
		if ( $order->has_status( array( 'processing', 'completed' ) ) ) {
			$this->link( $order );
		}
	}

	/**
	 * Clear the single-use recovery-link binding from the WooCommerce session.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function clear_recovery_binding(): void {
		if ( function_exists( 'WC' ) && null !== WC()->session ) {
			WC()->session->set( RecoveryHandler::SESSION_CART_ID, null );
		}
	}

	/**
	 * Reconcile a stamped-but-unlinked order when it reaches a paid status.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $order_id Order id.
	 * @param string $from     Previous status.
	 * @param string $to       New status.
	 * @return void
	 */
	public function on_status_changed( int $order_id, string $from, string $to ): void {
		if ( ! in_array( $to, array( 'processing', 'completed' ), true ) ) {
			return;
		}

		$order = $this->order( $order_id );

		if ( null !== $order ) {
			$this->link( $order );
		}
	}

	/**
	 * Resolve the stamped cart to recovered or completed (idempotent).
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Order $order The order.
	 * @return void
	 */
	private function link( WC_Order $order ): void {
		$cart_id = (int) $order->get_meta( self::META_CART );

		if ( $cart_id <= 0 ) {
			return;
		}

		$row = CartSession::find( $cart_id );

		if ( ! is_array( $row ) || (int) ( $row['order_id'] ?? 0 ) > 0 ) {
			return;
		}

		$via_link     = '1' === (string) $order->get_meta( self::META_RECOVERED );
		$is_recovered = $via_link || CartSession::STATUS_ABANDONED === (string) ( $row['status'] ?? '' );

		if ( ! $is_recovered ) {
			CartSession::update(
				$cart_id,
				array(
					'status'       => CartSession::STATUS_COMPLETED,
					'order_id'     => $order->get_id(),
					'completed_at' => gmdate( 'Y-m-d H:i:s' ),
				)
			);

			return;
		}

		CartSession::update(
			$cart_id,
			array(
				'status'           => CartSession::STATUS_RECOVERED,
				'order_id'         => $order->get_id(),
				'recovered_amount' => (float) $order->get_total(),
				'currency'         => $order->get_currency(),
				'recovered_at'     => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		$fresh = CartSession::find( $cart_id );

		$this->events->recovered(
			is_array( $fresh ) ? $fresh : $row,
			$order,
			$via_link ? 'email_link' : 'direct'
		);
	}

	/**
	 * Resolve the originating cart for an order.
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Order $order The order.
	 * @return array{id: int, via_link: bool}
	 */
	private function resolve_cart( WC_Order $order ): array {
		if ( function_exists( 'WC' ) && null !== WC()->session ) {
			$bound    = WC()->session->get( RecoveryHandler::SESSION_CART_ID );
			$bound_id = is_numeric( $bound ) ? (int) $bound : 0;

			if ( $bound_id > 0 ) {
				return array(
					'id'       => $bound_id,
					'via_link' => true,
				);
			}
		}

		$open = array( CartSession::STATUS_ACTIVE, CartSession::STATUS_ABANDONED );

		$key = $this->sessions->resolve_session_key();

		if ( '' !== $key ) {
			$row = CartSession::query()
				->where( 'session_key', '=', $key )
				->where_in( 'status', $open )
				->order_by( 'last_activity', 'DESC' )
				->first();

			if ( is_array( $row ) ) {
				return array(
					'id'       => (int) ( $row['id'] ?? 0 ),
					'via_link' => false,
				);
			}
		}

		$customer_id = (int) $order->get_customer_id();

		if ( $customer_id > 0 ) {
			$row = CartSession::query()
				->where( 'user_id', '=', $customer_id )
				->where_in( 'status', $open )
				->order_by( 'last_activity', 'DESC' )
				->first();

			if ( is_array( $row ) ) {
				return array(
					'id'       => (int) ( $row['id'] ?? 0 ),
					'via_link' => false,
				);
			}
		}

		return array(
			'id'       => 0,
			'via_link' => false,
		);
	}

	/**
	 * Load a WooCommerce order.
	 *
	 * @since 0.1.0
	 *
	 * @param int $order_id Order id.
	 * @return WC_Order|null
	 */
	private function order( int $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return $order instanceof WC_Order ? $order : null;
	}
}
