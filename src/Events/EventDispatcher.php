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
