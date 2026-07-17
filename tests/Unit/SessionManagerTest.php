<?php
/**
 * Session-manager unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Tests\TestCase;
use CartRebound\Tracking\SessionManager;

/**
 * @covers \CartRebound\Tracking\SessionManager
 */
final class SessionManagerTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
	}

	protected function tear_down(): void {
		unset( $_COOKIE[ SessionManager::COOKIE ] );
		parent::tear_down();
	}

	public function test_accepts_a_valid_plugin_generated_cookie_key(): void {
		$key                               = str_repeat( 'aB3', 21 ) . 'x';
		$_COOKIE[ SessionManager::COOKIE ] = $key;

		$this->assertSame( $key, ( new SessionManager() )->resolve_session_key() );
	}

	public function test_rejects_a_predictable_numeric_cookie_key(): void {
		$_COOKIE[ SessionManager::COOKIE ] = '42';

		$this->assertSame( '', ( new SessionManager() )->resolve_session_key() );
	}

	public function test_rejects_non_scalar_cookie_input(): void {
		$_COOKIE[ SessionManager::COOKIE ] = array( '42' );

		$this->assertSame( '', ( new SessionManager() )->resolve_session_key() );
	}
}
