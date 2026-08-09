<?php
/**
 * Recovery email sender.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Mail;

defined( 'ABSPATH' ) || exit;

use CartRebound\Followup\Step;
use CartRebound\Models\CartSession;
use CartRebound\Models\Unsubscribe;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Support\Settings;
use WC_Order;
use WP_Error;

/**
 * Renders and delivers recovery email.
 *
 * This is the only place the plugin talks to wp_mail: it owns the HTML shell,
 * the From header, the content-type swap, and the failure capture. Everything
 * that sends — the scheduled follow-up pipeline, the admin's on-demand send,
 * the test send, and an add-on's own message — goes through {@see deliver()},
 * so none of them can drift from the others or leak the HTML content type into
 * unrelated site mail.
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
			 * @since 1.1.0 Added the `$step` parameter.
			 *
			 * @param int                  $cart_id  The cart id.
			 * @param array<string, mixed> $row      The cart row.
			 * @param array<string, mixed> $template The template that was sent.
			 * @param Step|null            $step     The follow-up step, or null for a send outside the plan.
			 */
			do_action( 'cart_rebound_email_sent', $cart_id, $row, $template, null );
		}
	}

	/**
	 * Send one step of a cart's follow-up plan.
	 *
	 * The eligibility guards deliberately live in {@see \CartRebound\Followup\Runner},
	 * which has already decided this cart still deserves mail and has claimed
	 * this exact step. This method renders and delivers, nothing more.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $row  The cart row.
	 * @param Step                 $step The step to send.
	 * @return bool True when the message was handed to wp_mail successfully.
	 */
	public function send_step( array $row, Step $step ): bool {
		$cart_id  = (int) ( $row['id'] ?? 0 );
		$email    = (string) ( $row['email'] ?? '' );
		$template = $this->resolve_template( $step->template_id() );
		$context  = $this->context( $row, $template, $step );
		$rendered = $this->render( $row, $template, $context );
		$sent     = $this->deliver( $email, $rendered['subject'], $rendered['html'], $template );

		if ( ! $sent ) {
			return false;
		}

		CartSession::update( $cart_id, array( 'email_sent' => 1 ) );

		/** This action is documented in src/Mail/RecoveryMailer.php */
		do_action( 'cart_rebound_email_sent', $cart_id, $row, $template, $step );

		return true;
	}

	/**
	 * Render a template against a cart row.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $row      The cart row.
	 * @param array<string, mixed> $template The template to render.
	 * @param array<string, mixed> $context  Render context passed to the token/HTML filters.
	 * @return array{subject: string, html: string}
	 */
	public function render( array $row, array $template, array $context = array() ): array {
		if ( array() === $context ) {
			$context = $this->context( $row, $template, null );
		}

		$tokens = $this->text_tokens( $row, $template, $context );

		return array(
			'subject' => $this->subject( $template, $tokens ),
			'html'    => $this->build_body( $row, $template, $tokens, $context ),
		);
	}

	/**
	 * Hand an already-rendered message to wp_mail.
	 *
	 * Forces the HTML content type and captures the transport's error for the
	 * duration of this send only, then restores both — so a failure here is
	 * reportable and cannot leak into unrelated site mail.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $email    Recipient address.
	 * @param string               $subject  Rendered subject.
	 * @param string               $html     Rendered HTML body.
	 * @param array<string, mixed> $template Template supplying the From header.
	 * @return bool
	 */
	public function deliver( string $email, string $subject, string $html, array $template = array() ): bool {
		$this->last_error = __( 'WordPress could not send the email. Check the site SMTP or mail transport configuration and try again.', 'cart-rebound' );

		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		try {
			$sent = wp_mail( $email, $subject, $html, $this->headers( $template ) );
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

		// Use the address the merchant configured, falling back to the site admin.
		$recipient = (string) $this->settings->get( 'admin_notification_email', '' );

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$recipient = (string) get_option( 'admin_email' );
		}

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

		$template = $this->resolve_template( $template_id );
		$sent     = $this->dispatch( $email, $row, $template );

		if ( $sent ) {
			CartSession::update( $cart_id, array( 'email_sent' => 1 ) );

			/** This action is documented in src/Mail/RecoveryMailer.php */
			do_action( 'cart_rebound_email_sent', $cart_id, $row, $template, null );
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
		return $this->render( $this->sample_row(), $template );
	}

	/**
	 * Resolve a template id to a template, falling back to the default.
	 *
	 * @since 1.1.0
	 *
	 * @param string $template_id Template id, or '' for the default.
	 * @return array<string, mixed>
	 */
	private function resolve_template( string $template_id ): array {
		$chosen = '' !== $template_id ? $this->templates->get( $template_id ) : null;

		return is_array( $chosen ) ? $chosen : $this->templates->default();
	}

	/**
	 * Build the render context handed to the token and HTML filters.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $row      The cart row.
	 * @param array<string, mixed> $template The template being rendered.
	 * @param Step|null            $step     The follow-up step, when one drove this send.
	 * @return array<string, mixed>
	 */
	private function context( array $row, array $template, ?Step $step ): array {
		return array(
			'cart_id'     => (int) ( $row['id'] ?? 0 ),
			'step'        => $step,
			'step_index'  => null !== $step ? $step->index() : -1,
			'template_id' => (string) ( $template['id'] ?? '' ),
			'row'         => $row,
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
	 * Collect the plain-text tokens a message can substitute.
	 *
	 * These are text, not markup: they are inserted raw into the subject and
	 * escaped into the body. An add-on adding a token here — a coupon code, an
	 * expiry countdown — therefore cannot inject markup into the email, whether
	 * or not it remembered to escape.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $row      The cart row.
	 * @param array<string, mixed> $template The template being rendered.
	 * @param array<string, mixed> $context  Render context.
	 * @return array<string, string>
	 */
	private function text_tokens( array $row, array $template, array $context ): array {
		$tokens = array(
			'first_name'  => (string) ( $row['first_name'] ?? '' ),
			'last_name'   => (string) ( $row['last_name'] ?? '' ),
			'coupon_code' => (string) ( $template['coupon'] ?? '' ),
		);

		/**
		 * Filter the plain-text tokens available to a recovery email.
		 *
		 * Keys are token names without braces, so `coupon_expiry` is written
		 * `{coupon_expiry}` in a template. Values are treated as text: they go
		 * into the subject as-is and are escaped into the HTML body, so markup
		 * in a value is shown, never rendered. Use the `cart_rebound_email_html`
		 * filter for anything that has to be markup.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, string> $tokens  Token name => value.
		 * @param array<string, mixed>  $context Render context: cart_id, step, step_index, template_id, row.
		 */
		$filtered = apply_filters( 'cart_rebound_email_tokens', $tokens, $context );

		if ( ! is_array( $filtered ) ) {
			return $tokens;
		}

		$clean = array();

		foreach ( $filtered as $name => $value ) {
			$key = sanitize_key( (string) $name );

			if ( '' !== $key && is_scalar( $value ) ) {
				$clean[ $key ] = (string) $value;
			}
		}

		return $clean;
	}

	/**
	 * Build the token-substituted subject line.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed>  $template The email template.
	 * @param array<string, string> $tokens   Plain-text tokens.
	 * @return string
	 */
	private function subject( array $template, array $tokens ): string {
		return $this->substitute( (string) ( $template['subject'] ?? '' ), $tokens, false );
	}

	/**
	 * Replace `{token}` placeholders in a string.
	 *
	 * @since 1.1.0
	 *
	 * @param string                $subject The string to substitute into.
	 * @param array<string, string> $tokens  Token name => value.
	 * @param bool                  $escape  Whether values are HTML-escaped.
	 * @return string
	 */
	private function substitute( string $subject, array $tokens, bool $escape ): string {
		$search  = array();
		$replace = array();

		foreach ( $tokens as $name => $value ) {
			$search[]  = '{' . $name . '}';
			$replace[] = $escape ? esc_html( $value ) : $value;
		}

		return str_replace( $search, $replace, $subject );
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
		$rendered = $this->render( $row, $template );

		return $this->deliver( $email, $rendered['subject'], $rendered['html'], $template );
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
	 * @param array<string, mixed>  $row      Cart row.
	 * @param array<string, mixed>  $template The email template to render.
	 * @param array<string, string> $tokens   Plain-text tokens (escaped on substitution).
	 * @param array<string, mixed>  $context  Render context.
	 * @return string
	 */
	private function build_body( array $row, array $template, array $tokens, array $context ): string {
		$token           = (string) ( $row['recovery_token'] ?? '' );
		$recovery_url    = $this->links->url( $token );
		$unsubscribe_url = $this->links->unsubscribe_url( $token );

		// Markup tokens are escaped for their own context here; the text tokens
		// are escaped generically by substitute().
		$content = str_replace(
			array( '{products}', '{recovery_url}', '{unsubscribe_url}' ),
			array( $this->products_html( $row ), esc_url( $recovery_url ), esc_url( $unsubscribe_url ) ),
			(string) ( $template['body'] ?? '' )
		);

		$content = $this->substitute( $content, $tokens, true );

		$template_path = defined( 'CART_REBOUND_PATH' ) ? CART_REBOUND_PATH . 'resources/views/emails/recovery.php' : '';

		if ( '' === $template_path || ! is_readable( $template_path ) ) {
			$html = wpautop( $content );
		} else {
			ob_start();
			require $template_path;
			$rendered = ob_get_clean();
			$html     = is_string( $rendered ) ? $rendered : wpautop( $content );
		}

		/**
		 * Filter the fully rendered HTML of a recovery email.
		 *
		 * Runs last, on the complete document, which is what a tracking pixel or
		 * a link rewriter needs. Unlike `cart_rebound_email_tokens` the value
		 * here is markup and is inserted verbatim, so a callback is responsible
		 * for escaping whatever it injects.
		 *
		 * @since 1.1.0
		 *
		 * @param string               $html    The rendered message.
		 * @param array<string, mixed> $context Render context: cart_id, step, step_index, template_id, row.
		 */
		$filtered = apply_filters( 'cart_rebound_email_html', $html, $context );

		return is_string( $filtered ) ? $filtered : $html;
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
