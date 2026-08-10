<?php
/**
 * Add-on registry unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Extend\Feature;
use CartRebound\Extend\Registry;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Extend\Registry
 * @covers \CartRebound\Extend\Addon
 * @covers \CartRebound\Extend\Feature
 */
final class AddonRegistryTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'esc_url_raw' )->returnArg();
	}

	public function test_a_site_with_no_add_ons_delivers_no_features(): void {
		$registry = new Registry();

		$this->assertFalse( $registry->has_addons() );
		$this->assertFalse( $registry->is_delivering() );
		$this->assertSame( array(), $registry->features() );
		$this->assertFalse( $registry->has( Feature::SEQUENCE ) );
	}

	public function test_an_add_on_delivers_the_features_it_reports(): void {
		$registry = $this->registry(
			array(
				'slug'     => 'cart-rebound-pro',
				'name'     => 'Cart Rebound Pro',
				'version'  => '1.0.0',
				'features' => array( Feature::SEQUENCE, Feature::COUPONS ),
			)
		);

		$this->assertTrue( $registry->has_addons() );
		$this->assertTrue( $registry->is_delivering() );
		$this->assertTrue( $registry->has( Feature::SEQUENCE ) );
		$this->assertTrue( $registry->has( Feature::COUPONS ) );
		$this->assertFalse( $registry->has( Feature::ANALYTICS ) );
	}

	public function test_a_dormant_add_on_unlocks_nothing(): void {
		$registry = $this->registry(
			array(
				'slug'         => 'cart-rebound-pro',
				'features'     => array(),
				'settings_url' => 'https://shop.test/wp-admin/admin.php?page=addon',
			)
		);

		// Installed, so its own screens are reachable and the lock can point at
		// them. Why it is delivering nothing is the add-on's business — this
		// plugin has no concept of a licence to ask about.
		$this->assertTrue( $registry->has_addons() );
		$this->assertFalse( $registry->is_delivering() );
		$this->assertSame( array(), $registry->features() );
		$this->assertSame( 'https://shop.test/wp-admin/admin.php?page=addon', $registry->settings_url() );
	}

	public function test_an_unrecognised_feature_key_unlocks_nothing(): void {
		$registry = $this->registry(
			array(
				'slug'     => 'rogue',
				'features' => array( 'sequenec', 'everything', '*', Feature::RULES ),
			)
		);

		$this->assertSame( array( Feature::RULES ), $registry->features() );
	}

	public function test_an_add_on_without_a_slug_is_refused(): void {
		$registry = new Registry();

		$this->assertFalse( $registry->register( array( 'name' => 'Nameless' ) ) );
	}

	public function test_the_registration_action_runs_once_however_often_it_is_asked(): void {
		$calls    = 0;
		$registry = new Registry();

		Functions\when( 'do_action' )->alias(
			static function ( $hook ) use ( &$calls ): void {
				if ( 'cart_rebound_register_addons' === $hook ) {
					++$calls;
				}
			}
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$registry->has_addons();
		$registry->features();
		$registry->state();

		$this->assertSame( 1, $calls );
	}

	public function test_state_reports_everything_the_admin_app_gates_on(): void {
		$registry = $this->registry(
			array(
				'slug'         => 'cart-rebound-pro',
				'name'         => 'Cart Rebound Pro',
				'version'      => '1.2.3',
				'url'          => 'https://example.test/pro',
				'settings_url' => 'https://shop.test/wp-admin/admin.php?page=addon',
				'features'     => array( Feature::ANALYTICS ),
			)
		);

		$state = $registry->state();

		$this->assertTrue( $state['installed'] );
		$this->assertSame( array( Feature::ANALYTICS ), $state['features'] );
		$this->assertSame( 'cart-rebound-pro', $state['addons'][0]['slug'] );
		$this->assertSame( '1.2.3', $state['addons'][0]['version'] );
		$this->assertSame( 'https://cart-rebound.test/upgrade', $state['upgrade_url'] );

		// Nothing in the state names a licence: the concept does not exist here.
		$this->assertArrayNotHasKey( 'licensed', $state );
		$this->assertArrayNotHasKey( 'licensed', $state['addons'][0] );
	}

	/**
	 * Build a registry whose registration action registers one add-on.
	 *
	 * @param array<string, mixed> $addon Add-on description.
	 * @return Registry
	 */
	private function registry( array $addon ): Registry {
		$registry = new Registry();

		Functions\when( 'do_action' )->alias(
			static function ( $hook, $subject = null ) use ( $addon ): void {
				if ( 'cart_rebound_register_addons' === $hook && $subject instanceof Registry ) {
					$subject->register( $addon );
				}
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) {
				return 'cart_rebound_upgrade_url' === $hook
					? 'https://cart-rebound.test/upgrade'
					: $value;
			}
		);

		return $registry;
	}
}
