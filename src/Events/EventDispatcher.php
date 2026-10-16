<?php
/**
 * Event dispatcher for cart abandonment / recovery.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Events;

defined( 'ABSPATH' ) || exit;

use CartRebound\Recovery\RecoveryLink;
use WC_Order;

/**
 * Fires the plugin's public integration events with a flat, mappable payload.
 *
 * Automation tools (FlowMattic, Bit Integrations, etc.) hook these actions
 * instead of coupling to the plugin internals.
 *
 * @since 0.1.0
 */
final class EventDispatcher {

	/**
	 * Lifetime abandoned-cart counter option (purge-immune; used for recovery rate).
	 *
	 * @var string
	 */
	public const OPTION_ABANDONED = 'cart_rebound_lifetime_abandoned';

	/**
	 * Lifetime recovered-cart counter option.
	 *
	 * @var string
	 */
	public const OPTION_RECOVERED = 'cart_rebound_lifetime_recovered';

	/**
	 * Recovery link builder.
	 *
	 * @since 0.1.0
	 * @var RecoveryLink
	 */
	private $links;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param RecoveryLink $links Recovery link builder.
	 */
	public function __construct( RecoveryLink $links ) {
		$this->links = $links;
	}

	/**
	 * Fire the cart-abandoned event.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row The cart session row.
	 * @return void
	 */
	public function abandoned( array $row ): void {
		$payload = $this->base_payload( $row );

		do_action( 'cart_rebound_abandoned', $payload );

		$this->increment( self::OPTION_ABANDONED );
	}

	/**
	 * Fire the cart-recovered event.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row    The cart session row.
	 * @param WC_Order             $order  The order that recovered the cart.
	 * @param string               $method Recovery method: 'email_link' or 'direct'.
	 * @return void
	 */
	public function recovered( array $row, WC_Order $order, string $method ): void {
		$payload                     = $this->base_payload( $row );
		$payload['order_id']         = $order->get_id();
		$payload['recovered_amount'] = isset( $row['recovered_amount'] ) ? (float) $row['recovered_amount'] : (float) $order->get_total();
		$payload['recovered_at']     = (string) ( $row['recovered_at'] ?? '' );
		$payload['recovery_method']  = $method;

		do_action( 'cart_rebound_recovered', $payload );

		$this->increment( self::OPTION_RECOVERED );
	}

	/**
	 * Fire the cart-created event.
	 *
	 * @since 1.1.2
	 *
	 * @param array<string, mixed> $row The newly tracked cart row.
	 * @return void
	 */
	public function created( array $row ): void {
		/**
		 * Fires when a new cart starts being tracked.
		 *
		 * Guest carts have no email until checkout captures it; listen to
		 * `cart_rebound_cart_captured` for carts that can be recovered.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload Flat cart payload.
		 */
		do_action( 'cart_rebound_cart_created', $this->base_payload( $row ) );
	}

	/**
	 * Fire the cart-captured event.
	 *
	 * Fires when a cart first holds a contact email, or when its contact details
	 * change, so automations hear about every cart that can be recovered.
	 *
	 * @since 1.1.2
	 *
	 * @param array<string, mixed> $row The cart row after the capture.
	 * @return void
	 */
	public function captured( array $row ): void {
		if ( '' === (string) ( $row['email'] ?? '' ) ) {
			return;
		}

		/**
		 * Fires when a cart's contact details are captured or change.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload Flat cart payload.
		 */
		do_action( 'cart_rebound_cart_captured', $this->base_payload( $row ) );
	}

	/**
	 * Fire the cart status-change events.
	 *
	 * @since 1.1.2
	 *
	 * @param array<string, mixed> $row      The cart row after the change.
	 * @param string               $previous The status before the change.
	 * @return void
	 */
	public function status_changed( array $row, string $previous ): void {
		$status = (string) ( $row['status'] ?? '' );

		if ( '' === $status || $status === $previous ) {
			return;
		}

		$payload                    = $this->base_payload( $row );
		$payload['previous_status'] = $previous;

		/**
		 * Fires when a cart moves into one specific status.
		 *
		 * The dynamic part is the new status: active, abandoned, pending-payment,
		 * recovered, completed or lost.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload Flat cart payload, including `previous_status`.
		 */
		do_action( "cart_rebound_cart_status_{$status}", $payload );

		/**
		 * Fires whenever a cart's status changes.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload Flat cart payload, including `previous_status`.
		 */
		do_action( 'cart_rebound_cart_status_changed', $payload );
	}

	/**
	 * Fire the recovery-email-sent events.
	 *
	 * Also fires the coupon event when the email carried a coupon code.
	 *
	 * @since 1.1.2
	 *
	 * @param array<string, mixed> $row      The cart row the email was sent for.
	 * @param array<string, mixed> $template The template that was sent.
	 * @param string               $trigger  'automatic' (scheduled) or 'manual' (sent from the admin).
	 * @return void
	 */
	public function email_sent( array $row, array $template, string $trigger ): void {
		$payload                  = $this->base_payload( $row );
		$payload['template_id']   = (string) ( $template['id'] ?? '' );
		$payload['template_name'] = (string) ( $template['name'] ?? '' );
		$payload['coupon_code']   = (string) ( $template['coupon'] ?? '' );
		$payload['trigger']       = $trigger;
		$payload['sent_at']       = gmdate( 'Y-m-d H:i:s' );

		/**
		 * Fires after a recovery email is handed to wp_mail successfully.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload Flat cart payload plus template, coupon and trigger details.
		 */
		do_action( 'cart_rebound_recovery_email_sent', $payload );

		if ( '' === $payload['coupon_code'] ) {
			return;
		}

		$payload['coupon_id'] = function_exists( 'wc_get_coupon_id_by_code' )
			? (int) wc_get_coupon_id_by_code( $payload['coupon_code'] )
			: 0;

		/**
		 * Fires after a recovery email carrying a coupon code is sent.
		 *
		 * The coupon is the existing WooCommerce coupon chosen on the template;
		 * Cart Rebound does not generate coupons.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload The recovery-email payload plus `coupon_id`.
		 */
		do_action( 'cart_rebound_recovery_coupon_sent', $payload );
	}

	/**
	 * Fire the recovery-email-failed event.
	 *
	 * @since 1.1.2
	 *
	 * @param array<string, mixed> $row      The cart row the email was for.
	 * @param array<string, mixed> $template The template that failed to send.
	 * @param string               $trigger  'automatic' (scheduled) or 'manual' (sent from the admin).
	 * @return void
	 */
	public function email_failed( array $row, array $template, string $trigger ): void {
		$payload                  = $this->base_payload( $row );
		$payload['template_id']   = (string) ( $template['id'] ?? '' );
		$payload['template_name'] = (string) ( $template['name'] ?? '' );
		$payload['trigger']       = $trigger;

		/**
		 * Fires when wp_mail rejects a recovery email.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload Flat cart payload plus template and trigger details.
		 */
		do_action( 'cart_rebound_recovery_email_failed', $payload );
	}

	/**
	 * Fire the recovery-link-clicked event.
	 *
	 * @since 1.1.2
	 *
	 * @param array<string, mixed> $row The cart row the link restored.
	 * @return void
	 */
	public function link_clicked( array $row ): void {
		$payload               = $this->base_payload( $row );
		$payload['clicked_at'] = gmdate( 'Y-m-d H:i:s' );

		/**
		 * Fires when a shopper opens a recovery link and their cart is restored.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload Flat cart payload plus `clicked_at`.
		 */
		do_action( 'cart_rebound_recovery_link_clicked', $payload );
	}

	/**
	 * Fire the order-linked event.
	 *
	 * @since 1.1.2
	 *
	 * @param array<string, mixed> $row   The cart row after the order was stamped on it.
	 * @param WC_Order             $order The order placed from the cart.
	 * @return void
	 */
	public function order_linked( array $row, WC_Order $order ): void {
		$payload                 = $this->base_payload( $row );
		$payload['order_id']     = $order->get_id();
		$payload['order_total']  = (float) $order->get_total();
		$payload['order_status'] = (string) $order->get_status();

		/**
		 * Fires when an order is placed from a tracked cart, before it is paid.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload Flat cart payload plus order details.
		 */
		do_action( 'cart_rebound_order_linked', $payload );
	}

	/**
	 * Fire the unsubscribed event.
	 *
	 * @since 1.1.2
	 *
	 * @param array<string, mixed> $row The cart row whose unsubscribe link was used.
	 * @return void
	 */
	public function unsubscribed( array $row ): void {
		$payload                    = $this->base_payload( $row );
		$payload['unsubscribed_at'] = gmdate( 'Y-m-d H:i:s' );

		/**
		 * Fires when a shopper unsubscribes from recovery emails.
		 *
		 * @since 1.1.2
		 *
		 * @param array<string, mixed> $payload Flat cart payload plus `unsubscribed_at`.
		 */
		do_action( 'cart_rebound_unsubscribed', $payload );
	}

	/**
	 * Increment a lifetime counter option.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key Option key.
	 * @return void
	 */
	private function increment( string $key ): void {
		update_option( $key, (int) get_option( $key, 0 ) + 1, false );
	}

	/**
	 * Build the shared, flat payload from a cart row.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row The cart session row.
	 * @return array<string, mixed>
	 */
	private function base_payload( array $row ): array {
		return array(
			'cart_id'          => (int) ( $row['id'] ?? 0 ),
			'session_id'       => (string) ( $row['session_key'] ?? '' ),
			'customer_id'      => (int) ( $row['user_id'] ?? 0 ),
			'customer_email'   => (string) ( $row['email'] ?? '' ),
			'first_name'       => (string) ( $row['first_name'] ?? '' ),
			'last_name'        => (string) ( $row['last_name'] ?? '' ),
			'phone'            => (string) ( $row['phone'] ?? '' ),
			'cart_total'       => (float) ( $row['cart_total'] ?? 0 ),
			'currency'         => (string) ( $row['currency'] ?? '' ),
			'cart_items_count' => (int) ( $row['items_count'] ?? 0 ),
			'status'           => (string) ( $row['status'] ?? '' ),
			'order_id'         => (int) ( $row['order_id'] ?? 0 ),
			'products'         => $this->products( $row ),
			'checkout_url'     => (string) ( $row['checkout_url'] ?? '' ),
			'recovery_url'     => $this->links->url( (string) ( $row['recovery_token'] ?? '' ) ),
			'last_activity'    => (string) ( $row['last_activity'] ?? '' ),
		);
	}

	/**
	 * Decode the stored cart snapshot into a flat product list.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row The cart session row.
	 * @return array<int, array<string, mixed>>
	 */
	private function products( array $row ): array {
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
				'price'      => (float) ( $line['price'] ?? 0 ),
				'total'      => (float) ( $line['line_total'] ?? 0 ),
			);
		}

		return $products;
	}
}
