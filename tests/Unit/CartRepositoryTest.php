<?php
/**
 * Cart-repository unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

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
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	private function repository(): CartRepository {
		return new CartRepository(
			new EventDispatcher( new RecoveryLink() ),
			new CartDataCleaner( new Scheduler() )
		);
	}

	public function test_the_product_report_ranks_by_the_money_still_on_the_table(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$cheap = wp_json_encode_fixture(
			array(
				array( 'product_id' => 1, 'name' => 'Sticker', 'quantity' => 1, 'line_total' => 4.0 ),
			)
		);
		$dear  = wp_json_encode_fixture(
			array(
				array( 'product_id' => 2, 'name' => 'Winter Coat', 'quantity' => 1, 'line_total' => 400.0 ),
			)
		);

		$recent = gmdate( 'Y-m-d H:i:s' );

		// The sticker is abandoned five times over, the coat twice. Counting
		// occurrences would rank the sticker first and send the merchant after
		// four pounds instead of eight hundred.
		$this->wpdb->rows = array(
			array( 'cart_contents' => $cheap, 'abandoned_at' => $recent, 'recovered_at' => '' ),
			array( 'cart_contents' => $cheap, 'abandoned_at' => $recent, 'recovered_at' => '' ),
			array( 'cart_contents' => $cheap, 'abandoned_at' => $recent, 'recovered_at' => '' ),
			array( 'cart_contents' => $cheap, 'abandoned_at' => $recent, 'recovered_at' => '' ),
			array( 'cart_contents' => $cheap, 'abandoned_at' => $recent, 'recovered_at' => '' ),
			array( 'cart_contents' => $dear, 'abandoned_at' => $recent, 'recovered_at' => '' ),
			array( 'cart_contents' => $dear, 'abandoned_at' => $recent, 'recovered_at' => '' ),
		);

		$report = $this->repository()->get_product_report( 30, 10 );

		$this->assertSame( 'Winter Coat', $report[0]['name'] );
		$this->assertSame( 800.0, $report[0]['lost_value'] );
		$this->assertSame( 'Sticker', $report[1]['name'] );
		$this->assertSame( 20.0, $report[1]['lost_value'] );
	}

	public function test_a_recovered_cart_costs_nothing(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$contents = wp_json_encode_fixture(
			array(
				array( 'product_id' => 2, 'name' => 'Winter Coat', 'quantity' => 1, 'line_total' => 400.0 ),
			)
		);

		$recent = gmdate( 'Y-m-d H:i:s' );

		$this->wpdb->rows = array(
			array( 'cart_contents' => $contents, 'abandoned_at' => $recent, 'recovered_at' => $recent ),
		);

		$report = $this->repository()->get_product_report( 30, 10 );

		// It was abandoned and then won back, so nothing was lost with it.
		$this->assertSame( 1, $report[0]['abandoned'] );
		$this->assertSame( 1, $report[0]['recovered'] );
		$this->assertSame( 0.0, $report[0]['lost_value'] );
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

		$this->assertTrue( $repository->update_status( 9, CartSession::STATUS_LOST ) );
		$this->assertSame( CartSession::STATUS_LOST, $this->wpdb->updated['data']['status'] );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$this->wpdb->updated['data']['abandoned_at']
		);
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

// phpcs:disable -- test helper.
function wp_json_encode_fixture( array $value ): string {
	return (string) json_encode( $value );
}
