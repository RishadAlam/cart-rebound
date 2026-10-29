<?php
/**
 * Recovery link handler.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Recovery;

defined( 'ABSPATH' ) || exit;

use CartRebound\Events\EventDispatcher;
use CartRebound\Models\CartSession;
use CartRebound\Tracking\CartTracker;
use CartRebound\Tracking\SessionManager;

/**
 * Intercepts a tokenised recovery link, rebuilds the cart, and redirects to checkout.
 *
 * The unguessable token is the credential; no nonce applies, like a password-reset link.
 *
 * @since 0.1.0
 */
final class RecoveryHandler {

	/**
	 * WooCommerce session key binding the recovered cart row id.
	 *
	 * @var string
	 */
	public const SESSION_CART_ID = 'cart_rebound_recovery_cart_id';

	/**
	 * Session key resolver.
	 *
	 * @since 1.1.2
	 * @var SessionManager
	 */
	private $sessions;

	/**
	 * Event dispatcher.
	 *
	 * @since 1.1.2
	 * @var EventDispatcher
	 */
	private $events;

	/**
	 * Cart tracker.
	 *
	 * @since 1.1.2
	 * @var CartTracker
	 */
	private $tracker;

	/**
	 * Constructor.
	 *
	 * @since 1.1.2
	 *
	 * @param SessionManager  $sessions Session key resolver.
	 * @param EventDispatcher $events   Event dispatcher.
	 * @param CartTracker     $tracker  Cart tracker.
	 */
	public function __construct( SessionManager $sessions, EventDispatcher $events, CartTracker $tracker ) {
		$this->sessions = $sessions;
		$this->events   = $events;
		$this->tracker  = $tracker;
	}

	/**
	 * Handle a possible recovery request on template_redirect.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- recovery link is authenticated by an unguessable token, not a nonce.
		$flag = isset( $_GET[ RecoveryLink::QUERY_FLAG ] ) ? sanitize_text_field( wp_unslash( $_GET[ RecoveryLink::QUERY_FLAG ] ) ) : '';

		if ( '1' !== $flag ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token-authenticated recovery link; value sanitised inline.
		$token = isset( $_GET[ RecoveryLink::QUERY_TOKEN ] ) ? sanitize_text_field( wp_unslash( $_GET[ RecoveryLink::QUERY_TOKEN ] ) ) : '';

		if ( '' === $token ) {
			return;
		}

		$row = CartSession::query()
			->where( 'recovery_token', '=', $token )
			->where_in( 'status', array( CartSession::STATUS_ACTIVE, CartSession::STATUS_ABANDONED ) )
			->first();

		if ( ! is_array( $row ) ) {
			$this->notice( __( 'This cart link has expired, or its order was already placed.', 'cart-rebound' ) );

			return;
		}

		// Adopt the row before the rebuild: CartTracker runs on those writes and would open a duplicate row.
		$this->adopt_session( (int) ( $row['id'] ?? 0 ) );

		$restored = $this->tracker->without_tracking(
			function () use ( $row ): bool {
				return $this->restore_cart( $row );
			}
		);

		if ( ! $restored ) {
			return;
		}

		$this->tracker->track();
		$this->prefill_customer( $row );

		$fresh = CartSession::find( (int) ( $row['id'] ?? 0 ) );

		$this->events->link_clicked( is_array( $fresh ) ? $fresh : $row );

		if ( function_exists( 'WC' ) && null !== WC()->session ) {
			WC()->session->set( self::SESSION_CART_ID, (int) ( $row['id'] ?? 0 ) );
		}

		$checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' );

		wp_safe_redirect( $checkout );
		exit;
	}

	/**
	 * Re-key the recovered row onto this visitor's tracking session.
	 *
	 * Any row already holding the key is archived first so the UNIQUE index is respected.
	 *
	 * @since 1.1.2
	 *
	 * @param int $cart_id The recovered cart row id.
	 * @return void
	 */
	private function adopt_session( int $cart_id ): void {
		if ( $cart_id <= 0 || ! $this->tracker->is_enabled() ) {
			return;
		}

		$key = $this->sessions->resolve_session_key();

		if ( '' === $key ) {
			return;
		}

		$holder = CartSession::query()->where( 'session_key', '=', $key )->first();

		if ( is_array( $holder ) ) {
			$holder_id = (int) ( $holder['id'] ?? 0 );

			if ( $holder_id === $cart_id ) {
				return;
			}

			CartSession::update(
				$holder_id,
				array( 'session_key' => hash( 'sha256', $key . '|archived|' . $holder_id ) )
			);
		}

		CartSession::update( $cart_id, array( 'session_key' => $key ) );
	}

	/**
	 * Seed the checkout with the contact details captured on the tracked cart.
	 *
	 * @since 1.1.2
	 *
	 * @param array<string, mixed> $row The cart row.
	 * @return void
	 */
	private function prefill_customer( array $row ): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$customer = WC()->customer;

		if ( null === $customer ) {
			return;
		}

		// Saving the customer writes account meta: never copy one shopper's details into another's account.
		if ( is_user_logged_in() && (int) ( $row['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return;
		}

		$email  = (string) ( $row['email'] ?? '' );
		$filled = false;

		if ( '' !== $email && is_email( $email ) && '' === $customer->get_billing_email() ) {
			$customer->set_billing_email( $email );
			$filled = true;
		}

		// Both address books are seeded: the block checkout compares them to decide
		// whether to keep "use the same address for billing" ticked.
		$names = array(
			'first_name' => array( 'billing_first_name', 'shipping_first_name' ),
			'last_name'  => array( 'billing_last_name', 'shipping_last_name' ),
			'phone'      => array( 'billing_phone', 'shipping_phone' ),
		);

		foreach ( $names as $column => $properties ) {
			$value = (string) ( $row[ $column ] ?? '' );

			if ( '' === $value ) {
				continue;
			}

			foreach ( $properties as $property ) {
				if ( '' !== (string) $customer->{'get_' . $property}() ) {
					continue;
				}

				$customer->{'set_' . $property}( $value );
				$filled = true;
			}
		}

		if ( $filled ) {
			$customer->save();
		}
	}

	/**
	 * Empty the cart and re-add the stored lines + coupons.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row The cart row.
	 * @return bool Whether at least one stored line was put back in the cart.
	 */
	private function restore_cart( array $row ): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$cart = WC()->cart;

		if ( null === $cart ) {
			return false;
		}

		$decoded = ( isset( $row['cart_contents'] ) && is_string( $row['cart_contents'] ) )
			? json_decode( $row['cart_contents'], true )
			: array();
		$lines   = array_values( array_filter( is_array( $decoded ) ? $decoded : array(), 'is_array' ) );
		$buyable = array_values(
			array_filter(
				$lines,
				function ( array $line ): bool {
					return $this->is_available( $line );
				}
			)
		);

		// Leave the visitor's current cart alone when nothing saved can be bought any more.
		if ( array() === $buyable ) {
			$this->notice( __( 'The items in this saved cart are no longer available.', 'cart-rebound' ) );

			return false;
		}

		$cart->empty_cart();

		$added = 0;

		foreach ( $buyable as $line ) {
			$key = $cart->add_to_cart(
				(int) $line['product_id'],
				max( 1, (int) ( $line['quantity'] ?? 1 ) ),
				(int) ( $line['variation_id'] ?? 0 ),
				( isset( $line['variation'] ) && is_array( $line['variation'] ) ) ? $line['variation'] : array()
			);

			if ( false !== $key ) {
				++$added;
			}
		}

		if ( 0 === $added ) {
			return false;
		}

		if ( $added < count( $lines ) ) {
			$this->notice( __( 'Some items from your saved cart are no longer available and were left out.', 'cart-rebound' ) );
		}

		$coupons = ( isset( $row['coupons'] ) && is_string( $row['coupons'] ) )
			? json_decode( $row['coupons'], true )
			: array();

		if ( is_array( $coupons ) ) {
			foreach ( $coupons as $code ) {
				// Restore only live codes: WooCommerce shows a "coupon does not exist" error for a deleted one.
				if ( is_string( $code ) && '' !== $code && $this->coupon_exists( $code ) ) {
					$cart->apply_coupon( $code );
				}
			}
		}

		return true;
	}

	/**
	 * Whether a stored cart line's product can still be bought.
	 *
	 * @since 1.1.3
	 *
	 * @param array<string, mixed> $line Cart line.
	 * @return bool
	 */
	private function is_available( array $line ): bool {
		$product_id = (int) ( $line['product_id'] ?? 0 );

		if ( $product_id <= 0 ) {
			return false;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return true;
		}

		$variation_id = (int) ( $line['variation_id'] ?? 0 );
		$product      = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );

		return $product instanceof \WC_Product
			&& 'trash' !== $product->get_status()
			&& $product->is_purchasable()
			&& $product->is_in_stock();
	}

	/**
	 * Show the shopper a storefront notice, when WooCommerce notices are available.
	 *
	 * @since 1.1.3
	 *
	 * @param string $message Notice text.
	 * @return void
	 */
	private function notice( string $message ): void {
		if ( function_exists( 'wc_add_notice' ) && function_exists( 'WC' ) && null !== WC()->session ) {
			wc_add_notice( $message, 'notice' );
		}
	}

	/**
	 * Whether a coupon code still exists in the store.
	 *
	 * @since 1.1.2
	 *
	 * @param string $code Coupon code.
	 * @return bool
	 */
	private function coupon_exists( string $code ): bool {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return true;
		}

		return wc_get_coupon_id_by_code( $code ) > 0;
	}
}
