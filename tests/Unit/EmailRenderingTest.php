<?php
/**
 * Email token and markup extension-point tests.
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

/**
 * @covers \CartRebound\Mail\RecoveryMailer
 */
final class EmailRenderingTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		$GLOBALS['wpdb'] = new RenderingWpdb();

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				return strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
			}
		);
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'wpautop' )->returnArg();
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'template-id' );
		Functions\when( 'get_option' )->justReturn( null );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wc_get_cart_url' )->justReturn( 'https://shop.test/cart' );
		Functions\when( 'add_query_arg' )->justReturn( 'https://shop.test/cart?recover' );
		Functions\when( 'home_url' )->justReturn( 'https://shop.test/' );
		Functions\when( 'esc_html' )->alias(
			static function ( $value ) {
				return htmlspecialchars( (string) $value, ENT_QUOTES );
			}
		);
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_built_in_tokens_are_substituted(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$rendered = $this->mailer()->render(
			array(
				'first_name'     => 'Jordan',
				'recovery_token' => 'token',
				'cart_contents'  => '[]',
			),
			array(
				'subject' => 'Hi {first_name}',
				'body'    => 'Hello {first_name}, come back: {recovery_url}',
			)
		);

		$this->assertSame( 'Hi Jordan', $rendered['subject'] );
		$this->assertStringContainsString( 'Hello Jordan', $rendered['html'] );
		$this->assertStringContainsString( 'https://shop.test/cart?recover', $rendered['html'] );
	}

	public function test_an_extension_token_is_rendered(): void {
		$this->filter_tokens( array( 'coupon_expiry' => '24 hours' ) );

		$rendered = $this->mailer()->render(
			array( 'recovery_token' => 'token' ),
			array(
				'subject' => 'Expires in {coupon_expiry}',
				'body'    => 'Your code expires in {coupon_expiry}.',
			)
		);

		$this->assertSame( 'Expires in 24 hours', $rendered['subject'] );
		$this->assertStringContainsString( 'expires in 24 hours.', $rendered['html'] );
	}

	public function test_markup_in_an_extension_token_is_shown_not_rendered(): void {
		// The whole point of tokens being text: an add-on that forgets to escape
		// cannot inject markup into someone else's email.
		$this->filter_tokens( array( 'coupon_code' => '<script>alert(1)</script>' ) );

		$rendered = $this->mailer()->render(
			array( 'recovery_token' => 'token' ),
			array(
				'subject' => 'Code',
				'body'    => 'Use {coupon_code}',
			)
		);

		$this->assertStringNotContainsString( '<script>', $rendered['html'] );
		$this->assertStringContainsString( '&lt;script&gt;', $rendered['html'] );
	}

	public function test_a_non_scalar_token_value_is_dropped(): void {
		$this->filter_tokens( array( 'broken' => array( 'nope' ) ) );

		$rendered = $this->mailer()->render(
			array( 'recovery_token' => 'token' ),
			array(
				'subject' => 'Subject',
				'body'    => 'Value: {broken}',
			)
		);

		$this->assertStringContainsString( '{broken}', $rendered['html'] );
	}

	public function test_the_markup_filter_receives_the_finished_document(): void {
		$seen = '';

		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null, $context = null ) use ( &$seen ) {
				if ( 'cart_rebound_email_html' === $hook ) {
					$seen = (string) $value;

					return $value . '<img src="https://shop.test/pixel.gif" alt="" width="1" height="1" />';
				}

				return $value;
			}
		);

		$rendered = $this->mailer()->render(
			array( 'recovery_token' => 'token' ),
			array(
				'subject' => 'Subject',
				'body'    => 'Body copy',
			)
		);

		$this->assertStringContainsString( 'Body copy', $seen );
		$this->assertStringContainsString( 'pixel.gif', $rendered['html'] );
	}

	public function test_the_render_context_names_the_cart_and_the_template(): void {
		$context = array();

		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null, $passed = null ) use ( &$context ) {
				if ( 'cart_rebound_email_tokens' === $hook && is_array( $passed ) ) {
					$context = $passed;
				}

				return $value;
			}
		);

		$this->mailer()->render(
			array(
				'id'             => 42,
				'recovery_token' => 'token',
			),
			array(
				'id'      => 'tpl-7',
				'subject' => 'Subject',
				'body'    => 'Body',
			)
		);

		$this->assertSame( 42, $context['cart_id'] );
		$this->assertSame( 'tpl-7', $context['template_id'] );
		$this->assertNull( $context['step'] );
		$this->assertSame( -1, $context['step_index'] );
	}

	/**
	 * Make the token filter add the given tokens.
	 *
	 * @param array<string, string> $extra Tokens to merge in.
	 * @return void
	 */
	private function filter_tokens( array $extra ): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) use ( $extra ) {
				return 'cart_rebound_email_tokens' === $hook && is_array( $value )
					? array_merge( $value, $extra )
					: $value;
			}
		);
	}

	private function mailer(): RecoveryMailer {
		$settings = new Settings();

		return new RecoveryMailer( $settings, new RecoveryLink(), new TemplateStore( $settings ) );
	}
}

// phpcs:disable -- Minimal database fixture; rendering never reads a row.
class RenderingWpdb {

	/** @var string */
	public $prefix = 'wp_';

	public function prepare( $query, $args = array() ) {
		unset( $args );

		return (string) $query;
	}

	public function get_results( $query, $output = null ) {
		unset( $query, $output );

		return array();
	}
}
