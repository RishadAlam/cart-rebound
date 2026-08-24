<?php
/**
 * Recovery link handler.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Recovery;

defined( 'ABSPATH' ) || exit;

use CartRebound\Models\CartSession;
use CartRebound\Tracking\SessionManager;

/**
 * Intercepts a tokenised recovery link, rebuilds the cart, and redirects to checkout.
 *
 * The unguessable token is the credential (no nonce applies, like a password-reset
 * link); the cart row id is bound into the WooCommerce session so {@see OrderLinker}
 * can attribute the resulting order as recovered.
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
	 * Constructor.
	 *
	 * @since 1.1.2
	 *
	 * @param SessionManager $sessions Session key resolver.
	 */
	public function __construct( SessionManager $sessions ) {
		$this->sessions = $sessions;
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
			return;
		}

		// Adopt the recovered row into this browser's tracking session BEFORE the
		// cart is rebuilt. CartTracker runs on the cart writes below, and without
		// this it would open a second row for the same cart — leaving the original
		// sitting in `abandoned` while the duplicate collected its own recovery
		// email for the shopper who just clicked this link.
		$this->adopt_session( (int) ( $row['id'] ?? 0 ) );

		if ( ! $this->restore_cart( $row ) ) {
			return;
		}

		$this->prefill_customer( $row );

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
	 * Any row already holding the key is archived first — the same deterministic
	 * rename {@see \CartRebound\Tracking\CartTracker} uses — so the UNIQUE index
	 * is respected and no tracked history is destroyed.
	 *
	 * @since 1.1.2
	 *
	 * @param int $cart_id The recovered cart row id.
	 * @return void
	 */
	private function adopt_session( int $cart_id ): void {
		if ( $cart_id <= 0 ) {
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
	 * A recovery link is a one-click return: the shopper should not have to retype
	 * the address the store already emailed them at. Only empty fields are filled,
	 * so a logged-in customer's saved details are never overwritten.
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

		$email  = (string) ( $row['email'] ?? '' );
		$filled = false;

		if ( '' !== $email && is_email( $email ) && '' === $customer->get_billing_email() ) {
			$customer->set_billing_email( $email );
			$filled = true;
		}

		// Both address books are seeded: the block checkout compares them to decide
		// whether to keep "use the same address for billing" ticked, and filling
		// only one side would split the form into two addresses to complete.
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
	 * @return bool Whether the cart was available to restore into.
	 */
	private function restore_cart( array $row ): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$cart = WC()->cart;

		if ( null === $cart ) {
			return false;
		}

		$cart->empty_cart();

		$lines = ( isset( $row['cart_contents'] ) && is_string( $row['cart_contents'] ) )
			? json_decode( $row['cart_contents'], true )
			: array();

		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				if ( ! is_array( $line ) ) {
					continue;
				}

				$product_id = (int) ( $line['product_id'] ?? 0 );

				if ( $product_id <= 0 ) {
					continue;
				}

				$cart->add_to_cart(
					$product_id,
					max( 1, (int) ( $line['quantity'] ?? 1 ) ),
					(int) ( $line['variation_id'] ?? 0 ),
					( isset( $line['variation'] ) && is_array( $line['variation'] ) ) ? $line['variation'] : array()
				);
			}
		}

		$coupons = ( isset( $row['coupons'] ) && is_string( $row['coupons'] ) )
			? json_decode( $row['coupons'], true )
			: array();

		if ( is_array( $coupons ) ) {
			foreach ( $coupons as $code ) {
				// Coupons captured weeks ago are often gone by the time the link is
				// clicked. Re-applying a deleted code makes WooCommerce print a red
				// "coupon does not exist" block, which is the first thing a
				// returning shopper would see — so only live codes are restored.
				if ( is_string( $code ) && '' !== $code && $this->coupon_exists( $code ) ) {
					$cart->apply_coupon( $code );
				}
			}
		}

		return true;
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
