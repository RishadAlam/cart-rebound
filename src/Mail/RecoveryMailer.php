<?php
/**
 * Recovery email sender.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Mail;

defined( 'ABSPATH' ) || exit;

use CartRebound\Models\CartSession;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Support\Settings;

/**
 * Sends the optional single recovery email, scheduled a delay after abandonment.
 *
 * Skips carts that already converted or were already emailed; restores the
 * wp_mail content type after sending so it never leaks to other mail.
 *
 * @since 0.1.0
 */
final class RecoveryMailer {

	/**
	 * Single-action hook that triggers a send.
	 *
	 * @var string
	 */
	public const HOOK = 'cart_rebound_send_recovery_email';

	/**
	 * Settings store.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

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
	 * @param Settings     $settings Settings store.
	 * @param RecoveryLink $links    Recovery link builder.
	 */
	public function __construct( Settings $settings, RecoveryLink $links ) {
		$this->settings = $settings;
		$this->links    = $links;
	}

	/**
	 * Send the recovery email for a cart, if still eligible.
	 *
	 * @since 0.1.0
	 *
	 * @param int $cart_id Cart id.
	 * @return void
	 */
	public function send( int $cart_id ): void {
		if ( ! $this->settings->get( 'recovery_email_enabled' ) ) {
			return;
		}

		$row = CartSession::find( $cart_id );

		if ( ! is_array( $row ) ) {
			return;
		}

		$email = (string) ( $row['email'] ?? '' );

		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}

		if ( CartSession::STATUS_ABANDONED !== (string) ( $row['status'] ?? '' ) ) {
			return;
		}

		if ( 1 === (int) ( $row['email_sent'] ?? 0 ) ) {
			return;
		}

		if ( (int) ( $row['items_count'] ?? 0 ) <= 0 ) {
			return;
		}

		$sent = $this->dispatch( $email, $row );

		if ( $sent ) {
			CartSession::update( $cart_id, array( 'email_sent' => 1 ) );
		}
	}

	/**
	 * Render and send the HTML email.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $email Recipient.
	 * @param array<string, mixed> $row   Cart row.
	 * @return bool
	 */
	private function dispatch( string $email, array $row ): bool {
		$subject = (string) $this->settings->get( 'email_subject' );
		$body    = $this->build_body( $row );
		$headers = $this->headers();

		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		$sent = wp_mail( $email, $subject, $body, $headers );
		remove_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );

		return $sent;
	}

	/**
	 * Build the HTML body from the configured template + tokens.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Cart row.
	 * @return string
	 */
	private function build_body( array $row ): string {
		$recovery_url  = $this->links->url( (string) ( $row['recovery_token'] ?? '' ) );
		$first_name    = (string) ( $row['first_name'] ?? '' );
		$products_html = $this->products_html( $row );

		$content = str_replace(
			array( '{first_name}', '{products}', '{recovery_url}' ),
			array( esc_html( $first_name ), $products_html, esc_url( $recovery_url ) ),
			(string) $this->settings->get( 'email_body' )
		);

		$template = defined( 'CART_REBOUND_PATH' ) ? CART_REBOUND_PATH . 'resources/views/emails/recovery.php' : '';

		if ( '' === $template || ! is_readable( $template ) ) {
			return wpautop( $content );
		}

		ob_start();
		require $template;
		$html = ob_get_clean();

		return is_string( $html ) ? $html : wpautop( $content );
	}

	/**
	 * Build a simple escaped product list for the email.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Cart row.
	 * @return string
	 */
	private function products_html( array $row ): string {
		$raw = ( isset( $row['cart_contents'] ) && is_string( $row['cart_contents'] ) )
			? json_decode( $row['cart_contents'], true )
			: array();

		if ( ! is_array( $raw ) || array() === $raw ) {
			return '';
		}

		$items = array();

		foreach ( $raw as $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}

			$items[] = '<li>' . esc_html(
				sprintf(
					'%1$s × %2$d',
					(string) ( $line['name'] ?? '' ),
					(int) ( $line['quantity'] ?? 0 )
				)
			) . '</li>';
		}

		return array() === $items ? '' : '<ul>' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Build the From header from settings, if configured.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, string>
	 */
	private function headers(): array {
		$name = (string) $this->settings->get( 'email_from_name' );
		$from = (string) $this->settings->get( 'email_from_email' );

		if ( '' === $from || ! is_email( $from ) ) {
			return array();
		}

		$label = '' !== $name ? $name : $from;

		return array( sprintf( 'From: %1$s <%2$s>', $label, $from ) );
	}

	/**
	 * Content-type filter callback forcing HTML email.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function html_content_type(): string {
		return 'text/html';
	}
}
