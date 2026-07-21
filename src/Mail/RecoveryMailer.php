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
use CartRebound\Models\Unsubscribe;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Support\Settings;
use WC_Order;
use WP_Error;

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
	 * @since 0.1.0
	 * @var TemplateStore
	 */
	private $templates;

	/**
	 * Human-readable reason the most recent on-demand send failed.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $last_error = '';

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

		// Suppression is a DB lookup, so it runs last — after the free row-field
		// guards have already rejected the common no-op cases.
		if ( Unsubscribe::is_suppressed( $email ) ) {
			return;
		}

		$template = $this->templates->default();
		$sent     = $this->dispatch( $email, $row, $template );

		if ( $sent ) {
			CartSession::update( $cart_id, array( 'email_sent' => 1 ) );

			/**
			 * Fires after a recovery email is sent (drives the activity log).
			 *
			 * @since 0.1.0
			 *
			 * @param int                  $cart_id  The cart id.
			 * @param array<string, mixed> $row      The cart row.
			 * @param array<string, mixed> $template The template that was sent.
			 */
			do_action( 'cart_rebound_email_sent', $cart_id, $row, $template );
		}
	}

	/**
	 * Email the site admin that a tracked cart was recovered into a paid order.
	 *
	 * A no-op unless the `admin_recovery_email` setting is enabled. Sends a
	 * plain-text summary to the WordPress admin address; independent of the
	 * shopper-facing recovery email toggle and never throws.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row   The recovered cart row.
	 * @param WC_Order             $order The paid order that recovered it.
	 * @return void
	 */
	public function notify_admin( array $row, WC_Order $order ): void {
		if ( ! $this->settings->get( 'admin_recovery_email' ) ) {
			return;
		}

		$recipient = (string) get_option( 'admin_email' );

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return;
		}

		$name     = trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
		$email    = (string) ( $row['email'] ?? '' );
		$customer = '' !== $name ? $name : ( '' !== $email ? $email : $order->get_billing_email() );
		$amount   = html_entity_decode(
			wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			ENT_QUOTES,
			'UTF-8'
		);

		$subject = sprintf(
			/* translators: %s: order number. */
			__( '[Cart Rebound] Recovered cart — order #%s', 'cart-rebound' ),
			$order->get_order_number()
		);

		$body = implode(
			"\n",
			array(
				__( 'A tracked cart was recovered into a paid order.', 'cart-rebound' ),
				'',
				sprintf(
					/* translators: %s: order number. */
					__( 'Order: #%s', 'cart-rebound' ),
					$order->get_order_number()
				),
				sprintf(
					/* translators: %s: formatted order total. */
					__( 'Amount: %s', 'cart-rebound' ),
					$amount
				),
				sprintf(
					/* translators: %s: customer name or email. */
					__( 'Customer: %s', 'cart-rebound' ),
					'' !== $customer ? $customer : __( '(unknown)', 'cart-rebound' )
				),
				'',
				$order->get_edit_order_url(),
			)
		);

		wp_mail( $recipient, $subject, $body );
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
		$this->last_error = '';

		$row = CartSession::find( $cart_id );

		if ( ! is_array( $row ) ) {
			return $this->fail( __( 'The cart could not be found.', 'cart-rebound' ) );
		}

		$email = (string) ( $row['email'] ?? '' );

		if ( '' === $email || ! is_email( $email ) ) {
			return $this->fail( __( 'This cart does not have a valid email address.', 'cart-rebound' ) );
		}

		if ( (int) ( $row['items_count'] ?? 0 ) <= 0 ) {
			return $this->fail( __( 'This cart has no items to recover.', 'cart-rebound' ) );
		}

		// Never re-pitch a cart that already converted to an order: recovered and
		// completed carts are order-linked, and mailing "you left something in
		// your cart" (with a coupon) to a paid customer is wrong.
		if ( (int) ( $row['order_id'] ?? 0 ) > 0 ) {
			return $this->fail( __( 'This cart is already linked to an order.', 'cart-rebound' ) );
		}

		if ( Unsubscribe::is_suppressed( $email ) ) {
			return $this->fail( __( 'This address has unsubscribed from recovery emails.', 'cart-rebound' ) );
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
	 * Send a test render of a template to an address (admin "send test" action).
	 *
	 * Uses representative sample cart data so an admin can see exactly what a
	 * shopper would receive, without touching a real cart.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $email    Recipient address.
	 * @param array<string, mixed> $template The (possibly unsaved) template fields.
	 * @return bool True when the test email was handed to wp_mail successfully.
	 */
	public function send_test( string $email, array $template ): bool {
		$this->last_error = '';

		$email = sanitize_email( $email );

		if ( '' === $email || ! is_email( $email ) ) {
			return $this->fail( __( 'Enter a valid email address to send the test to.', 'cart-rebound' ) );
		}

		return $this->dispatch( $email, $this->sample_row(), $template );
	}

	/**
	 * Get the reason the most recent on-demand send failed.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_last_error(): string {
		return $this->last_error;
	}

	/**
	 * Capture the detailed error emitted by WordPress when wp_mail() fails.
	 *
	 * This callback is attached only for the duration of this mailer's own send,
	 * so unrelated site email failures can never leak into the admin response.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Error $error WordPress mail error.
	 * @return void
	 */
	public function capture_mail_error( WP_Error $error ): void {
		$message = $error->get_error_message();

		if ( '' !== $message ) {
			$this->last_error = sprintf(
				/* translators: %s: WordPress or SMTP mail error. */
				__( 'WordPress could not send the email: %s', 'cart-rebound' ),
				$message
			);
		}
	}

	/**
	 * Render a template against sample data for an on-screen preview.
	 *
	 * Uses the real subject/body token substitution and the real email shell,
	 * so the preview matches what a shopper would receive.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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

		$this->last_error = __( 'WordPress could not send the email. Check the site SMTP or mail transport configuration and try again.', 'cart-rebound' );
		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		try {
			$sent = wp_mail( $email, $subject, $body, $headers );
		} finally {
			remove_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );
			remove_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		}

		if ( $sent ) {
			$this->last_error = '';
		}

		return $sent;
	}

	/**
	 * Store an on-demand send failure and return false to the caller.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message Failure message.
	 * @return bool
	 */
	private function fail( string $message ): bool {
		$this->last_error = $message;

		return false;
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
		$token           = (string) ( $row['recovery_token'] ?? '' );
		$recovery_url    = $this->links->url( $token );
		$unsubscribe_url = $this->links->unsubscribe_url( $token );
		$first_name      = (string) ( $row['first_name'] ?? '' );
		$coupon_code     = (string) ( $template['coupon'] ?? '' );
		$products_html   = $this->products_html( $row );

		$content = str_replace(
			array( '{first_name}', '{products}', '{recovery_url}', '{coupon_code}', '{unsubscribe_url}' ),
			array( esc_html( $first_name ), $products_html, esc_url( $recovery_url ), esc_html( $coupon_code ), esc_url( $unsubscribe_url ) ),
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
