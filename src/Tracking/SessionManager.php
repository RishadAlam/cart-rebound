<?php
/**
 * Stable per-visitor session key resolution.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tracking;

defined( 'ABSPATH' ) || exit;

use CartRebound\Models\CartSession;

/**
 * Resolves a stable key that identifies a visitor's cart across the
 * WooCommerce customer-id rotation that happens on login/expiry.
 *
 * Primary source is the WooCommerce session customer id; it is mirrored into a
 * first-party cookie so the same row keeps being updated even when WooCommerce
 * rotates its id. On login a guest row is merged into the user's identity.
 *
 * @since 0.1.0
 */
final class SessionManager {

	/**
	 * First-party cookie name holding the stable key.
	 *
	 * @var string
	 */
	public const COOKIE = 'cart_rebound_ref';

	/**
	 * Resolve the stable session key for the current visitor.
	 *
	 * @since 0.1.0
	 *
	 * @return string Empty string when no WooCommerce session exists yet.
	 */
	public function resolve_session_key(): string {
		$cookie = $this->read_cookie();

		if ( '' !== $cookie ) {
			return $cookie;
		}

		$wc_id = $this->wc_customer_id();

		if ( '' === $wc_id ) {
			return '';
		}

		$this->persist_cookie( $wc_id );

		return $wc_id;
	}

	/**
	 * Merge a guest's tracked cart into the user identity on login.
	 *
	 * @since 0.1.0
	 *
	 * @param int $user_id The user that just logged in.
	 * @return void
	 */
	public function merge_guest_into_user( int $user_id ): void {
		$key = $this->read_cookie();

		if ( '' === $key || $user_id <= 0 ) {
			return;
		}

		$row = CartSession::query()->where( 'session_key', '=', $key )->first();

		if ( ! is_array( $row ) || 0 !== (int) ( $row['user_id'] ?? 0 ) ) {
			return;
		}

		$update = array(
			'user_id'       => $user_id,
			'last_activity' => gmdate( 'Y-m-d H:i:s' ),
		);

		$user = get_userdata( $user_id );

		if ( false !== $user && '' === (string) ( $row['email'] ?? '' ) ) {
			$update['email']      = $user->user_email;
			$update['first_name'] = (string) get_user_meta( $user_id, 'first_name', true );
			$update['last_name']  = (string) get_user_meta( $user_id, 'last_name', true );
		}

		CartSession::update( (int) ( $row['id'] ?? 0 ), $update );
	}

	/**
	 * The current WooCommerce session customer id, if any.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private function wc_customer_id(): string {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}

		$wc = WC();

		if ( null === $wc->session ) {
			return '';
		}

		return $wc->session->get_customer_id();
	}

	/**
	 * Read the first-party cookie value (sanitised).
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private function read_cookie(): string {
		if ( ! isset( $_COOKIE[ self::COOKIE ] ) ) {
			return '';
		}

		// Identifier cookie; unslashed + sanitised on use, no nonce applies to a read-only ref.
		return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
	}

	/**
	 * Persist the stable key into a ~30-day first-party cookie.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value The stable key to store.
	 * @return void
	 */
	private function persist_cookie( string $value ): void {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
				'path'     => defined( 'COOKIEPATH' ) && '' !== COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}
