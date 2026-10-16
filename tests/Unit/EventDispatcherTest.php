<?php

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
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );
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

	public function test_abandoned_fires_event(): void {
		Actions\expectDone( 'cart_rebound_abandoned' )->once();

		( new EventDispatcher( new RecoveryLink() ) )->abandoned( $this->row() );

		$this->addToAssertionCount( 1 );
	}

	public function test_payload_has_flat_keys(): void {
		$captured = null;

		Actions\expectDone( 'cart_rebound_abandoned' )->once()->whenHappen(
			static function ( $payload ) use ( &$captured ): void {
				$captured = $payload;
			}
		);

		( new EventDispatcher( new RecoveryLink() ) )->abandoned( $this->row() );

		$this->assertIsArray( $captured );
		$this->assertSame( 7, $captured['cart_id'] );
		$this->assertSame( 'a@b.com', $captured['customer_email'] );
		$this->assertSame( 2, $captured['cart_items_count'] );
		$this->assertArrayHasKey( 'recovery_url', $captured );
		$this->assertIsArray( $captured['products'] );
		$this->assertSame( 'Widget', $captured['products'][0]['name'] );
	}

	private function dispatcher(): EventDispatcher {
		return new EventDispatcher( new RecoveryLink() );
	}

	public function test_status_change_fires_the_specific_then_the_global_hook(): void {
		$order = array();

		Actions\expectDone( 'cart_rebound_cart_status_abandoned' )->once()->whenHappen(
			static function ( $payload ) use ( &$order ): void {
				$order[] = 'specific:' . $payload['previous_status'];
			}
		);
		Actions\expectDone( 'cart_rebound_cart_status_changed' )->once()->whenHappen(
			static function ( $payload ) use ( &$order ): void {
				$order[] = 'global:' . $payload['status'];
			}
		);

		$this->dispatcher()->status_changed(
			array_merge( $this->row(), array( 'status' => 'abandoned' ) ),
			'active'
		);

		$this->assertSame( array( 'specific:active', 'global:abandoned' ), $order );
	}

	public function test_status_change_is_silent_when_the_status_did_not_move(): void {
		Actions\expectDone( 'cart_rebound_cart_status_changed' )->never();

		$this->dispatcher()->status_changed(
			array_merge( $this->row(), array( 'status' => 'active' ) ),
			'active'
		);

		$this->addToAssertionCount( 1 );
	}

	public function test_captured_needs_an_email(): void {
		Actions\expectDone( 'cart_rebound_cart_captured' )->never();

		$this->dispatcher()->captured( array_merge( $this->row(), array( 'email' => '' ) ) );

		$this->addToAssertionCount( 1 );
	}

	public function test_email_sent_without_a_coupon_skips_the_coupon_hook(): void {
		Actions\expectDone( 'cart_rebound_recovery_email_sent' )->once();
		Actions\expectDone( 'cart_rebound_recovery_coupon_sent' )->never();

		$this->dispatcher()->email_sent( $this->row(), array( 'id' => 't1' ), 'automatic' );

		$this->addToAssertionCount( 1 );
	}

	public function test_email_sent_with_a_coupon_reports_the_coupon(): void {
		Functions\when( 'wc_get_coupon_id_by_code' )->justReturn( 42 );

		$coupon = null;

		Actions\expectDone( 'cart_rebound_recovery_email_sent' )->once();
		Actions\expectDone( 'cart_rebound_recovery_coupon_sent' )->once()->whenHappen(
			static function ( $payload ) use ( &$coupon ): void {
				$coupon = $payload;
			}
		);

		$this->dispatcher()->email_sent(
			$this->row(),
			array(
				'id'     => 't1',
				'name'   => 'Default',
				'coupon' => 'SAVE10',
			),
			'manual'
		);

		$this->assertIsArray( $coupon );
		$this->assertSame( 'SAVE10', $coupon['coupon_code'] );
		$this->assertSame( 42, $coupon['coupon_id'] );
		$this->assertSame( 'manual', $coupon['trigger'] );
		$this->assertSame( 't1', $coupon['template_id'] );
	}

	public function test_unsubscribed_carries_the_shopper(): void {
		$payload = null;

		Actions\expectDone( 'cart_rebound_unsubscribed' )->once()->whenHappen(
			static function ( $data ) use ( &$payload ): void {
				$payload = $data;
			}
		);

		$this->dispatcher()->unsubscribed( $this->row() );

		$this->assertIsArray( $payload );
		$this->assertSame( 'a@b.com', $payload['customer_email'] );
		$this->assertArrayHasKey( 'unsubscribed_at', $payload );
	}
}
