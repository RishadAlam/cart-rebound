<?php

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use CartRebound\Events\EventDispatcher;
use CartRebound\Models\CartSession;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Support\Settings;
use CartRebound\Tests\TestCase;
use CartRebound\Tracking\CartTracker;
use CartRebound\Tracking\SessionManager;

/**
 * @covers \CartRebound\Tracking\CartTracker
 */
final class CartTrackerTest extends TestCase {

	/** @var CartTrackerWpdb */
	private $wpdb;

	protected function set_up(): void {
		parent::set_up();

		$this->wpdb      = new CartTrackerWpdb();
		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'wc_get_cart_url' )->justReturn( 'https://shop.test/cart' );
		Functions\when( 'add_query_arg' )->justReturn( 'https://shop.test/cart?recover' );
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	private function events(): EventDispatcher {
		return new EventDispatcher( new RecoveryLink() );
	}

	public function test_returning_shopper_reopens_the_cart_but_keeps_its_abandonment_stamp(): void {
		$this->wpdb->rows[] = array(
			'id'                   => 7,
			'session_key'          => 'key-7',
			'status'               => CartSession::STATUS_ABANDONED,
			'abandoned_at'         => '2026-08-01 10:00:00',
			'abandonment_notified' => 1,
		);

		Actions\expectDone( 'cart_rebound_cart_status_active' )->once()->with(
			\Mockery::on(
				static function ( $payload ): bool {
					return 7 === $payload['cart_id'] && CartSession::STATUS_ABANDONED === $payload['previous_status'];
				}
			)
		);

		$tracker = new CartTracker( new SessionManager( $this->events() ), new Settings(), $this->events() );

		$this->assertSame( 7, $tracker->upsert( 'key-7', array( 'items_count' => 2, 'cart_total' => 40.0 ) ) );

		$written = $this->wpdb->updated['data'];

		$this->assertSame( CartSession::STATUS_ACTIVE, $written['status'] );
		$this->assertSame( 0, $written['abandonment_notified'] );

		// OrderLinker reads abandoned_at to attribute a later paid order, so reopening must keep it.
		$this->assertArrayNotHasKey( 'abandoned_at', $written );
	}
}

// phpcs:disable -- lightweight custom-table test fixture.

class CartTrackerWpdb {

	public $prefix = 'wp_';

	/** @var array<int, array<string, mixed>> */
	public $rows = array();

	/** @var array{table: string, data: array<string, mixed>, where: array<string, mixed>} */
	public $updated = array(
		'table' => '',
		'data'  => array(),
		'where' => array(),
	);

	public function prepare( $query, $args = array() ) {
		return $query;
	}

	public function get_results( $query, $output ) {
		return $this->rows;
	}

	public function update( $table, $data, $where ) {
		$this->updated = array(
			'table' => $table,
			'data'  => $data,
			'where' => $where,
		);

		return 1;
	}
}
