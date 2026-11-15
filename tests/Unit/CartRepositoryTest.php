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

	public function test_timeseries_counts_only_carts_that_are_still_recovered(): void {
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'UTC' ) );

		$this->repository()->get_timeseries( 7 );

		$this->assertCount( 2, $this->wpdb->prepared );
		$this->assertStringContainsString( "(%s = '' OR status = %s)", $this->wpdb->prepared[1]['query'] );
		$this->assertSame( '', end( $this->wpdb->prepared[0]['args'] ) );
		$this->assertSame( CartSession::STATUS_RECOVERED, end( $this->wpdb->prepared[1]['args'] ) );
	}

	public function test_timeseries_days_follow_the_store_time_zone(): void {
		$zone = new \DateTimeZone( 'Asia/Dhaka' );
		Functions\when( 'wp_timezone' )->justReturn( $zone );

		$series = $this->repository()->get_timeseries( 7 );
		$today  = new \DateTimeImmutable( 'today', $zone );
		$args   = $this->wpdb->prepared[0]['args'];

		$this->assertCount( 7, $series );
		$this->assertSame( $today->format( 'Y-m-d' ), $series[6]['date'] );
		$this->assertSame( 21600, $args[1] );
		$this->assertSame(
			$today->modify( '-6 days' )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			$args[5]
		);
	}

	public function test_product_report_does_not_count_a_reversed_recovery_as_won_back(): void {
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'UTC' ) );

		$recent   = gmdate( 'Y-m-d H:i:s', time() - 3600 );
		$contents = (string) json_encode( array( array( 'product_id' => 5, 'name' => 'Mug' ) ) );

		$this->wpdb->rows = array(
			array(
				'cart_contents' => $contents,
				'status'        => CartSession::STATUS_LOST,
				'abandoned_at'  => $recent,
				'recovered_at'  => $recent,
			),
			array(
				'cart_contents' => $contents,
				'status'        => CartSession::STATUS_RECOVERED,
				'abandoned_at'  => $recent,
				'recovered_at'  => $recent,
			),
		);

		$report = $this->repository()->get_product_report( 7, 5 );

		$this->assertSame( 2, $report[0]['abandoned'] );
		$this->assertSame( 1, $report[0]['recovered'] );
	}

	private function repository(): CartRepository {
		return new CartRepository(
			new EventDispatcher( new RecoveryLink() ),
			new CartDataCleaner( new Scheduler() )
		);
	}

	public function test_a_zero_search_does_not_match_carts_without_an_order(): void {
		Functions\when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$repository = new CartRepository(
			new EventDispatcher( new RecoveryLink() ),
			new CartDataCleaner( new Scheduler() )
		);

		$repository->get_carts( array( 'search' => '#0' ) );

		$query = $this->wpdb->prepared[0]['query'];

		$this->assertStringContainsString( '(%i LIKE %s OR %i LIKE %s OR %i LIKE %s OR %i LIKE %s)', $query );
		$this->assertStringNotContainsString( '%i = %s', $query );
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
