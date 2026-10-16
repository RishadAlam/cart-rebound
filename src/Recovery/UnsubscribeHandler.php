<?php
/**
 * Unsubscribe link handler.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Recovery;

defined( 'ABSPATH' ) || exit;

use CartRebound\Events\EventDispatcher;
use CartRebound\Models\CartSession;
use CartRebound\Models\Unsubscribe;

/**
 * Intercepts a tokenised unsubscribe link and suppresses the address.
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
	 * Event dispatcher.
	 *
	 * @since 1.1.2
	 * @var EventDispatcher
	 */
	private $events;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param RecoveryLink    $links  Recovery link builder.
	 * @param EventDispatcher $events Event dispatcher.
	 */
	public function __construct( RecoveryLink $links, EventDispatcher $events ) {
		$this->links  = $links;
		$this->events = $events;
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
			// A GET may be a mail-client or scanner prefetch: never unsubscribe on it, only offer a confirmation.
			$this->render( 'confirm', $token );
		}

		$row   = CartSession::query()->where( 'recovery_token', '=', $token )->first();
		$email = is_array( $row ) ? (string) ( $row['email'] ?? '' ) : '';

		if ( '' !== $email ) {
			$already = Unsubscribe::is_suppressed( $email );

			if ( Unsubscribe::suppress( $email ) ) {
				if ( ! $already && is_array( $row ) ) {
					$this->events->unsubscribed( $row );
				}

				$this->render( 'done', $token );
			}
		}

		$this->render( 'invalid', $token );
	}

	/**
	 * Render the standalone, theme-independent unsubscribe page and stop.
	 *
	 * Bypasses wp_die(), whose wp_kses would strip the styled layout; the view escapes every
	 * dynamic value.
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

		require $view;
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
		if ( isset( $_POST[ $key ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see the authentication rationale above.
			return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see the authentication rationale above.
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}
}
