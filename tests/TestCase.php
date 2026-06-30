<?php
/**
 * Base test case.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Shared base test case wiring Brain\Monkey for WordPress function mocking.
 */
abstract class TestCase extends PolyfillTestCase {

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();

		// Escaping/i18n helpers return their first argument in unit tests.
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
