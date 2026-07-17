<?php
/**
 * Cart snapshot + upsert data layer.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tracking;

defined( 'ABSPATH' ) || exit;

use CartRebound\Models\CartSession;
use CartRebound\Support\Settings;
use WC_Product;

/**
 * Captures the live WooCommerce cart into a tracked row and back-fills identity.
 *
 * One row per stable session key (enforced by a UNIQUE index); when a key's row
 * has reached a terminal status its key is archived so a fresh cart cycle starts
 * a new row without losing the prior order/revenue attribution.
 *
 * @since 0.1.0
 */
final class CartTracker {

	/**
	 * Session key resolver.
	 *
	 * @since 0.1.0
	 * @var SessionManager
	 */
	private $sessions;

	/**
	 * Settings store.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param SessionManager $sessions Session key resolver.
	 * @param Settings       $settings Settings store.
	 */
	public function __construct( SessionManager $sessions, Settings $settings ) {
		$this->sessions = $sessions;
		$this->settings = $settings;
	}

	/**
	 * Capture/refresh the current cart snapshot.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function track(): void {
		if ( ! $this->tracking_allowed() ) {
			return;
		}

		$key = $this->sessions->resolve_session_key();

		if ( '' === $key ) {
			return;
		}

		$this->upsert( $key, $this->snapshot() );
	}

	/**
	 * Back-fill identity fields (email/name/phone) onto the current cart row.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $fields Raw identity fields.
	 * @return void
	 */
	public function capture_identity( array $fields ): void {
		if ( ! $this->tracking_allowed() ) {
			return;
		}

		$key = $this->sessions->resolve_session_key();

		if ( '' === $key ) {
			return;
		}

		$data = $this->sanitise_identity( $fields );

		if ( array() === $data ) {
			return;
		}

		$existing = CartSession::query()->where( 'session_key', '=', $key )->first();

		if ( ! is_array( $existing ) || $this->is_terminal( (string) ( $existing['status'] ?? '' ) ) ) {
			return;
		}

		$data['last_activity'] = gmdate( 'Y-m-d H:i:s' );

		CartSession::update( (int) ( $existing['id'] ?? 0 ), $data );
	}

	/**
	 * Insert or update the cart row for a session key.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $key  Stable session key.
	 * @param array<string, mixed> $data Snapshot columns.
	 * @return int The affected row id (0 on failure).
	 */
	public function upsert( string $key, array $data ): int {
		$existing = CartSession::query()->where( 'session_key', '=', $key )->first();
		$now      = gmdate( 'Y-m-d H:i:s' );

		if ( is_array( $existing ) ) {
			$id     = (int) ( $existing['id'] ?? 0 );
			$status = (string) ( $existing['status'] ?? '' );

			if ( ! $this->is_terminal( $status ) ) {
				$update = array_merge( $data, array( 'last_activity' => $now ) );

				if ( CartSession::STATUS_ABANDONED === $status ) {
					// Shopper is active again — return the cart to the active funnel.
					$update['status']               = CartSession::STATUS_ACTIVE;
					$update['abandonment_notified'] = 0;
					$update['abandoned_at']         = null;
				}

				CartSession::update( $id, $update );

				return $id;
			}

			// Free the UNIQUE session_key slot while staying within varchar(64).
			// The archived key is deterministic and cannot collide with the live key.
			CartSession::update( $id, array( 'session_key' => hash( 'sha256', $key . '|archived|' . $id ) ) );
		}

		// Never open a brand-new row for an empty cart: WooCommerce fires
		// `woocommerce_cart_updated` on ordinary page loads (shop, product,
		// account) where the cart holds nothing, which would otherwise litter
		// the list with 0-item rows. Existing rows are still updated above.
		if ( (int) ( $data['items_count'] ?? 0 ) < 1 ) {
			return 0;
		}

		$insert = array_merge(
			$data,
			$this->logged_in_identity(),
			array(
				'session_key'    => $key,
				'user_id'        => get_current_user_id(),
				'status'         => CartSession::STATUS_ACTIVE,
				'recovery_token' => wp_generate_password( 32, false ),
				'created_at'     => $now,
				'last_activity'  => $now,
			)
		);

		$id = CartSession::create( $insert );

		return is_int( $id ) ? $id : 0;
	}

	/**
	 * Build a snapshot of the live WooCommerce cart.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	private function snapshot(): array {
		$cart = function_exists( 'WC' ) ? WC()->cart : null;

		if ( null === $cart ) {
			return array(
				'cart_contents' => '[]',
				'cart_total'    => 0,
				'currency'      => '',
				'items_count'   => 0,
				'coupons'       => '[]',
				'checkout_url'  => '',
			);
		}

		$lines = array();

		foreach ( $cart->get_cart() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$product = ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) ? $item['data'] : null;

			$lines[] = array(
				'product_id'   => (int) ( $item['product_id'] ?? 0 ),
				'variation_id' => (int) ( $item['variation_id'] ?? 0 ),
				'variation'    => ( isset( $item['variation'] ) && is_array( $item['variation'] ) ) ? $item['variation'] : array(),
				'quantity'     => (int) ( $item['quantity'] ?? 0 ),
				'name'         => $product instanceof \WC_Product ? $product->get_name() : '',
				'price'        => $product instanceof \WC_Product ? (float) $product->get_price() : 0.0,
				'line_total'   => (float) ( $item['line_total'] ?? 0 ),
			);
		}

		return array(
			'cart_contents' => $this->encode( $lines ),
			'cart_total'    => (float) $cart->get_total( 'edit' ),
			'currency'      => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'items_count'   => (int) $cart->get_cart_contents_count(),
			'coupons'       => $this->encode( array_values( $cart->get_applied_coupons() ) ),
			'checkout_url'  => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
		);
	}

	/**
	 * Identity fields for the current logged-in user (empty for guests).
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string>
	 */
	private function logged_in_identity(): array {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return array();
		}

		$user = get_userdata( $user_id );

		if ( false === $user ) {
			return array();
		}

		return array(
			'email'      => $user->user_email,
			'first_name' => (string) get_user_meta( $user_id, 'first_name', true ),
			'last_name'  => (string) get_user_meta( $user_id, 'last_name', true ),
		);
	}

	/**
	 * Sanitise inbound identity fields, keeping only valid ones.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $fields Raw fields.
	 * @return array<string, string>
	 */
	private function sanitise_identity( array $fields ): array {
		$out = array();

		if ( isset( $fields['email'] ) ) {
			$email = sanitize_email( (string) $fields['email'] );

			if ( is_email( $email ) ) {
				$out['email'] = $email;
			}
		}

		foreach ( array( 'first_name', 'last_name', 'phone' ) as $field ) {
			if ( isset( $fields[ $field ] ) && '' !== (string) $fields[ $field ] ) {
				$out[ $field ] = sanitize_text_field( (string) $fields[ $field ] );
			}
		}

		return $out;
	}

	/**
	 * Whether tracking is enabled for the current request/visitor.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private function tracking_allowed(): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		if ( get_current_user_id() > 0 ) {
			return true;
		}

		return (bool) $this->settings->get( 'guest_tracking' );
	}

	/**
	 * Whether a status is terminal (no further tracking for that row).
	 *
	 * @since 0.1.0
	 *
	 * @param string $status Status value.
	 * @return bool
	 */
	private function is_terminal( string $status ): bool {
		return in_array(
			$status,
			array( CartSession::STATUS_RECOVERED, CartSession::STATUS_COMPLETED, CartSession::STATUS_LOST ),
			true
		);
	}

	/**
	 * JSON-encode a value, never returning false.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value Value to encode.
	 * @return string
	 */
	private function encode( $value ): string {
		$json = wp_json_encode( $value );

		return is_string( $json ) ? $json : '[]';
	}
}
