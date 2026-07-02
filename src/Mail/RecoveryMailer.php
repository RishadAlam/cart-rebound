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
	 * Email template store.
	 *
	 * @since 0.2.0
	 * @var TemplateStore
	 */
	private $templates;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Settings      $settings  Settings store.
	 * @param RecoveryLink  $links     Recovery link builder.
	 * @param TemplateStore $templates Email template store.
	 */
	public function __construct( Settings $settings, RecoveryLink $links, TemplateStore $templates ) {
		$this->settings  = $settings;
		$this->links     = $links;
		$this->templates = $templates;
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

		$template = $this->templates->default();
		$sent     = $this->dispatch( $email, $row, $template );

		if ( $sent ) {
			CartSession::update( $cart_id, array( 'email_sent' => 1 ) );

			/**
			 * Fires after a recovery email is sent (drives the activity log).
			 *
			 * @since 0.2.0
			 *
			 * @param int                  $cart_id  The cart id.
			 * @param array<string, mixed> $row      The cart row.
			 * @param array<string, mixed> $template The template that was sent.
			 */
			do_action( 'cart_rebound_email_sent', $cart_id, $row, $template );
		}
	}

	/**
	 * Send the recovery email right now, on demand (admin "send email" action).
	 *
	 * Unlike {@see send()} this ignores the scheduled-delay guards — the
	 * enabled toggle, the abandoned-only rule, and the already-sent flag — so an
	 * admin can (re)send at will. It still refuses to mail an empty cart or an
	 * address that is missing / invalid.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $cart_id     Cart id.
	 * @param string $template_id Optional template id to send; defaults to the default template.
	 * @return bool True when the email was handed to wp_mail successfully.
	 */
	public function send_now( int $cart_id, string $template_id = '' ): bool {
		$row = CartSession::find( $cart_id );

		if ( ! is_array( $row ) ) {
			return false;
		}

		$email = (string) ( $row['email'] ?? '' );

		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}

		if ( (int) ( $row['items_count'] ?? 0 ) <= 0 ) {
			return false;
		}

		// Never re-pitch a cart that already converted to an order: recovered and
		// completed carts are order-linked, and mailing "you left something in
		// your cart" (with a coupon) to a paid customer is wrong.
		if ( (int) ( $row['order_id'] ?? 0 ) > 0 ) {
			return false;
		}

		$chosen   = '' !== $template_id ? $this->templates->get( $template_id ) : null;
		$template = is_array( $chosen ) ? $chosen : $this->templates->default();
		$sent     = $this->dispatch( $email, $row, $template );

		if ( $sent ) {
			CartSession::update( $cart_id, array( 'email_sent' => 1 ) );

			/** This action is documented in src/Mail/RecoveryMailer.php */
			do_action( 'cart_rebound_email_sent', $cart_id, $row, $template );
		}

		return $sent;
	}

	/**
	 * Render a template against sample data for an on-screen preview.
	 *
	 * Uses the real subject/body token substitution and the real email shell,
	 * so the preview matches what a shopper would receive.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, mixed> $template The (unsaved) template to preview.
	 * @return array{subject: string, html: string}
	 */
	public function preview( array $template ): array {
		$row = $this->sample_row();

		return array(
			'subject' => $this->subject( $template, $row ),
			'html'    => $this->build_body( $row, $template ),
		);
	}

	/**
	 * A representative cart row for previews.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, mixed>
	 */
	private function sample_row(): array {
		return array(
			'first_name'     => 'Jordan',
			'recovery_token' => 'sample-token',
			'cart_contents'  => wp_json_encode(
				array(
					array(
						'name'     => 'Blue T-Shirt',
						'quantity' => 2,
					),
					array(
						'name'     => 'Leather Wallet',
						'quantity' => 1,
					),
				)
			),
		);
	}

	/**
	 * Build the token-substituted subject line.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, mixed> $template The email template.
	 * @param array<string, mixed> $row      The cart row.
	 * @return string
	 */
	private function subject( array $template, array $row ): string {
		return str_replace(
			array( '{first_name}', '{coupon_code}' ),
			array( (string) ( $row['first_name'] ?? '' ), (string) ( $template['coupon'] ?? '' ) ),
			(string) ( $template['subject'] ?? '' )
		);
	}

	/**
	 * Render and send the HTML email.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $email    Recipient.
	 * @param array<string, mixed> $row      Cart row.
	 * @param array<string, mixed> $template The email template to render.
	 * @return bool
	 */
	private function dispatch( string $email, array $row, array $template ): bool {
		$subject = $this->subject( $template, $row );
		$body    = $this->build_body( $row, $template );
		$headers = $this->headers( $template );

		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		$sent = wp_mail( $email, $subject, $body, $headers );
		remove_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );

		return $sent;
	}

	/**
	 * Build the HTML body from the given template + tokens.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row      Cart row.
	 * @param array<string, mixed> $template The email template to render.
	 * @return string
	 */
	private function build_body( array $row, array $template ): string {
		$recovery_url  = $this->links->url( (string) ( $row['recovery_token'] ?? '' ) );
		$first_name    = (string) ( $row['first_name'] ?? '' );
		$coupon_code   = (string) ( $template['coupon'] ?? '' );
		$products_html = $this->products_html( $row );

		$content = str_replace(
			array( '{first_name}', '{products}', '{recovery_url}', '{coupon_code}' ),
			array( esc_html( $first_name ), $products_html, esc_url( $recovery_url ), esc_html( $coupon_code ) ),
			(string) ( $template['body'] ?? '' )
		);

		$template_path = defined( 'CART_REBOUND_PATH' ) ? CART_REBOUND_PATH . 'resources/views/emails/recovery.php' : '';

		if ( '' === $template_path || ! is_readable( $template_path ) ) {
			return wpautop( $content );
		}

		ob_start();
		require $template_path;
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
	 * Build the From header from the template, if configured.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $template The email template.
	 * @return array<int, string>
	 */
	private function headers( array $template ): array {
		$name = (string) ( $template['from_name'] ?? '' );
		$from = (string) ( $template['from_email'] ?? '' );

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
