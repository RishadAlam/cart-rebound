<?php
/**
 * Unsubscribe link handler.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Recovery;

defined( 'ABSPATH' ) || exit;

use CartRebound\Models\CartSession;
use CartRebound\Models\Unsubscribe;

/**
 * Intercepts a tokenised unsubscribe link and suppresses the address.
 *
 * The unguessable recovery token is the credential (no nonce applies, like the
 * recovery link). To avoid the well-known problem of mail clients and security
 * scanners auto-following links, a GET only renders a confirmation the shopper
 * submits; suppression happens on the resulting POST and the address is only
 * reported as unsubscribed when it was actually added to the suppression list.
 *
 * @since 0.1.0
 */
final class UnsubscribeHandler {

	/**
	 * Recovery link builder (used to build the confirmation form action).
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
	 * Handle a possible unsubscribe request on template_redirect.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( '1' !== $this->input( RecoveryLink::QUERY_UNSUBSCRIBE ) ) {
			return;
		}

		$token = $this->input( RecoveryLink::QUERY_TOKEN );

		if ( '' === $token ) {
			return;
		}

		if ( ! $this->is_post() ) {
			// A GET may be a mail-client / scanner prefetch, which must never
			// unsubscribe anyone — only offer a confirmation the shopper submits.
			$this->render( 'confirm', $token );
		}

		$row   = CartSession::query()->where( 'recovery_token', '=', $token )->first();
		$email = is_array( $row ) ? (string) ( $row['email'] ?? '' ) : '';

		if ( '' !== $email && Unsubscribe::suppress( $email ) ) {
			$this->render( 'done', $token );
		}

		$this->render( 'invalid', $token );
	}

	/**
	 * Render the standalone, theme-independent unsubscribe page and stop.
	 *
	 * All dynamic values are escaped inside the view; only the trusted static
	 * markup and CSS live there, so this bypasses wp_die() (whose wp_kses would
	 * strip the styled layout) and outputs a complete document directly.
	 *
	 * @since 0.1.0
	 *
	 * @param string $state One of 'confirm', 'done', 'invalid'.
	 * @param string $token Recovery token.
	 * @return void
	 */
	private function render( string $state, string $token ): void {
		$view = defined( 'CART_REBOUND_PATH' )
			? CART_REBOUND_PATH . 'resources/views/unsubscribe.php'
			: '';

		if ( '' === $view || ! is_readable( $view ) ) {
			// Fall back to a bare status page if the view is somehow missing.
			wp_die(
				esc_html__( 'Unsubscribe', 'cart-rebound' ),
				esc_html__( 'Unsubscribe', 'cart-rebound' ),
				array( 'response' => 200 )
			);
		}

		if ( ! headers_sent() ) {
			nocache_headers();
			status_header( 200 );
			header( 'Content-Type: text/html; charset=utf-8' );
		}

		// Variables consumed by the view.
		$form_action = $this->links->unsubscribe_url( $token );
		$field_flag  = RecoveryLink::QUERY_UNSUBSCRIBE;
		$field_token = RecoveryLink::QUERY_TOKEN;
		$home_url    = home_url( '/' );

		require $view; // The view calls exit; execution stops here.
		exit;
	}

	/**
	 * Whether the current request is a POST (the confirmation submit).
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private function is_post(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		return 'POST' === $method;
	}

	/**
	 * Read a sanitised value from the POST body, falling back to the query.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	private function input( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- authenticated by the unguessable recovery token, not a nonce (the shopper is logged out); only an explicit POST suppresses, so a scanner GET cannot act.
		$raw = $_POST[ $key ] ?? ( $_GET[ $key ] ?? '' );

		return is_string( $raw ) ? sanitize_text_field( wp_unslash( $raw ) ) : '';
	}
}
