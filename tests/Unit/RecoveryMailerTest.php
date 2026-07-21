<?php
/**
 * Recovery mailer unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Mail\TemplateStore;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Support\Settings;
use CartRebound\Tests\TestCase;
use WP_Error;

/**
 * @covers \CartRebound\Mail\RecoveryMailer
 */
final class RecoveryMailerTest extends TestCase {

	/** @var RecoveryMailerWpdb */
	private $wpdb;

	protected function set_up(): void {
		parent::set_up();

		$this->wpdb      = new RecoveryMailerWpdb();
		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'is_email' )->alias(
			static function ( $email ) {
				return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
			}
		);
		Functions\when( 'sanitize_email' )->returnArg();
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_send_now_explains_an_invalid_recipient(): void {
		$this->wpdb->results = array(
			array(
				'id'          => 7,
				'email'       => 'invalid-address',
				'items_count' => 1,
				'order_id'    => 0,
			),
		);

		$mailer = $this->mailer();

		$this->assertFalse( $mailer->send_now( 7 ) );
		$this->assertSame( 'This cart does not have a valid email address.', $mailer->get_last_error() );
	}

	public function test_send_now_explains_an_empty_cart(): void {
		$this->wpdb->results = array(
			array(
				'id'          => 7,
				'email'       => 'shopper@example.com',
				'items_count' => 0,
				'order_id'    => 0,
			),
		);

		$mailer = $this->mailer();

		$this->assertFalse( $mailer->send_now( 7 ) );
		$this->assertSame( 'This cart has no items to recover.', $mailer->get_last_error() );
	}

	public function test_send_now_explains_an_order_link(): void {
		$this->wpdb->results = array(
			array(
				'id'          => 7,
				'email'       => 'shopper@example.com',
				'items_count' => 1,
				'order_id'    => 99,
			),
		);

		$mailer = $this->mailer();

		$this->assertFalse( $mailer->send_now( 7 ) );
		$this->assertSame( 'This cart is already linked to an order.', $mailer->get_last_error() );
	}

	public function test_send_now_refuses_an_unsubscribed_address(): void {
		$this->wpdb->unsubscribed = true;
		$this->wpdb->results      = array(
			array(
				'id'          => 7,
				'email'       => 'shopper@example.com',
				'items_count' => 1,
				'order_id'    => 0,
			),
		);

		$mailer = $this->mailer();

		$this->assertFalse( $mailer->send_now( 7 ) );
		$this->assertSame( 'This address has unsubscribed from recovery emails.', $mailer->get_last_error() );
	}

	public function test_mail_error_preserves_the_transport_message(): void {
		$mailer = $this->mailer();

		$mailer->capture_mail_error( new WP_Error( 'wp_mail_failed', 'SMTP authentication failed.' ) );

		$this->assertSame(
			'WordPress could not send the email: SMTP authentication failed.',
			$mailer->get_last_error()
		);
	}

	public function test_send_now_distinguishes_a_mail_transport_failure(): void {
		$this->wpdb->results = array(
			array(
				'id'             => 7,
				'email'          => 'shopper@example.com',
				'first_name'     => 'Shopper',
				'items_count'    => 1,
				'order_id'       => 0,
				'cart_contents'  => '[]',
				'recovery_token' => 'token',
			),
		);

		Functions\when( 'get_option' )->justReturn( null );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'template-id' );
		Functions\when( 'wpautop' )->returnArg();
		Functions\when( 'wc_get_cart_url' )->justReturn( 'https://shop.test/cart' );
		Functions\when( 'add_query_arg' )->justReturn( 'https://shop.test/cart?recover' );
		Functions\when( 'home_url' )->justReturn( 'https://shop.test/' );
		Functions\when( 'wp_mail' )->justReturn( false );

		$mailer = $this->mailer();

		$this->assertFalse( $mailer->send_now( 7 ) );
		$this->assertSame(
			'WordPress could not send the email. Check the site SMTP or mail transport configuration and try again.',
			$mailer->get_last_error()
		);
	}

	private function mailer(): RecoveryMailer {
		$settings = new Settings();

		return new RecoveryMailer( $settings, new RecoveryLink(), new TemplateStore( $settings ) );
	}
}

// phpcs:disable -- Lightweight database fixture for model lookups.
class RecoveryMailerWpdb {

	/** @var string */
	public $prefix = 'wp_';

	/** @var array<int, array<string, mixed>> */
	public $results = array();

	/** @var bool */
	public $unsubscribed = false;

	public function prepare( $query, $args = array() ) {
		$flat = is_array( $args ) ? $args : array( $args );

		// Interpolate %i/%s/%d in order so the target table name is visible to
		// get_results() (which routes the suppression lookup to an empty result).
		return (string) preg_replace_callback(
			'/%[isd]/',
			static function () use ( &$flat ) {
				return (string) array_shift( $flat );
			},
			(string) $query
		);
	}

	public function get_results( $query, $output ) {
		unset( $output );

		// Route the suppression lookup (against the unsubscribes table specifically,
		// not any query whose values merely contain the word) to a controllable
		// result so fixtures decide whether an address is unsubscribed.
		if ( is_string( $query ) && false !== strpos( $query, 'cart_rebound_unsubscribes' ) ) {
			return $this->unsubscribed
				? array( array( 'id' => 1, 'email' => 'shopper@example.com' ) )
				: array();
		}

		return $this->results;
	}
}
