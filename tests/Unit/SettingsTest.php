<?php
/**
 * Settings unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Support\Settings;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Support\Settings
 */
final class SettingsTest extends TestCase {

	public function test_defaults_include_expected_keys(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$all = ( new Settings() )->all();

		$this->assertFalse( $all['guest_tracking'] );
		$this->assertSame( 30, $all['abandonment_threshold'] );
		$this->assertSame( 30, $all['cleanup_days'] );
		$this->assertSame( 365, $all['converted_cleanup_days'] );
		$this->assertFalse( $all['recovery_email_enabled'] );
	}

	public function test_get_prefers_stored_value_over_default(): void {
		Functions\when( 'get_option' )->justReturn( array( 'abandonment_threshold' => 10 ) );

		$settings = new Settings();

		$this->assertSame( 10, $settings->get( 'abandonment_threshold' ) );
		$this->assertFalse( $settings->get( 'guest_tracking' ) );
	}

	public function test_get_unknown_key_uses_fallback(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$this->assertSame( 'fallback', ( new Settings() )->get( 'does_not_exist', 'fallback' ) );
	}

	public function test_update_sanitises_and_persists(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
			}
		);

		$saved = null;
		Functions\when( 'update_option' )->alias(
			static function ( $key, $value ) use ( &$saved ): bool {
				$saved = $value;

				return true;
			}
		);

		$result = ( new Settings() )->update(
			array(
				'abandonment_threshold'  => '9',
				'converted_cleanup_days' => '-20',
				'guest_tracking'         => '1',
				'recovery_email_enabled' => '',
			)
		);

		$this->assertSame( 9, $result['abandonment_threshold'] );
		$this->assertSame( 1, $result['converted_cleanup_days'] );
		$this->assertTrue( $result['guest_tracking'] );
		$this->assertFalse( $result['recovery_email_enabled'] );
		$this->assertSame( $result, $saved );
	}

	public function test_admin_notification_email_defaults_empty_and_sanitises(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'update_option' )->justReturn( true );

		$settings = new Settings();

		$this->assertSame( '', $settings->all()['admin_notification_email'] );

		$result = $settings->update( array( 'admin_notification_email' => 'owner@example.com' ) );

		$this->assertSame( 'owner@example.com', $result['admin_notification_email'] );
	}

	public function test_paid_statuses_drop_the_wc_prefix_and_reject_unknown_slugs(): void {
		$this->stub_option_writes();
		Functions\when( 'wc_get_order_statuses' )->justReturn(
			array(
				'wc-pending'    => 'Pending payment',
				'wc-processing' => 'Processing',
				'wc-completed'  => 'Completed',
				'wc-on-hold'    => 'On hold',
			)
		);

		$result = ( new Settings() )->update(
			array(
				'paid_order_statuses' => array( 'wc-processing', 'completed', 'not-a-status', 'processing' ),
			)
		);

		$this->assertSame( array( 'processing', 'completed' ), $result['paid_order_statuses'] );
	}

	public function test_paid_statuses_fall_back_when_nothing_valid_survives(): void {
		$this->stub_option_writes();
		Functions\when( 'wc_get_order_statuses' )->justReturn( array( 'wc-processing' => 'Processing' ) );

		$result = ( new Settings() )->update( array( 'paid_order_statuses' => array( 'bogus', '' ) ) );

		$this->assertSame( array( 'processing', 'completed' ), $result['paid_order_statuses'] );
	}

	/**
	 * Stub the WordPress helpers Settings::update() needs to persist a value.
	 *
	 * @return void
	 */
	private function stub_option_writes(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'update_option' )->justReturn( true );
	}
}
