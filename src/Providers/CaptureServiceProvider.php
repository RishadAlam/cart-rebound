<?php
/**
 * Cart + guest-identity capture provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\ServiceProvider;
use CartRebound\Support\Settings;
use CartRebound\Tracking\CartTracker;
use WC_Order;

/**
 * Wires WooCommerce cart-capture hooks and guest email capture across both
 * classic checkout (AJAX beacon + server-side hooks) and block / Store API.
 *
 * @since 0.1.0
 */
final class CaptureServiceProvider extends ServiceProvider {

	/**
	 * Wire capture hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		$tracker = $this->app->make( CartTracker::class );

		add_action( 'woocommerce_add_to_cart', array( $tracker, 'track' ), 20 );
		add_action( 'woocommerce_cart_updated', array( $tracker, 'track' ), 20 );
		add_action( 'woocommerce_cart_item_removed', array( $tracker, 'track' ), 20 );
		add_action( 'woocommerce_cart_emptied', array( $tracker, 'track' ), 20 );

		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'capture_from_review' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'capture_from_validation' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'capture_from_store_api' ), 20, 1 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_asset' ) );
	}

	/**
	 * Capture identity from the serialised classic-checkout review payload.
	 *
	 * @since 0.1.0
	 *
	 * @param string $post_data Serialised checkout form data.
	 * @return void
	 */
	public function capture_from_review( $post_data ): void {
		if ( ! is_string( $post_data ) || '' === $post_data ) {
			return;
		}

		$parsed = array();
		parse_str( $post_data, $parsed );

		$this->app->make( CartTracker::class )->capture_identity( $this->map_billing( $parsed ) );
	}

	/**
	 * Capture identity from the validated classic-checkout data (no-JS safety net).
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $data Posted checkout fields.
	 * @return void
	 */
	public function capture_from_validation( $data ): void {
		if ( ! is_array( $data ) ) {
			return;
		}

		$this->app->make( CartTracker::class )->capture_identity( $this->map_billing( $data ) );
	}

	/**
	 * Capture identity from a block / Store API checkout request.
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Order $order The order being built from the request.
	 * @return void
	 */
	public function capture_from_store_api( WC_Order $order ): void {
		$this->app->make( CartTracker::class )->capture_identity(
			array(
				'email'      => $order->get_billing_email(),
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'phone'      => $order->get_billing_phone(),
			)
		);
	}

	/**
	 * Enqueue the front-end checkout capture beacon on the checkout page.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function enqueue_checkout_asset(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		if ( ! defined( 'CART_REBOUND_FILE' ) || ! defined( 'CART_REBOUND_VERSION' ) ) {
			return;
		}

		$settings = $this->app->make( Settings::class );

		if ( ! $settings->get( 'enabled' ) || ! $settings->get( 'guest_tracking' ) ) {
			return;
		}

		$handle = 'cart-rebound-checkout';

		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/js/checkout-capture.js', CART_REBOUND_FILE ),
			array(),
			CART_REBOUND_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'cartReboundCheckout',
			array(
				'endpoint' => esc_url_raw( rest_url( 'cart-rebound/v1/capture' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Map WooCommerce billing fields to identity fields.
	 *
	 * @since 0.1.0
	 *
	 * @param array<array-key, mixed> $source Source fields.
	 * @return array<string, string>
	 */
	private function map_billing( array $source ): array {
		$map = array(
			'email'      => 'billing_email',
			'first_name' => 'billing_first_name',
			'last_name'  => 'billing_last_name',
			'phone'      => 'billing_phone',
		);

		$out = array();

		foreach ( $map as $key => $source_key ) {
			if ( isset( $source[ $source_key ] ) && '' !== (string) $source[ $source_key ] ) {
				$out[ $key ] = (string) $source[ $source_key ];
			}
		}

		return $out;
	}
}
