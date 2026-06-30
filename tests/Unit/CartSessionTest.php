<?php
/**
 * CartSession model unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use CartRebound\Models\CartSession;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Models\CartSession
 */
final class CartSessionTest extends TestCase {

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
		$this->assertSame( 'wp_cart_rebound_sessions', ( new CartSession() )->get_table() );
	}

	public function test_status_constants(): void {
		$this->assertSame( 'active', CartSession::STATUS_ACTIVE );
		$this->assertSame( 'abandoned', CartSession::STATUS_ABANDONED );
		$this->assertSame( 'recovered', CartSession::STATUS_RECOVERED );
		$this->assertSame( 'completed', CartSession::STATUS_COMPLETED );
		$this->assertSame( 'lost', CartSession::STATUS_LOST );
	}

	public function test_fillable_covers_core_columns(): void {
		$fillable = ( new CartSession() )->get_fillable();

		foreach ( array( 'session_key', 'email', 'status', 'recovery_token', 'order_id', 'recovered_amount' ) as $column ) {
			$this->assertContains( $column, $fillable );
		}
	}
}
