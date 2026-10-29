<?php
/**
 * Order to cart linking + recovery attribution.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Recovery;

defined( 'ABSPATH' ) || exit;

use CartRebound\Cron\Scheduler;
use CartRebound\Events\EventDispatcher;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Models\CartSession;
use CartRebound\Support\Settings;
use CartRebound\Tracking\SessionManager;
use WC_Order;

/**
 * Links an order to its originating cart by explicit meta — never fuzzy totals.
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
	 * Job scheduler (used to cancel a pending recovery email on conversion).
	 *
	 * @since 0.1.0
	 * @var Scheduler
	 */
	private $scheduler;

	/**
	 * Recovery mailer (used for the optional admin recovery notification).
	 *
	 * @since 0.1.0
	 * @var RecoveryMailer
	 */
	private $mailer;

	/**
	 * Settings store (supplies the "counts as paid" order statuses).
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Per-request memo of the resolved paid-order-status list.
	 *
	 * @since 0.1.0
	 * @var array<int, string>|null
	 */
	private $paid_statuses_cache = null;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param EventDispatcher $events    Event dispatcher.
	 * @param SessionManager  $sessions  Session key resolver.
	 * @param Scheduler       $scheduler Job scheduler.
	 * @param RecoveryMailer  $mailer    Recovery mailer.
	 * @param Settings        $settings  Settings store.
	 */
	public function __construct( EventDispatcher $events, SessionManager $sessions, Scheduler $scheduler, RecoveryMailer $mailer, Settings $settings ) {
		$this->events    = $events;
		$this->sessions  = $sessions;
		$this->scheduler = $scheduler;
		$this->mailer    = $mailer;
		$this->settings  = $settings;
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

				// Mark pending payment before the order is paid so post-checkout empty-cart events keep
				// its items and no recovery email goes out; the paid transition happens in link().
				$this->mark_order_placed( $resolved['id'], $order );
			}

			// The recovery binding is single-use, so a later unrelated order can't be mis-attributed to this cart.
			$this->clear_recovery_binding();
		}

		if ( $order->has_status( $this->paid_statuses() ) ) {
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
		if ( in_array( $to, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			$this->on_reversal( $order_id );

			return;
		}

		if ( ! in_array( $to, $this->paid_statuses(), true ) ) {
			return;
		}

		$order = $this->order( $order_id );

		if ( null !== $order ) {
			$this->link( $order );
		}
	}

	/**
	 * WooCommerce order statuses that count as a paid conversion.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, string>
	 */
	private function paid_statuses(): array {
		if ( null !== $this->paid_statuses_cache ) {
			return $this->paid_statuses_cache;
		}

		$statuses = $this->settings->get( 'paid_order_statuses', array( 'processing', 'completed' ) );

		if ( ! is_array( $statuses ) || array() === $statuses ) {
			$statuses = array( 'processing', 'completed' );
		}

		/**
		 * Filter the order statuses that mark a tracked cart as paid/recovered.
		 *
		 * @since 0.1.0
		 *
		 * @param array<int, string> $statuses Order-status slugs (without the wc- prefix).
		 */
		$statuses = (array) apply_filters( 'cart_rebound_paid_order_statuses', $statuses );

		$this->paid_statuses_cache = array_values( array_map( 'strval', $statuses ) );

		return $this->paid_statuses_cache;
	}

	/**
	 * Undo a cart's conversion when its order is cancelled, fails, or is refunded.
	 *
	 * @since 0.1.0
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	private function on_reversal( int $order_id ): void {
		$order = $this->order( $order_id );

		if ( null === $order ) {
			return;
		}

		$cart_id = (int) $order->get_meta( self::META_CART );

		if ( $cart_id <= 0 ) {
			return;
		}

		$row = CartSession::find( $cart_id );

		if ( ! is_array( $row ) ) {
			return;
		}

		$status = (string) ( $row['status'] ?? '' );

		if ( CartSession::STATUS_PENDING_PAYMENT === $status ) {
			// Keep `abandoned_at` so a later paid order still attributes as recovered.
			$reopened = array(
				'status'               => CartSession::STATUS_ACTIVE,
				'order_id'             => 0,
				'abandonment_notified' => 0,
			);

			if ( CartSession::update( $cart_id, $reopened ) ) {
				$this->events->status_changed( array_merge( $row, $reopened ), $status );
			}

			return;
		}

		if ( $this->already_converted( $status ) ) {
			$lost = array(
				'status'           => CartSession::STATUS_LOST,
				'recovered_amount' => 0,
				'order_id'         => 0,
			);

			if ( CartSession::update( $cart_id, $lost ) ) {
				$this->events->status_changed( array_merge( $row, $lost ), $status );

				if ( CartSession::STATUS_RECOVERED === $status ) {
					$this->events->recovery_reversed();
				}
			}

			$order->add_order_note(
				sprintf(
					/* translators: %d: tracked cart id. */
					__( 'Cart Rebound: order reversed; tracked cart #%d moved to lost.', 'cart-rebound' ),
					$cart_id
				)
			);
		}
	}

	/**
	 * Resolve the stamped cart once its order is paid (idempotent).
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

		// Idempotent on paid states, not order_id: the id is stamped at order creation,
		// so a pending-payment row still needs its paid transition here.
		if ( ! is_array( $row ) || $this->already_converted( (string) ( $row['status'] ?? '' ) ) ) {
			return;
		}

		$this->scheduler->clear_with_args( RecoveryMailer::HOOK, array( $cart_id ) );

		$via_link      = '1' === (string) $order->get_meta( self::META_RECOVERED );
		$was_abandoned = '' !== (string) ( $row['abandoned_at'] ?? '' );
		$is_recovered  = $via_link || $was_abandoned;

		if ( ! $is_recovered ) {
			$this->complete( $order, $row );
			$this->sessions->clear();

			return;
		}

		$recovered = array(
			'status'           => CartSession::STATUS_RECOVERED,
			'order_id'         => $order->get_id(),
			'recovered_amount' => (float) $order->get_total(),
			'currency'         => $order->get_currency(),
			'recovered_at'     => gmdate( 'Y-m-d H:i:s' ),
		);

		$saved = CartSession::update( $cart_id, $recovered );

		$order->add_order_note(
			sprintf(
				/* translators: 1: tracked cart id, 2: recovery method. */
				__( 'Cart Rebound: recovered cart #%1$d via %2$s.', 'cart-rebound' ),
				$cart_id,
				$via_link ? __( 'email link', 'cart-rebound' ) : __( 'direct return', 'cart-rebound' )
			)
		);

		$fresh = array_merge( $row, $recovered );

		if ( $saved ) {
			$this->events->status_changed( $fresh, (string) ( $row['status'] ?? '' ) );
		}

		$this->events->recovered( $fresh, $order, $via_link ? 'email_link' : 'direct' );
		$this->mailer->notify_admin( $fresh, $order );

		$this->sessions->clear();
	}

	/**
	 * Resolve a cart that converted to a paid order without being abandoned.
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Order             $order The paid order.
	 * @param array<string, mixed> $row   The cart row.
	 * @return void
	 */
	private function complete( WC_Order $order, array $row ): void {
		$cart_id   = (int) ( $row['id'] ?? 0 );
		$completed = array(
			'status'       => CartSession::STATUS_COMPLETED,
			'order_id'     => $order->get_id(),
			'completed_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		if ( CartSession::update( $cart_id, $completed ) ) {
			$this->events->status_changed( array_merge( $row, $completed ), (string) ( $row['status'] ?? '' ) );
		}

		$order->add_order_note(
			sprintf(
				/* translators: %d: tracked cart id. */
				__( 'Cart Rebound: linked to tracked cart #%d (completed).', 'cart-rebound' ),
				$cart_id
			)
		);
	}

	/**
	 * Stamp a newly placed order onto its cart row as pending payment.
	 *
	 * @since 0.1.0
	 *
	 * @param int      $cart_id Cart id.
	 * @param WC_Order $order   The order just placed.
	 * @return void
	 */
	private function mark_order_placed( int $cart_id, WC_Order $order ): void {
		$row = CartSession::find( $cart_id );

		if ( ! is_array( $row ) ) {
			return;
		}

		$open = in_array(
			(string) ( $row['status'] ?? '' ),
			array( CartSession::STATUS_ACTIVE, CartSession::STATUS_ABANDONED ),
			true
		);

		if ( ! $open || (int) ( $row['order_id'] ?? 0 ) > 0 ) {
			return;
		}

		$placed = array(
			'status'   => CartSession::STATUS_PENDING_PAYMENT,
			'order_id' => $order->get_id(),
		);

		$saved = CartSession::update( $cart_id, $placed );

		$this->scheduler->clear_with_args( RecoveryMailer::HOOK, array( $cart_id ) );

		if ( ! $saved ) {
			return;
		}

		$linked = array_merge( $row, $placed );

		$this->events->status_changed( $linked, (string) ( $row['status'] ?? '' ) );
		$this->events->order_linked( $linked, $order );
	}

	/**
	 * Whether a status is a paid, already-attributed terminal state.
	 *
	 * @since 0.1.0
	 *
	 * @param string $status Status value.
	 * @return bool
	 */
	private function already_converted( string $status ): bool {
		return CartSession::STATUS_RECOVERED === $status || CartSession::STATUS_COMPLETED === $status;
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

		$key = $this->sessions->existing_session_key();

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
			/**
			 * Recency window (in days) for matching a customer's earlier cart to a
			 * new order by customer id.
			 *
			 * @since 0.1.0
			 *
			 * @param int $days Window in days.
			 */
			$window = (int) apply_filters( 'cart_rebound_recovery_match_window_days', 90 );
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $window ) * DAY_IN_SECONDS ) );

			$row = CartSession::query()
				->where( 'user_id', '=', $customer_id )
				->where_in( 'status', $open )
				->where( 'last_activity', '>', $cutoff )
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
