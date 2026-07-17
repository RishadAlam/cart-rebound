<?php
/**
 * Cart-repository unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

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
