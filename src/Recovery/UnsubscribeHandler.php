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
			$this->render_confirmation( $token );
		}

		$row   = CartSession::query()->where( 'recovery_token', '=', $token )->first();
		$email = is_array( $row ) ? (string) ( $row['email'] ?? '' ) : '';

		if ( '' !== $email && Unsubscribe::suppress( $email ) ) {
			$this->render_page(
				esc_html__( 'You have been unsubscribed and will no longer receive cart recovery emails.', 'cart-rebound' )
			);
		}

		$this->render_page(
			esc_html__( 'This unsubscribe link is no longer valid.', 'cart-rebound' )
		);
	}

	/**
	 * Render the confirmation form (submitting it performs the unsubscribe).
	 *
	 * @since 0.1.0
	 *
	 * @param string $token Recovery token.
	 * @return void
	 */
	private function render_confirmation( string $token ): void {
		$html = '<form method="post" action="' . esc_url( $this->links->unsubscribe_url( $token ) ) . '">'
			. '<p>' . esc_html__( 'Stop receiving cart recovery emails from this store?', 'cart-rebound' ) . '</p>'
			. '<input type="hidden" name="' . esc_attr( RecoveryLink::QUERY_UNSUBSCRIBE ) . '" value="1" />'
			. '<input type="hidden" name="' . esc_attr( RecoveryLink::QUERY_TOKEN ) . '" value="' . esc_attr( $token ) . '" />'
			. '<p><button type="submit">' . esc_html__( 'Unsubscribe', 'cart-rebound' ) . '</button></p>'
			. '</form>';

		$this->render_page( $html );
	}

	/**
	 * Output a minimal status page and stop.
	 *
	 * @since 0.1.0
	 *
	 * @param string $body Pre-escaped HTML body.
	 * @return void
	 */
	private function render_page( string $body ): void {
		// The body is assembled from esc_* helpers above; allow just the small set
		// of form tags the confirmation needs (wp_kses_post would strip them).
		$allowed = array(
			'form'   => array(
				'method' => array(),
				'action' => array(),
			),
			'p'      => array(),
			'input'  => array(
				'type'  => array(),
				'name'  => array(),
				'value' => array(),
			),
			'button' => array(
				'type' => array(),
			),
		);

		wp_die(
			wp_kses( $body, $allowed ),
			esc_html__( 'Unsubscribe', 'cart-rebound' ),
			array( 'response' => 200 )
		);
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
