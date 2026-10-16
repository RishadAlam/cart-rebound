<?php

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use CartRebound\Cron\Scheduler;
use CartRebound\Data\CartDataCleaner;
use CartRebound\Data\CartRepository;
use CartRebound\Events\EventDispatcher;
use CartRebound\Models\CartSession;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Data\CartRepository
 */
final class CartRepositoryTest extends TestCase {

	/** @var CartRepositoryWpdb */
	private $wpdb;

	protected function set_up(): void {
		parent::set_up();

		$this->wpdb      = new CartRepositoryWpdb();
		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'wc_get_cart_url' )->justReturn( 'https://shop.test/cart' );
		Functions\when( 'add_query_arg' )->justReturn( 'https://shop.test/cart?recover' );
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_marking_an_active_cart_lost_starts_its_retention_clock(): void {
		$this->wpdb->rows[] = array(
			'id'           => 9,
			'status'       => CartSession::STATUS_ACTIVE,
			'abandoned_at' => null,
		);

		$repository = new CartRepository(
			new EventDispatcher( new RecoveryLink() ),
			new CartDataCleaner( new Scheduler() )
		);

		add_action( 'cart_rebound_cart_status_lost', '__return_null' );
		Actions\expectDone( 'cart_rebound_cart_status_lost' )->once();
		Actions\expectDone( 'cart_rebound_cart_status_changed' )->once();

		$this->assertTrue( $repository->update_status( 9, CartSession::STATUS_LOST ) );
		$this->assertSame( CartSession::STATUS_LOST, $this->wpdb->updated['data']['status'] );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$this->wpdb->updated['data']['abandoned_at']
		);
	}

	public function test_search_matches_every_word_across_contact_fields_and_ids(): void {
		Functions\when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$repository = new CartRepository(
			new EventDispatcher( new RecoveryLink() ),
			new CartDataCleaner( new Scheduler() )
		);

		$repository->get_carts( array( 'search' => '  Ann_  #42 ' ) );

		$call = $this->wpdb->prepared[0];

		$this->assertStringContainsString(
			'WHERE (%i LIKE %s OR %i LIKE %s OR %i LIKE %s OR %i LIKE %s) AND (%i LIKE %s OR %i LIKE %s OR %i LIKE %s OR %i LIKE %s OR %i = %s OR %i = %s)',
			$call['query']
		);
		$this->assertContains( '%Ann\\_%', $call['args'] );
		$this->assertContains( '%#42%', $call['args'] );
		$this->assertContains( 42, $call['args'] );
	}
}

// phpcs:disable -- lightweight custom-table test fixture.

class CartRepositoryWpdb {

	public $prefix = 'wp_';

	/** @var array<int, array<string, mixed>> */
	public $rows = array();

	/** @var array{table: string, data: array<string, mixed>, where: array<string, mixed>} */
	public $updated = array(
		'table' => '',
		'data'  => array(),
		'where' => array(),
	);

	/** @var array<int, array{query: string, args: array<int, mixed>}> */
	public $prepared = array();

	public function prepare( $query, ...$args ) {
		$this->prepared[] = array(
			'query' => $query,
			'args'  => isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : $args,
		);

		return $query;
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}

	public function get_var( $query ) {
		return 0;
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
