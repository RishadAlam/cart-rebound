<?php
/**
 * Runtime requirements unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Support\Requirements;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Support\Requirements
 */
final class RequirementsTest extends TestCase {

	public function test_reports_woocommerce_as_missing_when_its_bootstrap_class_is_absent(): void {
		$this->assertFalse( Requirements::has_woocommerce() );
	}

	public function test_notice_is_hidden_from_users_who_cannot_activate_plugins(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'activate_plugins' )
			->andReturn( false );

		ob_start();
		Requirements::render_admin_notice();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_notice_explains_the_missing_dependency_to_administrators(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'activate_plugins' )
			->andReturn( true );

		ob_start();
		Requirements::render_admin_notice();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Cart Rebound requires WooCommerce', $output );
	}
}
