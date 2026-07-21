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
}
