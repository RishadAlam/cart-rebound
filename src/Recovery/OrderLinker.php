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

				// Move the cart to "pending payment" as soon as the order exists —
				// before it is paid — so the post-checkout empty-cart events keep its
				// captured items and it drops out of the active/abandoned funnel (no
				// recovery email for a shopper who already ordered). The paid
				// transition to recovered/completed happens later in link().
				$this->mark_order_placed( $resolved['id'], $order_id );
			}

			// The recovery binding is single-use; drop it so a later unrelated
			// order can't be mis-attributed to this recovery cart.
			$this->clear_recovery_binding();
		}

		// Only transition the cart once the order is actually paid — a pending or
		// never-paid order must not prematurely complete/recover the cart.
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
	 * Configurable via the `paid_order_statuses` setting (default
	 * processing + completed) and the `cart_rebound_paid_order_statuses` filter.
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
	 * - A pending-payment cart (order placed, never paid) returns to `active` with
	 *   its items intact so it can be recovered like any abandoned cart.
	 * - A recovered/completed cart (a paid order that was reversed) drops to `lost`
	 *   with its recovered revenue cleared, so reporting never counts money that
	 *   was refunded. Other states are left untouched.
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
			CartSession::update(
				$cart_id,
				array(
					'status'               => CartSession::STATUS_ACTIVE,
					'order_id'             => 0,
					'abandoned_at'         => null,
					'abandonment_notified' => 0,
				)
			);

			return;
		}

		if ( $this->already_converted( $status ) ) {
			CartSession::update(
				$cart_id,
				array(
					'status'           => CartSession::STATUS_LOST,
					'recovered_amount' => 0,
					'order_id'         => 0,
				)
			);

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
	 * A previously abandoned/lost cart — or one that arrived through a recovery
	 * link — becomes `recovered`; anything else converted straight through. Either
	 * way the just-converted cart never needs its pending recovery email, so that
	 * scheduled job is cancelled, an order note is written, and the visitor's
	 * tracking session is retired so their next cart opens a fresh row.
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

		// Idempotent on the paid lifecycle states, not on order_id: the id is
		// stamped at order creation (see mark_order_placed()), so a pending-payment
		// row still needs its paid transition here.
		if ( ! is_array( $row ) || $this->already_converted( (string) ( $row['status'] ?? '' ) ) ) {
			return;
		}

		// The cart converted: drop any recovery email still queued for it.
		$this->scheduler->clear_with_args( RecoveryMailer::HOOK, array( $cart_id ) );

		// A cart that was ever abandoned (its abandoned_at survives the
		// pending-payment transition) — or that arrived via a recovery link —
		// counts as recovered; a pristine cart converted straight through.
		$via_link      = '1' === (string) $order->get_meta( self::META_RECOVERED );
		$was_abandoned = '' !== (string) ( $row['abandoned_at'] ?? '' );
		$is_recovered  = $via_link || $was_abandoned;

		if ( ! $is_recovered ) {
			$this->complete( $order, $cart_id );
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

		CartSession::update( $cart_id, $recovered );

		$order->add_order_note(
			sprintf(
				/* translators: 1: tracked cart id, 2: recovery method. */
				__( 'Cart Rebound: recovered cart #%1$d via %2$s.', 'cart-rebound' ),
				$cart_id,
				$via_link ? __( 'email link', 'cart-rebound' ) : __( 'direct return', 'cart-rebound' )
			)
		);

		// The payload is the row we already hold plus the columns just written —
		// no need to re-read the row we just saved.
		$fresh = array_merge( $row, $recovered );

		$this->events->recovered( $fresh, $order, $via_link ? 'email_link' : 'direct' );
		$this->mailer->notify_admin( $fresh, $order );

		$this->sessions->clear();
	}

	/**
	 * Resolve a cart that converted to a paid order without being abandoned.
	 *
	 * Kept as `completed` (not deleted) so a straight-through paid conversion
	 * stays visible in the list and its revenue is counted, mirroring the
	 * recovered path for abandoned carts.
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Order $order   The paid order.
	 * @param int      $cart_id The cart id.
	 * @return void
	 */
	private function complete( WC_Order $order, int $cart_id ): void {
		CartSession::update(
			$cart_id,
			array(
				'status'       => CartSession::STATUS_COMPLETED,
				'order_id'     => $order->get_id(),
				'completed_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

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
	 * Runs as soon as the order is created (before payment) so the cart keeps its
	 * captured items through the post-checkout empty-cart events and leaves the
	 * active/abandoned funnel. Only an open cart is moved; a row already resolved
	 * (paid/lost) or already linked to an order is left untouched.
	 *
	 * @since 0.1.0
	 *
	 * @param int $cart_id  Cart id.
	 * @param int $order_id Order id.
	 * @return void
	 */
	private function mark_order_placed( int $cart_id, int $order_id ): void {
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

		CartSession::update(
			$cart_id,
			array(
				'status'   => CartSession::STATUS_PENDING_PAYMENT,
				'order_id' => $order_id,
			)
		);

		// They placed an order — drop any recovery email still queued for this cart.
		$this->scheduler->clear_with_args( RecoveryMailer::HOOK, array( $cart_id ) );
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
			/**
			 * Recency window (in days) for matching a customer's earlier cart to a
			 * new order by customer id. Guards against crediting an ancient cart to
			 * an unrelated later purchase from the same account.
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
