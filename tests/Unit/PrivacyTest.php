<?php
/**
 * Privacy integration unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Cron\Scheduler;
use CartRebound\Data\CartDataCleaner;
use CartRebound\Privacy\PersonalDataEraser;
use CartRebound\Privacy\PersonalDataExporter;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Privacy\PersonalDataExporter
 * @covers \CartRebound\Privacy\PersonalDataEraser
 * @covers \CartRebound\Data\CartDataCleaner
 */
final class PrivacyTest extends TestCase {

	/** @var PrivacyWpdb */
	private $wpdb;

	/** @var array<int, array{hook: string, args: array<int, mixed>}> */
	private $cleared_jobs = array();

	protected function set_up(): void {
		parent::set_up();

		$this->wpdb      = new PrivacyWpdb();
		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'get_user_by' )->justReturn( false );
		Functions\when( 'absint' )->alias(
			static function ( $value ): int {
				return abs( (int) $value );
			}
		);
		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			function ( string $hook, array $args ): int {
				$this->cleared_jobs[] = array(
					'hook' => $hook,
					'args' => $args,
				);

				return 1;
			}
		);
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_cart_export_is_batched_and_excludes_recovery_credentials(): void {
		$rows = array();

		for ( $id = 1; $id <= 21; ++$id ) {
			$rows[] = $this->cart_row( $id );
		}

		$this->wpdb->results_queue[] = $rows;

		$result = ( new PersonalDataExporter() )->export_carts( 'shopper@example.com', 1 );

		$this->assertFalse( $result['done'] );
		$this->assertCount( 20, $result['data'] );
		$this->assertSame( 'cart-rebound-cart-1', $result['data'][0]['item_id'] );

		$names = array_column( $result['data'][0]['data'], 'name' );
		$this->assertNotContains( 'Recovery token', $names );
		$this->assertNotContains( 'Checkout URL', $names );

		$call = $this->wpdb->prepared[0];
		$this->assertSame( 'SELECT * FROM %i WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d', $call['query'] );
		$this->assertSame( 21, $call['args'][2] );
		$this->assertSame( 0, $call['args'][3] );
	}

	public function test_cart_export_matches_historical_email_by_current_user_id(): void {
		Functions\when( 'get_user_by' )->justReturn( (object) array( 'ID' => 77 ) );
		$this->wpdb->results_queue[] = array( $this->cart_row( 4 ) );

		$result = ( new PersonalDataExporter() )->export_carts( 'new-address@example.com', 1 );

		$this->assertTrue( $result['done'] );
		$this->assertSame(
			array( 'wp_cart_rebound_sessions', 'new-address@example.com', 77, 21, 0 ),
			$this->wpdb->prepared[0]['args']
		);
		$this->assertStringContainsString( 'email = %s OR user_id = %d', $this->wpdb->prepared[0]['query'] );
	}

	public function test_log_export_joins_logs_to_matching_cart_email(): void {
		$this->wpdb->results_queue[] = array(
			array(
				'id'         => 8,
				'cart_id'    => 3,
				'created_at' => '2026-07-17 10:00:00',
				'level'      => 'info',
				'event'      => 'email_sent',
				'message'    => 'Recovery email sent.',
			),
		);

		$result = ( new PersonalDataExporter() )->export_logs( 'shopper@example.com', 2 );

		$this->assertTrue( $result['done'] );
		$this->assertSame( 'cart-rebound-log-8', $result['data'][0]['item_id'] );

		$call = $this->wpdb->prepared[0];
		$this->assertStringContainsString( 'INNER JOIN %i AS sessions', $call['query'] );
		$this->assertSame(
			array(
				'wp_cart_rebound_logs',
				'wp_cart_rebound_sessions',
				'shopper@example.com',
				21,
				20,
			),
			$call['args']
		);
	}

	public function test_eraser_removes_associated_logs_before_cart_sessions(): void {
		$this->wpdb->results_queue[] = array( $this->cart_row( 7 ) );
		$this->wpdb->var_queue       = array( 2, 1, 0 );
		$this->wpdb->affected_queue  = array( 2, 1 );

		$result = ( new PersonalDataEraser( new CartDataCleaner( new Scheduler() ) ) )->erase( 'shopper@example.com', 1 );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertTrue( $result['done'] );

		$this->assertSame( 'DELETE FROM %i WHERE %i IN (%s)', $this->wpdb->prepared[2]['query'] );
		$this->assertSame( 'DELETE FROM %i WHERE %i IN (%s)', $this->wpdb->prepared[4]['query'] );
		$this->assertSame( 'wp_cart_rebound_logs', $this->wpdb->prepared[2]['args'][0] );
		$this->assertSame( 'wp_cart_rebound_sessions', $this->wpdb->prepared[4]['args'][0] );
		$this->assertSame(
			array( array( 'hook' => 'cart_rebound_send_recovery_email', 'args' => array( 7 ) ) ),
			$this->cleared_jobs
		);
	}

	public function test_eraser_retains_sessions_when_log_deletion_fails(): void {
		$this->wpdb->results_queue[] = array( $this->cart_row( 7 ) );
		$this->wpdb->var_queue       = array( 2 );
		$this->wpdb->affected_queue  = array( 1 );

		$result = ( new PersonalDataEraser( new CartDataCleaner( new Scheduler() ) ) )->erase( 'shopper@example.com', 1 );

		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$this->assertCount( 1, $result['messages'] );
		$this->assertCount( 3, $this->wpdb->prepared );
	}

	public function test_eraser_matches_historical_email_by_current_user_id(): void {
		Functions\when( 'get_user_by' )->justReturn( (object) array( 'ID' => 77 ) );
		$this->wpdb->results_queue[] = array( $this->cart_row( 7 ) );
		$this->wpdb->var_queue       = array( 0, 1, 0 );
		$this->wpdb->affected_queue  = array( 0, 1 );

		$result = ( new PersonalDataEraser( new CartDataCleaner( new Scheduler() ) ) )->erase( 'new-address@example.com', 1 );

		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame(
			array( 'wp_cart_rebound_sessions', 'new-address@example.com', 77, 20 ),
			$this->wpdb->prepared[0]['args']
		);
		$this->assertStringContainsString( 'email = %s OR user_id = %d', $this->wpdb->prepared[0]['query'] );
	}

	/**
	 * Build a representative stored cart row.
	 *
	 * @param int $id Cart id.
	 * @return array<string, mixed>
	 */
	private function cart_row( int $id ): array {
		return array(
			'id'                   => $id,
			'session_key'          => 'session-' . $id,
			'user_id'              => 10,
			'email'                => 'shopper@example.com',
			'first_name'           => 'Shopper',
			'last_name'            => 'Example',
			'phone'                => '+123456',
			'cart_contents'        => '[{"product_id":1}]',
			'cart_total'           => '29.00',
			'currency'             => 'USD',
			'items_count'          => 1,
			'coupons'              => '[]',
			'status'               => 'abandoned',
			'recovery_token'       => 'secret-token',
			'checkout_url'         => 'https://example.com/checkout/',
			'order_id'             => 0,
			'recovered_amount'     => '0',
			'created_at'           => '2026-07-17 09:00:00',
			'last_activity'        => '2026-07-17 09:30:00',
			'abandoned_at'         => '2026-07-17 10:00:00',
			'recovered_at'         => null,
			'completed_at'         => null,
		);
	}
}

// phpcs:disable -- lightweight custom-table test fixture.

class PrivacyWpdb {

	public $prefix = 'wp_';

	/** @var array<int, array{query: string, args: array<int, mixed>}> */
	public $prepared = array();

	/** @var array<int, array<int, array<string, mixed>>> */
	public $results_queue = array();

	/** @var array<int, int> */
	public $var_queue = array();

	/** @var array<int, int> */
	public $affected_queue = array();

	public function prepare( $query, $args = array() ) {
		$this->prepared[] = array(
			'query' => $query,
			'args'  => is_array( $args ) ? $args : array( $args ),
		);

		return $query;
	}

	public function get_results( $query, $output ) {
		return array_shift( $this->results_queue ) ?? array();
	}

	public function get_var( $query ) {
		return array_shift( $this->var_queue ) ?? 0;
	}

	public function query( $query ) {
		return array_shift( $this->affected_queue ) ?? 0;
	}
}
