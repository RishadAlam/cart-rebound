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
			'last_name'      => 'Avery',
			'email'          => 'jordan@example.com',
			'recovery_token' => 'sample-token',
			'cart_total'     => 84.0,
			'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'items_count'    => 3,
			'checkout_url'   => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
			'abandoned_at'   => current_time( 'mysql' ),
			'cart_contents'  => wp_json_encode(
				array(
					array(
						'product_id' => 0,
						'name'       => 'Blue T-Shirt',
						'quantity'   => 2,
						'price'      => 24.0,
						'line_total' => 48.0,
						'variation'  => array( 'attribute_pa_size' => 'Large' ),
					),
					array(
						'product_id' => 0,
						'name'       => 'Leather Wallet',
						'quantity'   => 1,
						'price'      => 36.0,
						'line_total' => 36.0,
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
		$tokens = $this->tokens( $row, $template );

		// A subject line cannot carry markup, so the two list tokens fall back to
		// the plain comma-separated product names.
		$tokens['{products}']       = $tokens['{product_names}'];
		$tokens['{products_table}'] = $tokens['{product_names}'];

		return str_replace(
			array_keys( $tokens ),
			array_values( $tokens ),
			(string) ( $template['subject'] ?? '' )
		);
	}

	/**
	 * Every merge tag and its plain-text value for a cart.
	 *
	 * Values are unescaped here; callers escape per context — {@see subject()}
	 * uses them as text, {@see build_body()} escapes each one for HTML.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row      Cart row.
	 * @param array<string, mixed> $template The email template being rendered.
	 * @return array<string, string>
	 */
	private function tokens( array $row, array $template ): array {
		$token      = (string) ( $row['recovery_token'] ?? '' );
		$first_name = (string) ( $row['first_name'] ?? '' );
		$last_name  = (string) ( $row['last_name'] ?? '' );
		$currency   = (string) ( $row['currency'] ?? '' );
		$items      = $this->items( $row );
		$names      = array();

		foreach ( $items as $line ) {
			$name = $this->line_name( $line );

			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		$checkout_url = (string) ( $row['checkout_url'] ?? '' );

		if ( '' === $checkout_url && function_exists( 'wc_get_checkout_url' ) ) {
			$checkout_url = wc_get_checkout_url();
		}

		return array(
			'{first_name}'      => $first_name,
			'{last_name}'       => $last_name,
			'{full_name}'       => trim( $first_name . ' ' . $last_name ),
			'{email}'           => (string) ( $row['email'] ?? '' ),
			'{product_names}'   => implode( ', ', $names ),
			'{items_count}'     => (string) (int) ( $row['items_count'] ?? count( $items ) ),
			'{cart_total}'      => $this->money( (float) ( $row['cart_total'] ?? 0 ), $currency ),
			'{abandoned_on}'    => $this->abandoned_on( $row ),
			'{checkout_url}'    => $checkout_url,
			'{recovery_url}'    => $this->links->url( $token ),
			'{unsubscribe_url}' => $this->links->unsubscribe_url( $token ),
			'{coupon_code}'     => (string) ( $template['coupon'] ?? '' ),
			'{store_name}'      => (string) get_bloginfo( 'name' ),
			'{store_url}'       => home_url( '/' ),
			'{store_email}'     => $this->store_email(),
			'{manager_name}'    => $this->manager_name(),
			'{current_year}'    => (string) wp_date( 'Y' ),
		);
	}

	/**
	 * Tokens whose value is a URL, so they are escaped with esc_url in the body.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, string>
	 */
	private function url_tokens(): array {
		return array( '{checkout_url}', '{recovery_url}', '{unsubscribe_url}', '{store_url}' );
	}

	/**
	 * The date a cart was left, in the site's date format.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Cart row.
	 * @return string
	 */
	private function abandoned_on( array $row ): string {
		$stamp = (string) ( $row['abandoned_at'] ?? '' );

		if ( '' === $stamp ) {
			$stamp = (string) ( $row['created_at'] ?? '' );
		}

		if ( '' === $stamp ) {
			return '';
		}

		return (string) mysql2date( (string) get_option( 'date_format' ), $stamp );
	}

	/**
	 * The address shoppers should reply to, falling back to the site admin.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private function store_email(): string {
		$email = (string) $this->settings->get( 'admin_notification_email', '' );

		if ( '' === $email || ! is_email( $email ) ) {
			$email = (string) get_option( 'admin_email' );
		}

		return $email;
	}

	/**
	 * A human name for whoever runs the store — the admin user's first name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private function manager_name(): string {
		$admin = get_user_by( 'email', (string) get_option( 'admin_email' ) );

		if ( ! $admin instanceof \WP_User ) {
			return '';
		}

		$first = (string) $admin->first_name;

		return '' !== $first ? $first : (string) $admin->display_name;
	}

	/**
	 * Format an amount as store currency, as plain text.
	 *
	 * @since 0.1.0
	 *
	 * @param float  $amount   The amount.
	 * @param string $currency Currency code; the store default when empty.
	 * @return string
	 */
	private function money( float $amount, string $currency ): string {
		if ( ! function_exists( 'wc_price' ) ) {
			return number_format_i18n( $amount, 2 );
		}

		$args = '' !== $currency ? array( 'currency' => $currency ) : array();

		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, $args ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * The display name for a cart line.
	 *
	 * The snapshot stores the name as it was when the cart was captured, but a
	 * line written before that field existed — or captured while the product was
	 * unavailable — has none, so fall back to the live product.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $line Cart line.
	 * @return string
	 */
	private function line_name( array $line ): string {
		$name = trim( (string) ( $line['name'] ?? '' ) );

		if ( '' !== $name ) {
			return $name;
		}

		$product = $this->product( $line );

		return null !== $product ? (string) $product->get_name() : '';
	}

	/**
	 * Decode the stored cart lines.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Cart row.
	 * @return array<int, array<string, mixed>>
	 */
	private function items( array $row ): array {
		$raw = ( isset( $row['cart_contents'] ) && is_string( $row['cart_contents'] ) )
			? json_decode( $row['cart_contents'], true )
			: array();

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$lines = array();

		foreach ( $raw as $line ) {
			if ( is_array( $line ) ) {
				$lines[] = $line;
			}
		}

		return $lines;
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
		$tokens       = $this->tokens( $row, $template );
		$urls         = $this->url_tokens();
		$replacements = array();

		foreach ( $tokens as $tag => $plain ) {
			$replacements[ $tag ] = in_array( $tag, $urls, true ) ? esc_url( $plain ) : esc_html( $plain );
		}

		// The two list tokens are markup by definition, escaped as they are built.
		$replacements['{products}']       = $this->products_html( $row );
		$replacements['{products_table}'] = $this->products_table_html( $row, $template );

		$recovery_url    = $tokens['{recovery_url}'];
		$unsubscribe_url = $tokens['{unsubscribe_url}'];

		// Paragraph-wrap the authored body *before* substitution: wpautop closes
		// an open <p> when it meets block markup, which would leave a stray
		// </p> inside the injected product table.
		$content = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			wpautop( (string) ( $template['body'] ?? '' ) )
		);

		$template_path = defined( 'CART_REBOUND_PATH' ) ? CART_REBOUND_PATH . 'resources/views/emails/recovery.php' : '';

		if ( '' === $template_path || ! is_readable( $template_path ) ) {
			return $content;
		}

		ob_start();
		require $template_path;
		$html = ob_get_clean();

		return is_string( $html ) ? $html : $content;
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
		$items = array();

		foreach ( $this->items( $row ) as $line ) {
			$items[] = '<li style="margin: 0 0 4px;">' . esc_html(
				sprintf(
					'%1$s × %2$d',
					$this->line_name( $line ),
					(int) ( $line['quantity'] ?? 0 )
				)
			) . '</li>';
		}

		if ( array() === $items ) {
			return '';
		}

		return '<ul style="margin: 8px 0; padding-left: 20px; list-style-type: disc;">' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Build the cart as a table, laid out per the template's table options.
	 *
	 * Inline styles only, and a plain table layout, because email clients drop
	 * stylesheets and most of the modern box model.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row      Cart row.
	 * @param array<string, mixed> $template The email template being rendered.
	 * @return string
	 */
	private function products_table_html( array $row, array $template ): string {
		$lines = $this->items( $row );

		if ( array() === $lines ) {
			return '';
		}

		$config   = TemplateStore::table_config( $template['table'] ?? array() );
		$currency = (string) ( $row['currency'] ?? '' );
		$columns  = $config['columns'];
		$shown    = $config['max_items'] > 0 ? array_slice( $lines, 0, $config['max_items'] ) : $lines;
		$hidden   = count( $lines ) - count( $shown );
		$rows     = '';

		if ( $config['show_header'] ) {
			$heads = '';

			foreach ( $columns as $column ) {
				$heads .= '<th style="' . esc_attr( $this->table_style( $config, $column, true ) ) . '">'
					. esc_html( $this->column_label( $column ) )
					. '</th>';
			}

			$rows .= '<tr>' . $heads . '</tr>';
		}

		foreach ( $shown as $line ) {
			$cells = '';

			foreach ( $columns as $column ) {
				$cells .= '<td style="' . esc_attr( $this->table_style( $config, $column, false ) ) . '">'
					. $this->table_cell( $column, $line, $config, $currency )
					. '</td>';
			}

			$rows .= '<tr>' . $cells . '</tr>';
		}

		if ( $hidden > 0 ) {
			$rows .= '<tr><td colspan="' . count( $columns ) . '" style="' . esc_attr( $this->table_style( $config, 'name', false ) . ' color: #6b7280;' ) . '">'
				. esc_html(
					sprintf(
						/* translators: %d: number of cart items not listed. */
						_n( 'and %d more item', 'and %d more items', $hidden, 'cart-rebound' ),
						$hidden
					)
				)
				. '</td></tr>';
		}

		if ( $config['show_total_row'] ) {
			$total = $this->money( (float) ( $row['cart_total'] ?? 0 ), $currency );
			$span  = max( 1, count( $columns ) - 1 );
			$rows .= '<tr>'
				. '<td colspan="' . $span . '" style="' . esc_attr( $this->table_style( $config, 'name', false ) . ' font-weight: 600;' ) . '">'
				. esc_html__( 'Cart total', 'cart-rebound' )
				. '</td>'
				. '<td style="' . esc_attr( $this->table_style( $config, 'subtotal', false ) . ' font-weight: 600;' ) . '">'
				. esc_html( $total )
				. '</td>'
				. '</tr>';
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 12px 0; border-collapse: collapse; font-size: 14px;">'
			. $rows
			. '</table>';
	}

	/**
	 * Column heading text.
	 *
	 * @since 0.1.0
	 *
	 * @param string $column Column key.
	 * @return string
	 */
	private function column_label( string $column ): string {
		$labels = array(
			'image'    => '',
			'name'     => __( 'Item', 'cart-rebound' ),
			'sku'      => __( 'SKU', 'cart-rebound' ),
			'quantity' => __( 'Qty', 'cart-rebound' ),
			'price'    => __( 'Price', 'cart-rebound' ),
			'subtotal' => __( 'Total', 'cart-rebound' ),
		);

		return $labels[ $column ] ?? '';
	}

	/**
	 * Inline style for one table cell.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $config  Table options.
	 * @param string               $column  Column key.
	 * @param bool                 $heading Whether this is a heading cell.
	 * @return string
	 */
	private function table_style( array $config, string $column, bool $heading ): string {
		$style = 'padding: 8px 10px;';

		if ( 'boxed' === $config['style'] ) {
			$style .= ' border: 1px solid #e5e7eb;';

			if ( $heading ) {
				$style .= ' background: #f9fafb;';
			}
		} elseif ( 'lined' === $config['style'] ) {
			$style .= $heading
				? ' border-bottom: 2px solid #e5e7eb;'
				: ' border-bottom: 1px solid #e5e7eb;';
		}

		if ( $heading ) {
			$style .= ' font-weight: 600;';
		}

		$style .= in_array( $column, array( 'quantity', 'price', 'subtotal' ), true )
			? ' text-align: right;'
			: ' text-align: left;';

		if ( 'image' === $column ) {
			$style .= ' width: ' . ( (int) $config['image_size'] + 20 ) . 'px;';
		}

		return $style;
	}

	/**
	 * Render one table cell's contents.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $column   Column key.
	 * @param array<string, mixed> $line     Cart line.
	 * @param array<string, mixed> $config   Table options.
	 * @param string               $currency Store currency code.
	 * @return string
	 */
	private function table_cell( string $column, array $line, array $config, string $currency ): string {
		$quantity = (int) ( $line['quantity'] ?? 0 );
		$product  = $this->product( $line );
		$link     = $config['link_items'] ? $this->product_link( $line ) : '';

		if ( 'image' === $column ) {
			$size = (int) $config['image_size'];
			$src  = $this->product_image( $product, $size );

			if ( '' === $src ) {
				return '';
			}

			$img = '<img src="' . esc_url( $src ) . '" alt="" width="' . $size . '" height="' . $size . '" style="'
				. esc_attr( 'width: ' . $size . 'px; height: auto; border-radius: 4px; display: block;' )
				. '">';

			return '' !== $link ? '<a href="' . esc_url( $link ) . '" style="text-decoration: none;">' . $img . '</a>' : $img;
		}

		if ( 'name' === $column ) {
			$name = esc_html( $this->line_name( $line ) );
			$name = '' !== $link
				? '<a href="' . esc_url( $link ) . '" style="color: #1a1a1a; text-decoration: none;">' . $name . '</a>'
				: $name;

			$meta = $config['show_variations'] ? $this->variation_meta( $line ) : '';

			return $name . $meta;
		}

		if ( 'sku' === $column ) {
			return esc_html( null !== $product ? (string) $product->get_sku() : '' );
		}

		if ( 'quantity' === $column ) {
			return esc_html( (string) $quantity );
		}

		if ( 'price' === $column ) {
			return esc_html( $this->money( $this->unit_price( $line, $product, (bool) $config['with_tax'] ), $currency ) );
		}

		return esc_html( $this->money( $this->line_total( $line, $product, (bool) $config['with_tax'] ), $currency ) );
	}

	/**
	 * The chosen variation attributes as a small second line.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $line Cart line.
	 * @return string
	 */
	private function variation_meta( array $line ): string {
		if ( ! isset( $line['variation'] ) || ! is_array( $line['variation'] ) || array() === $line['variation'] ) {
			return '';
		}

		$parts = array();

		foreach ( $line['variation'] as $key => $value ) {
			$value = is_scalar( $value ) ? (string) $value : '';

			if ( '' === $value ) {
				continue;
			}

			$label   = str_replace( '-', ' ', (string) preg_replace( '/^attribute_(pa_)?/', '', (string) $key ) );
			$parts[] = ucfirst( $label ) . ': ' . $value;
		}

		if ( array() === $parts ) {
			return '';
		}

		return '<div style="margin-top: 2px; color: #6b7280; font-size: 12px;">' . esc_html( implode( ', ', $parts ) ) . '</div>';
	}

	/**
	 * Resolve the WooCommerce product behind a cart line, when it still exists.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $line Cart line.
	 * @return \WC_Product|null
	 */
	private function product( array $line ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$id = (int) ( $line['variation_id'] ?? 0 );

		if ( $id <= 0 ) {
			$id = (int) ( $line['product_id'] ?? 0 );
		}

		if ( $id <= 0 ) {
			return null;
		}

		$product = wc_get_product( $id );

		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Public URL of the product behind a cart line.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $line Cart line.
	 * @return string
	 */
	private function product_link( array $line ): string {
		$id = (int) ( $line['product_id'] ?? 0 );

		if ( $id <= 0 ) {
			$id = (int) ( $line['variation_id'] ?? 0 );
		}

		if ( $id <= 0 ) {
			return '';
		}

		$permalink = get_permalink( $id );

		return is_string( $permalink ) ? $permalink : '';
	}

	/**
	 * Thumbnail URL for a product, falling back to the WooCommerce placeholder.
	 *
	 * @since 0.1.0
	 *
	 * @param \WC_Product|null $product The product, when it still exists.
	 * @param int              $size    Requested pixel size.
	 * @return string
	 */
	private function product_image( $product, int $size ): string {
		if ( null !== $product ) {
			$image_id = (int) $product->get_image_id();

			if ( $image_id > 0 ) {
				$src = wp_get_attachment_image_url( $image_id, array( $size * 2, $size * 2 ) );

				if ( is_string( $src ) && '' !== $src ) {
					return $src;
				}
			}
		}

		return function_exists( 'wc_placeholder_img_src' ) ? (string) wc_placeholder_img_src() : '';
	}

	/**
	 * Unit price for a cart line, with tax when the template asks for it.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $line     Cart line.
	 * @param \WC_Product|null     $product  Resolved product, when available.
	 * @param bool                 $with_tax Whether to include tax.
	 * @return float
	 */
	private function unit_price( array $line, $product, bool $with_tax ): float {
		$price = (float) ( $line['price'] ?? 0 );

		if ( $with_tax && null !== $product && function_exists( 'wc_get_price_including_tax' ) ) {
			return (float) wc_get_price_including_tax( $product );
		}

		return $price;
	}

	/**
	 * Line total for a cart line, with tax when the template asks for it.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $line     Cart line.
	 * @param \WC_Product|null     $product  Resolved product, when available.
	 * @param bool                 $with_tax Whether to include tax.
	 * @return float
	 */
	private function line_total( array $line, $product, bool $with_tax ): float {
		$quantity = (int) ( $line['quantity'] ?? 0 );

		if ( $with_tax && null !== $product && function_exists( 'wc_get_price_including_tax' ) ) {
			return (float) wc_get_price_including_tax( $product, array( 'qty' => $quantity ) );
		}

		if ( isset( $line['line_total'] ) ) {
			return (float) $line['line_total'];
		}

		return (float) ( $line['price'] ?? 0 ) * $quantity;
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
