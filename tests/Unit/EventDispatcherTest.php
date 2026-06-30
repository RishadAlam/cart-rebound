<?php
/**
 * Event dispatcher unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use CartRebound\Events\EventDispatcher;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Events\EventDispatcher
 */
final class EventDispatcherTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'wc_get_cart_url' )->justReturn( 'https://shop.test/cart' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				unset( $args );

				return $url . '?recover';
			}
		);
	}

	private function row(): array {
		return array(
			'id'             => 7,
			'session_key'    => 'sk',
			'user_id'        => 0,
			'email'          => 'a@b.com',
			'first_name'     => 'Ann',
			'cart_total'     => 20.0,
			'currency'       => 'USD',
			'items_count'    => 2,
			'cart_contents'  => (string) json_encode(
				array(
					array(
						'product_id' => 3,
						'name'       => 'Widget',
						'quantity'   => 2,
						'line_total' => 20,
					),
				)
			),
			'recovery_token' => 'tok',
			'last_activity'  => '2026-06-30 00:00:00',
		);
	}

	public function test_abandoned_fires_event_and_legacy_alias(): void {
		Actions\expectDone( 'cart_rebound_abandoned' )->once();
		Actions\expectDone( 'cart_abandonment' )->once();

		( new EventDispatcher( new RecoveryLink() ) )->abandoned( $this->row() );

		// The two action expectations above are the assertions for this test.
		$this->addToAssertionCount( 2 );
	}

	public function test_payload_has_flat_keys(): void {
		$captured = null;

		Actions\expectDone( 'cart_rebound_abandoned' )->once()->whenHappen(
			static function ( $payload ) use ( &$captured ): void {
				$captured = $payload;
			}
		);
		Actions\expectDone( 'cart_abandonment' )->once();

		( new EventDispatcher( new RecoveryLink() ) )->abandoned( $this->row() );

		$this->assertIsArray( $captured );
		$this->assertSame( 7, $captured['cart_id'] );
		$this->assertSame( 'a@b.com', $captured['customer_email'] );
		$this->assertSame( 2, $captured['cart_items_count'] );
		$this->assertArrayHasKey( 'recovery_url', $captured );
		$this->assertIsArray( $captured['products'] );
		$this->assertSame( 'Widget', $captured['products'][0]['name'] );
	}
}
