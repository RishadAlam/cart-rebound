<?php
/**
 * Abandonment detector unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Cron\AbandonmentDetector;
use CartRebound\Cron\Scheduler;
use CartRebound\Events\EventDispatcher;
use CartRebound\Followup\Runner;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Mail\TemplateStore;
use CartRebound\Models\CartSession;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Support\Settings;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Cron\AbandonmentDetector
 */
final class AbandonmentDetectorTest extends TestCase {

	/** @var DetectorWpdb */
	private $wpdb;

	/** @var array<int, string> */
	private $abandoned = array();

	protected function set_up(): void {
		parent::set_up();

		$this->wpdb      = new DetectorWpdb();
		$GLOBALS['wpdb'] = $this->wpdb;
		$this->abandoned = array();

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_option' )->alias(
			static function ( $key ) {
				return Settings::OPTION === $key
					? array(
						'abandonment_threshold'  => 30,
						'recovery_email_enabled' => false,
					)
					: null;
			}
		);
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'wc_get_cart_url' )->justReturn( 'https://shop.test/cart' );
		Functions\when( 'add_query_arg' )->justReturn( 'https://shop.test/cart?recover' );
		Functions\when( 'home_url' )->justReturn( 'https://shop.test/' );
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_an_excluded_cart_stays_active_and_is_never_written(): void {
		$this->wpdb->pages = array( array( $this->row( 1 ) ), array() );

		$this->allow_abandonment( false );

		$this->detector()->run();

		$this->assertSame( 0, $this->wpdb->updates, 'An excluded cart must not be flipped.' );
	}

	public function test_the_scan_pages_past_carts_an_extension_excluded(): void {
		// A full first page of excluded carts. Without an offset the second read
		// would return the very same rows and the scan would never reach the
		// carts queued behind them.
		$this->wpdb->pages = array(
			$this->rows( 1, 50 ),
			$this->rows( 51, 50 ),
			array(),
		);

		$this->allow_abandonment( false );

		$this->detector()->run();

		$this->assertSame( array( 0, 50, 100 ), $this->wpdb->offsets );
	}

	public function test_a_cart_that_vanished_between_the_read_and_the_write_does_not_move_the_offset(): void {
		// The compare-and-set matches nothing: the shopper came back, so the row
		// no longer satisfies the candidate query either and has already left the
		// result set. Stepping over it here would skip an eligible cart instead.
		$this->wpdb->rows_updated = 0;
		$this->wpdb->pages        = array( $this->rows( 1, 50 ), array() );

		$this->allow_abandonment( true );

		$this->detector()->run();

		$this->assertSame( array( 0, 0 ), $this->wpdb->offsets );
	}

	public function test_an_abandoned_cart_does_not_move_the_offset_either(): void {
		$this->wpdb->pages = array( $this->rows( 1, 50 ), array() );

		$this->allow_abandonment( true );

		$this->detector()->run();

		$this->assertSame( array( 0, 0 ), $this->wpdb->offsets );
		$this->assertSame( 50, $this->wpdb->updates );
	}

	public function test_the_scan_stops_at_the_backlog_ceiling(): void {
		$this->wpdb->pages = array_fill( 0, 20, $this->rows( 1, 50 ) );

		$this->allow_abandonment( true );

		$this->detector()->run();

		$this->assertSame( 500, $this->wpdb->updates );
	}

	/**
	 * Make the abandonment filter answer with a fixed decision.
	 *
	 * @param bool $allow Whether carts may be abandoned.
	 * @return void
	 */
	private function allow_abandonment( bool $allow ): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) use ( $allow ) {
				return 'cart_rebound_should_abandon' === $hook ? $allow : $value;
			}
		);
	}

	/**
	 * Build a page of candidate rows.
	 *
	 * @param int $first First id.
	 * @param int $count How many.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows( int $first, int $count ): array {
		$rows = array();

		for ( $index = 0; $index < $count; $index++ ) {
			$rows[] = $this->row( $first + $index );
		}

		return $rows;
	}

	/**
	 * Build one candidate row.
	 *
	 * @param int $id Row id.
	 * @return array<string, mixed>
	 */
	private function row( int $id ): array {
		return array(
			'id'            => $id,
			'email'         => 'shopper@example.com',
			'status'        => CartSession::STATUS_ACTIVE,
			'items_count'   => 1,
			'cart_total'    => 42.0,
			'last_activity' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
		);
	}

	private function detector(): AbandonmentDetector {
		$settings = new Settings();
		$links    = new RecoveryLink();
		$mailer   = new RecoveryMailer( $settings, $links, new TemplateStore( $settings ) );

		return new AbandonmentDetector(
			$settings,
			new EventDispatcher( $links ),
			new Runner( $settings, new Scheduler(), $mailer )
		);
	}
}

// phpcs:disable -- Lightweight database fixture recording paging behaviour.
class DetectorWpdb {

	/** @var string */
	public $prefix = 'wp_';

	/** @var array<int, array<int, array<string, mixed>>> */
	public $pages = array();

	/** @var array<int, int> */
	public $offsets = array();

	/** @var int */
	public $rows_updated = 1;

	/** @var int */
	public $updates = 0;

	/** @var int */
	private $page = 0;

	public function prepare( $query, $args = array() ) {
		$flat = is_array( $args ) ? $args : array( $args );

		return (string) preg_replace_callback(
			'/%[isdf]/',
			static function () use ( &$flat ) {
				return (string) array_shift( $flat );
			},
			(string) $query
		);
	}

	public function get_results( $query, $output = null ) {
		unset( $output );

		if ( is_string( $query ) && false !== strpos( $query, 'cart_rebound_unsubscribes' ) ) {
			return array();
		}

		// Record the OFFSET the scan asked for, defaulting to 0 when the builder
		// omitted the clause entirely.
		$offset = 0;

		if ( is_string( $query ) && 1 === preg_match( '/OFFSET (\d+)/', $query, $matches ) ) {
			$offset = (int) $matches[1];
		}

		$this->offsets[] = $offset;

		$page = $this->pages[ $this->page ] ?? array();
		++$this->page;

		return $page;
	}

	public function query( $query ) {
		if ( is_string( $query ) && 0 === strpos( ltrim( $query ), 'UPDATE' ) ) {
			$this->updates += $this->rows_updated;

			return $this->rows_updated;
		}

		return 0;
	}

	public function update( $table, $data, $where ) {
		unset( $table, $data, $where );

		++$this->updates;

		return 1;
	}
}
