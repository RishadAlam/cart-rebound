<?php
/**
 * Recovery email sender.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Mail;

defined( 'ABSPATH' ) || exit;

use CartRebound\Admin\Menu;
use CartRebound\Admin\ReviewPrompt;
use CartRebound\Events\EventDispatcher;
use CartRebound\Models\CartSession;
use CartRebound\Models\Unsubscribe;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Support\Branding;
use CartRebound\Support\Settings;
use WC_Order;
use WP_Error;

/**
 * Sends the optional single recovery email, scheduled a delay after abandonment.
 *
 * Restores the wp_mail content type after sending so it never leaks to other mail.
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
	 * Event dispatcher.
	 *
	 * @since 1.1.2
	 * @var EventDispatcher
	 */
	private $events;

	/**
	 * Review request appended to the admin recovery email.
	 *
	 * @since 1.1.3
	 * @var ReviewPrompt
	 */
	private $review;

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
	 * @param Settings        $settings  Settings store.
	 * @param RecoveryLink    $links     Recovery link builder.
	 * @param TemplateStore   $templates Email template store.
	 * @param EventDispatcher $events    Event dispatcher.
	 * @param ReviewPrompt    $review    Review request.
	 */
	public function __construct( Settings $settings, RecoveryLink $links, TemplateStore $templates, EventDispatcher $events, ReviewPrompt $review ) {
		$this->settings  = $settings;
		$this->links     = $links;
		$this->templates = $templates;
		$this->events    = $events;
		$this->review    = $review;
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

		if ( 1 === (int) ( $row['email_sent'] ?? 0 ) || (int) ( $row['order_id'] ?? 0 ) > 0 ) {
			return;
		}

		if ( (int) ( $row['items_count'] ?? 0 ) <= 0 || $this->nothing_left( $row ) ) {
			return;
		}

		if ( Unsubscribe::is_suppressed( $email ) ) {
			return;
		}

		$template = $this->templates->default();
		$sent     = $this->dispatch( $email, $row, $template );

		if ( ! $sent ) {
			$this->events->email_failed( $row, $template, 'automatic' );

			return;
		}

		CartSession::update( $cart_id, array( 'email_sent' => 1 ) );
		$this->events->email_sent( array_merge( $row, array( 'email_sent' => 1 ) ), $template, 'automatic' );

		/**
		 * Fires after a recovery email is sent (drives the activity log).
		 *
		 * Prefer `cart_rebound_recovery_email_sent`, which carries the flat payload.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $cart_id  The cart id.
		 * @param array<string, mixed> $row      The cart row.
		 * @param array<string, mixed> $template The template that was sent.
		 */
		do_action( 'cart_rebound_email_sent', $cart_id, $row, $template );
	}

	/**
	 * Email the site admin that a tracked cart was recovered into a paid order.
	 *
	 * Sends an HTML email with the same content as a plain-text alternative.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row    The recovered cart row.
	 * @param WC_Order             $order  The paid order that recovered it.
	 * @param string               $method How the shopper came back: 'email_link' or 'direct'.
	 * @return void
	 */
	public function notify_admin( array $row, WC_Order $order, string $method = 'direct' ): void {
		if ( ! $this->settings->get( 'admin_recovery_email' ) ) {
			return;
		}

		$recipient = (string) $this->settings->get( 'admin_notification_email', '' );

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$recipient = (string) get_option( 'admin_email' );
		}

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return;
		}

		$summary    = $this->recovery_summary( $row, $order, $method );
		$ask_review = $this->review->claim_email();

		$subject = '' !== $summary['amount']
			? sprintf(
				/* translators: 1: formatted order total, 2: order number. */
				__( '[Cart Rebound] %1$s recovered — order #%2$s', 'cart-rebound' ),
				$summary['amount'],
				$summary['number']
			)
			: sprintf(
				/* translators: %s: order number. */
				__( '[Cart Rebound] Abandoned cart recovered — order #%s', 'cart-rebound' ),
				$summary['number']
			);

		$text = $this->admin_text( $summary, $ask_review );
		$html = $this->admin_html( $summary, $ask_review );

		if ( ! $this->send_admin( $recipient, $subject, $html, $text ) && $ask_review ) {
			$this->review->release_email();
		}
	}

	/**
	 * The facts the admin recovery email reports, as plain text.
	 *
	 * @since 1.1.3
	 *
	 * @param array<string, mixed> $row    The recovered cart row.
	 * @param WC_Order             $order  The paid order.
	 * @param string               $method 'email_link' or 'direct'.
	 * @return array{number: string, amount: string, customer: string, contact: string, via: string, paid_on: string, order_url: string}
	 */
	private function recovery_summary( array $row, WC_Order $order, string $method ): array {
		$name    = trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
		$email   = (string) ( $row['email'] ?? '' );
		$contact = '' !== $email ? $email : (string) $order->get_billing_email();
		$total   = (float) $order->get_total();
		$paid    = $order->get_date_paid();
		$format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return array(
			'number'    => (string) $order->get_order_number(),
			'amount'    => $total > 0 ? $this->money( $total, (string) $order->get_currency() ) : '',
			'customer'  => '' !== $name ? $name : ( '' !== $contact ? $contact : __( '(unknown)', 'cart-rebound' ) ),
			'contact'   => '' !== $name ? $contact : '',
			'via'       => 'email_link' === $method
				? __( 'Recovery email link', 'cart-rebound' )
				: __( 'The customer came back on their own', 'cart-rebound' ),
			'paid_on'   => (string) wp_date( $format, null !== $paid ? $paid->getTimestamp() : time() ),
			'order_url' => (string) $order->get_edit_order_url(),
		);
	}

	/**
	 * The admin email heading, split around the amount so it can be highlighted.
	 *
	 * @since 1.1.3
	 *
	 * @param string $amount Formatted amount, or '' for a free order.
	 * @return array{before: string, amount: string, after: string}
	 */
	private function admin_headline( string $amount ): array {
		if ( '' === $amount ) {
			return array(
				'before' => __( 'Abandoned cart recovered', 'cart-rebound' ),
				'amount' => '',
				'after'  => '',
			);
		}

		$slot  = "\0";
		$parts = explode(
			$slot,
			/* translators: %s: formatted recovered amount. */
			sprintf( __( '%s recovered', 'cart-rebound' ), $slot ),
			2
		);

		return array(
			'before' => $parts[0],
			'amount' => $amount,
			'after'  => $parts[1] ?? '',
		);
	}

	/**
	 * The sentence under the admin email heading.
	 *
	 * @since 1.1.3
	 *
	 * @param string $number Order number.
	 * @return string
	 */
	private function admin_intro( string $number ): string {
		return sprintf(
			/* translators: %s: order number. */
			__( 'Order #%s was paid after its cart had been abandoned.', 'cart-rebound' ),
			$number
		);
	}

	/**
	 * Order facts as label/value rows for the admin email.
	 *
	 * @since 1.1.3
	 *
	 * @param array{number: string, customer: string, contact: string, via: string, paid_on: string} $summary Recovery facts.
	 * @return array<int, array{label: string, value: string, note: string}>
	 */
	private function admin_details( array $summary ): array {
		return array(
			array(
				'label' => __( 'Order', 'cart-rebound' ),
				'value' => '#' . $summary['number'],
				'note'  => '',
			),
			array(
				'label' => __( 'Customer', 'cart-rebound' ),
				'value' => $summary['customer'],
				'note'  => $summary['contact'],
			),
			array(
				'label' => __( 'Recovered via', 'cart-rebound' ),
				'value' => $summary['via'],
				'note'  => '',
			),
			array(
				'label' => __( 'Paid', 'cart-rebound' ),
				'value' => $summary['paid_on'],
				'note'  => '',
			),
		);
	}

	/**
	 * The plain-text admin recovery email, also sent as the HTML version's alternative.
	 *
	 * @since 1.1.3
	 *
	 * @param array{number: string, amount: string, customer: string, contact: string, via: string, paid_on: string, order_url: string} $summary    Recovery facts.
	 * @param bool                                                                                                                      $ask_review Whether to append the review request.
	 * @return string
	 */
	private function admin_text( array $summary, bool $ask_review ): string {
		$lines = array(
			implode( '', $this->admin_headline( $summary['amount'] ) ),
			$this->admin_intro( $summary['number'] ),
			'',
		);

		foreach ( $this->admin_details( $summary ) as $detail ) {
			$lines[] = $detail['label'] . ': ' . $detail['value'] . ( '' !== $detail['note'] ? ' (' . $detail['note'] . ')' : '' );
		}

		$lines = array_merge(
			$lines,
			array(
				'',
				__( 'View order:', 'cart-rebound' ),
				$summary['order_url'],
			)
		);

		if ( $ask_review ) {
			$lines = array_merge( $lines, $this->review_request_lines() );
		}

		$lines[] = '';
		$lines[] = '—';
		$lines[] = __( 'You get this email because “Notify admin on recovery” is on in Cart Rebound. Change email settings:', 'cart-rebound' );
		$lines[] = $this->settings_url();

		return implode( "\n", $lines );
	}

	/**
	 * The HTML admin recovery email, or '' when the view is missing.
	 *
	 * @since 1.1.3
	 *
	 * @param array{number: string, amount: string, customer: string, contact: string, via: string, paid_on: string, order_url: string} $summary    Recovery facts.
	 * @param bool                                                                                                                      $ask_review Whether to include the review request.
	 * @return string
	 */
	private function admin_html( array $summary, bool $ask_review ): string {
		$template_path = defined( 'CART_REBOUND_PATH' ) ? CART_REBOUND_PATH . 'resources/views/emails/admin-recovered.php' : '';

		if ( '' === $template_path || ! is_readable( $template_path ) ) {
			return '';
		}

		$recoveries    = $this->review->recoveries();
		$store_name    = Branding::store_name();
		$headline      = $this->admin_headline( $summary['amount'] );
		$intro         = $this->admin_intro( $summary['number'] );
		$preheader     = $intro;
		$details       = $this->admin_details( $summary );
		$order_url     = $summary['order_url'];
		$dashboard_url = admin_url( 'admin.php?page=' . Menu::SLUG );
		$settings_url  = $this->settings_url();
		$review        = $ask_review
			? array(
				'count' => sprintf(
					/* translators: %s: number of abandoned carts Cart Rebound has recovered. */
					_n( '%s abandoned cart recovered so far.', '%s abandoned carts recovered so far.', $recoveries, 'cart-rebound' ),
					number_format_i18n( $recoveries )
				),
				'url'   => ReviewPrompt::REVIEW_URL,
			)
			: null;

		ob_start();
		require $template_path;
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Send the admin email as HTML with a plain-text alternative.
	 *
	 * The From name changes only while it is still WordPress's generic default.
	 *
	 * @since 1.1.3
	 *
	 * @param string $recipient Recipient address.
	 * @param string $subject   Subject line.
	 * @param string $html      HTML body, or '' to send plain text only.
	 * @param string $text      Plain-text body.
	 * @return bool
	 */
	private function send_admin( string $recipient, string $subject, string $html, string $text ): bool {
		if ( '' === $html ) {
			return wp_mail( $recipient, $subject, $text );
		}

		$alternative = static function ( $mailer ) use ( $text ): void {
			$mailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
		};
		$from_name   = static function ( $name ) {
			return 'WordPress' === $name ? __( 'Cart Rebound', 'cart-rebound' ) : $name;
		};

		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		add_filter( 'wp_mail_from_name', $from_name );
		add_action( 'phpmailer_init', $alternative );

		try {
			return wp_mail( $recipient, $subject, $html );
		} finally {
			remove_action( 'phpmailer_init', $alternative );
			remove_filter( 'wp_mail_from_name', $from_name );
			remove_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		}
	}

	/**
	 * The Cart Rebound settings screen URL.
	 *
	 * @since 1.1.3
	 *
	 * @return string
	 */
	private function settings_url(): string {
		return admin_url( 'admin.php?page=' . Menu::SLUG . '-settings' );
	}

	/**
	 * The plain-text review request appended to the admin recovery email.
	 *
	 * @since 1.1.3
	 *
	 * @return array<int, string>
	 */
	private function review_request_lines(): array {
		$recoveries = $this->review->recoveries();

		return array(
			'',
			'—',
			__( 'How has Cart Rebound worked for you?', 'cart-rebound' ),
			sprintf(
				/* translators: %s: number of abandoned carts Cart Rebound has recovered. */
				_n( '%s abandoned cart recovered so far.', '%s abandoned carts recovered so far.', $recoveries, 'cart-rebound' ),
				number_format_i18n( $recoveries )
			) . ' ' . __( 'Your honest review helps other store owners decide.', 'cart-rebound' ),
			'',
			__( 'Write a review (WordPress.org account required):', 'cart-rebound' ),
			ReviewPrompt::REVIEW_URL,
			'',
			__( 'Not now? You can be reminded later, or not asked again, from your Cart Rebound dashboard:', 'cart-rebound' ),
			admin_url( 'admin.php?page=' . Menu::SLUG ),
		);
	}

	/**
	 * Send the recovery email right now, on demand (admin "send email" action).
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

		if ( $this->nothing_left( $row ) ) {
			return $this->fail( __( 'Every product in this cart has since been deleted, so there is nothing to recover.', 'cart-rebound' ) );
		}

		if ( (int) ( $row['order_id'] ?? 0 ) > 0 ) {
			return $this->fail( __( 'This cart is already linked to an order.', 'cart-rebound' ) );
		}

		if ( Unsubscribe::is_suppressed( $email ) ) {
			return $this->fail( __( 'This address has unsubscribed from recovery emails.', 'cart-rebound' ) );
		}

		$chosen   = '' !== $template_id ? $this->templates->get( $template_id ) : null;
		$template = is_array( $chosen ) ? $chosen : $this->templates->default();
		$sent     = $this->dispatch( $email, $row, $template );

		if ( ! $sent ) {
			$this->events->email_failed( $row, $template, 'manual' );

			return false;
		}

		CartSession::update( $cart_id, array( 'email_sent' => 1 ) );
		$this->events->email_sent( array_merge( $row, array( 'email_sent' => 1 ) ), $template, 'manual' );

		/** This action is documented in src/Mail/RecoveryMailer.php */
		do_action( 'cart_rebound_email_sent', $cart_id, $row, $template );

		return true;
	}

	/**
	 * Send a test render of a template to an address (admin "send test" action).
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
	 * Attached only for this mailer's own send, so unrelated site email failures never leak
	 * into the admin response.
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
			'abandoned_at'   => current_time( 'mysql', true ),
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

		// A subject line cannot carry markup, so the list tokens fall back to plain names.
		$tokens['{products}']        = $tokens['{product_names}'];
		$tokens['{products_table}']  = $tokens['{product_names}'];
		$tokens['{recovery_button}'] = '';

		$filled  = trim( $this->tidy_blank_tokens( str_replace( array_keys( $tokens ), array_values( $tokens ), (string) ( $template['subject'] ?? '' ) ) ) );
		$subject = ltrim( $filled, " ,\t" );

		// Only re-capitalise when a blank leading tag was stripped ("{first_name}, your cart…").
		if ( $subject === $filled || '' === $subject || ! function_exists( 'mb_substr' ) ) {
			return $subject;
		}

		return mb_strtoupper( mb_substr( $subject, 0, 1 ) ) . mb_substr( $subject, 1 );
	}

	/**
	 * Close the gap a blank merge tag leaves before a comma ("Hi {first_name}," becomes "Hi,").
	 *
	 * @since 1.1.3
	 *
	 * @param string $text Text after token substitution.
	 * @return string
	 */
	private function tidy_blank_tokens( string $text ): string {
		return (string) preg_replace( '/[ \t]+,/u', ',', $text );
	}

	/**
	 * Every merge tag and its plain-text value for a cart.
	 *
	 * Values are unescaped; callers escape per context.
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
			'{store_name}'      => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
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

		$time = strtotime( $stamp . ' UTC' );

		return false === $time ? '' : (string) wp_date( (string) get_option( 'date_format' ), $time );
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
	 * A human name for whoever runs the store — the admin user's first name, else "our team".
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private function manager_name(): string {
		$admin = get_user_by( 'email', (string) get_option( 'admin_email' ) );

		$name = '';

		if ( $admin instanceof \WP_User ) {
			$name = '' !== (string) $admin->first_name ? (string) $admin->first_name : (string) $admin->display_name;
		}

		return '' !== $name ? $name : __( 'our team', 'cart-rebound' );
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
			if ( is_array( $line ) && ! $this->is_withdrawn( $line ) ) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * Whether a cart had stored lines but every one of their products is gone.
	 *
	 * @since 1.1.3
	 *
	 * @param array<string, mixed> $row Cart row.
	 * @return bool
	 */
	private function nothing_left( array $row ): bool {
		$stored = ( isset( $row['cart_contents'] ) && is_string( $row['cart_contents'] ) )
			? json_decode( $row['cart_contents'], true )
			: null;

		return is_array( $stored ) && array() !== $stored && array() === $this->items( $row );
	}

	/**
	 * Whether a cart line points at a product that was deleted or trashed since capture.
	 *
	 * @since 1.1.3
	 *
	 * @param array<string, mixed> $line Cart line.
	 * @return bool
	 */
	private function is_withdrawn( array $line ): bool {
		if ( (int) ( $line['product_id'] ?? 0 ) <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = $this->product( $line );

		return null === $product || 'trash' === $product->get_status();
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
		$headers = $this->headers( $template, $this->links->unsubscribe_url( (string) ( $row['recovery_token'] ?? '' ) ) );

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

		$recovery_url    = $tokens['{recovery_url}'];
		$unsubscribe_url = $tokens['{unsubscribe_url}'];

		// Run wpautop before substitution: it closes an open <p> at block markup,
		// which would leave a stray </p> inside the injected product table. A list
		// tag on its own line is then unwrapped so the table never sits inside <p>.
		$html = wpautop( (string) ( $template['body'] ?? '' ) );
		$html = (string) preg_replace( '#<p>\s*(\{(?:products|products_table|recovery_button)\})\s*</p>#', '$1', $html );

		if ( '' === $tokens['{coupon_code}'] ) {
			$html = (string) preg_replace( '#<p\b[^>]*>(?:(?!</p>).)*\{coupon_code\}(?:(?!</p>).)*</p>\s*#is', '', $html );
		}

		$content = $this->tidy_blank_tokens( str_replace( array_keys( $replacements ), array_values( $replacements ), $html ) );

		// The markup tokens are escaped as they are built.
		$button      = $this->button_html( $template, $recovery_url );
		$placed      = false !== strpos( $content, '{recovery_button}' );
		$content     = str_replace(
			array( '{products}', '{products_table}', '{recovery_button}' ),
			array( $this->products_html( $row ), $this->products_table_html( $row, $template ), $button ),
			$content
		);
		$button_html = $placed ? '' : $button;

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
	 * The recovery call-to-action as a bulletproof (Outlook-safe) table button, or '' when switched off.
	 *
	 * @since 1.1.3
	 *
	 * @param array<string, mixed> $template     The email template.
	 * @param string               $recovery_url Tokenised recovery URL.
	 * @return string
	 */
	private function button_html( array $template, string $recovery_url ): string {
		$config = TemplateStore::button_config( $template['button'] ?? array() );

		if ( ! $config['enabled'] ) {
			return '';
		}

		$theme                       = Branding::colors();
		$background                  = '' !== $config['background'] ? $config['background'] : $theme['accent'];
		$ink                         = '' !== $config['text_color']
			? $config['text_color']
			: ( '' !== $config['background'] ? Branding::readable_on( $background ) : $theme['ink'] );
		$text                        = '' !== $config['text'] ? $config['text'] : __( 'Complete your order', 'cart-rebound' );
		$metrics                     = array(
			'small'  => array( '8px 16px', 14 ),
			'medium' => array( '12px 24px', 16 ),
			'large'  => array( '16px 32px', 18 ),
		);
		list( $padding, $font_size ) = $metrics[ $config['size'] ];

		$radius = (int) $config['radius'] . 'px';
		$width  = $config['full_width'] ? ' width="100%"' : '';
		$link   = sprintf(
			'<a href="%1$s" style="%2$s">%3$s</a>',
			esc_url( $recovery_url ),
			esc_attr( "display: block; padding: {$padding}; border-radius: {$radius}; color: {$ink}; font-size: {$font_size}px; font-weight: 600; line-height: 1.25; text-align: center; text-decoration: none;" ),
			esc_html( $text )
		);

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 32px 0;"><tr>'
			. '<td align="' . esc_attr( $config['align'] ) . '">'
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0"' . $width . '><tr>'
			. '<td align="center" bgcolor="' . esc_attr( $background ) . '" style="' . esc_attr( "background: {$background}; border-radius: {$radius};" ) . '">'
			. $link
			. '</td></tr></table></td></tr></table>';
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
					/* translators: 1: product name, 2: quantity. */
					__( '%1$s × %2$d', 'cart-rebound' ),
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
	 * Inline styles and a plain table layout only, because email clients drop stylesheets.
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

			$img = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $this->line_name( $line ) ) . '" width="' . $size . '" style="'
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

			$parts[] = $this->attribute_label( (string) $key ) . ': ' . $this->attribute_value( (string) $key, $value );
		}

		if ( array() === $parts ) {
			return '';
		}

		return '<div style="margin-top: 2px; color: #6b7280; font-size: 12px;">' . esc_html( implode( ', ', $parts ) ) . '</div>';
	}

	/**
	 * A human label for a stored variation key such as attribute_pa_size.
	 *
	 * @since 1.1.3
	 *
	 * @param string $key Stored variation key.
	 * @return string
	 */
	private function attribute_label( string $key ): string {
		$name  = (string) preg_replace( '/^attribute_/', '', $key );
		$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $name ) : $name;

		// wc_attribute_label() echoes the slug back when the attribute is gone or local.
		if ( $label === $name ) {
			$label = ucfirst( str_replace( array( '-', '_' ), ' ', (string) preg_replace( '/^pa_/', '', rawurldecode( $name ) ) ) );
		}

		return $label;
	}

	/**
	 * The display value for a variation: a global attribute's term name, else the raw text.
	 *
	 * @since 1.1.3
	 *
	 * @param string $key   Stored variation key.
	 * @param string $value Stored value (a term slug for global attributes).
	 * @return string
	 */
	private function attribute_value( string $key, string $value ): string {
		$taxonomy = (string) preg_replace( '/^attribute_/', '', $key );

		if ( taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, $taxonomy );

			if ( $term instanceof \WP_Term ) {
				return $term->name;
			}
		}

		return rawurldecode( $value );
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
	 * Build the From header (when configured) and the one-click unsubscribe headers.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $template        The email template.
	 * @param string               $unsubscribe_url Tokenised unsubscribe URL; empty skips List-Unsubscribe.
	 * @return array<int, string>
	 */
	private function headers( array $template, string $unsubscribe_url ): array {
		$name    = (string) ( $template['from_name'] ?? '' );
		$from    = (string) ( $template['from_email'] ?? '' );
		$headers = array();

		if ( '' !== $from && is_email( $from ) ) {
			$label     = '' !== $name ? $name : $from;
			$headers[] = sprintf( 'From: %1$s <%2$s>', $label, $from );
		}

		// The starter copy invites replies, which must not land on wordpress@ when From is unset.
		$headers[] = sprintf( 'Reply-To: %s', $this->store_email() );

		// RFC 8058 one-click unsubscribe, which Gmail and Yahoo expect from bulk senders.
		if ( '' !== $unsubscribe_url ) {
			$headers[] = sprintf( 'List-Unsubscribe: <%s>', esc_url_raw( $unsubscribe_url ) );
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}

		return $headers;
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
