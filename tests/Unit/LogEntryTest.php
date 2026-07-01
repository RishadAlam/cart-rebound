<?php
/**
 * LogEntry model unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use CartRebound\Models\LogEntry;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Models\LogEntry
 */
final class LogEntryTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();
		$GLOBALS['wpdb'] = new class() {
			public $prefix = 'wp_';
		};
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_table_name_is_prefixed(): void {
		$this->assertSame( 'wp_cart_rebound_logs', ( new LogEntry() )->get_table() );
	}

	public function test_level_vocabulary(): void {
		$this->assertSame(
			array( 'info', 'success', 'warning', 'error' ),
			LogEntry::LEVELS
		);
	}

	public function test_fillable_covers_columns(): void {
		$fillable = ( new LogEntry() )->get_fillable();

		foreach ( array( 'created_at', 'level', 'event', 'message', 'cart_id' ) as $column ) {
			$this->assertContains( $column, $fillable );
		}
	}
}
