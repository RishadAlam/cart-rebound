<?php

declare( strict_types=1 );

namespace CartRebound\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

abstract class TestCase extends PolyfillTestCase {

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		Functions\stubs(
			array(
				'esc_html',
				'esc_attr',
				'esc_url',
				'esc_html__',
				'esc_attr__',
				'__',
			)
		);

		Functions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
			}
		);
	}

	/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}
}
