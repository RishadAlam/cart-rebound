<?php
/**
 * Query builder unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use CartRebound\Models\Model;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Models\QueryBuilder
 * @covers \CartRebound\Models\Model
 */
final class QueryBuilderTest extends TestCase {

	/** @var FakeWpdb */
	private $wpdb;

	protected function set_up(): void {
		parent::set_up();
		$this->wpdb            = new FakeWpdb();
		$GLOBALS['wpdb']       = $this->wpdb;
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_table_name_is_prefixed(): void {
		$this->assertSame( 'wp_cart_rebound_examples', ( new ExampleModel() )->get_table() );
	}

	public function test_select_with_where_limit_builds_prepared_sql(): void {
		ExampleModel::where( 'title', '=', 'hello' )->limit( 5 )->get();

		$call = $this->wpdb->prepared[0];

		$this->assertSame( 'SELECT * FROM %i WHERE %i = %s LIMIT %d', $call['query'] );
		$this->assertSame(
			array( 'wp_cart_rebound_examples', 'title', 'hello', 5 ),
			$call['args']
		);
	}

	public function test_find_builds_primary_key_lookup(): void {
		ExampleModel::find( 7 );

		$call = $this->wpdb->prepared[0];

		$this->assertSame( 'SELECT * FROM %i WHERE %i = %s LIMIT %d', $call['query'] );
		$this->assertSame( array( 'wp_cart_rebound_examples', 'id', 7, 1 ), $call['args'] );
	}

	public function test_order_by_descending_uses_literal_direction(): void {
		ExampleModel::query()->order_by( 'created_at', 'desc' )->get();

		$this->assertSame( 'SELECT * FROM %i ORDER BY %i DESC', $this->wpdb->prepared[0]['query'] );
	}

	public function test_create_only_persists_fillable_columns(): void {
		$id = ExampleModel::create(
			array(
				'title'   => 'Hi',
				'ignored' => 'nope',
			)
		);

		$this->assertSame( 42, $id );
		$this->assertSame( 'wp_cart_rebound_examples', $this->wpdb->inserted['table'] );
		$this->assertSame( array( 'title' => 'Hi' ), $this->wpdb->inserted['data'] );
	}

	public function test_where_in_builds_placeholder_list(): void {
		ExampleModel::where_in( 'id', array( 1, 2, 3 ) )->get();

		$call = $this->wpdb->prepared[0];

		$this->assertSame( 'SELECT * FROM %i WHERE %i IN (%s, %s, %s)', $call['query'] );
		$this->assertSame( array( 'wp_cart_rebound_examples', 'id', 1, 2, 3 ), $call['args'] );
	}

	public function test_empty_where_in_matches_nothing(): void {
		ExampleModel::where_in( 'id', array() )->get();

		$this->assertSame( 'SELECT * FROM %i WHERE 1 = 0', $this->wpdb->prepared[0]['query'] );
		$this->assertSame( array( 'wp_cart_rebound_examples' ), $this->wpdb->prepared[0]['args'] );
	}

	public function test_not_equal_alias_maps_to_not_equal_fragment(): void {
		ExampleModel::where( 'status', '<>', 'banned' )->get();

		$this->assertSame( 'SELECT * FROM %i WHERE %i != %s', $this->wpdb->prepared[0]['query'] );
	}

	public function test_unknown_operator_throws(): void {
		$this->expectException( \InvalidArgumentException::class );

		ExampleModel::where( 'id', 'DROP', 1 );
	}

	public function test_non_scalar_where_value_throws(): void {
		$this->expectException( \InvalidArgumentException::class );

		ExampleModel::where( 'id', '=', array( 1, 2 ) );
	}

	public function test_count_builds_aggregate_sql(): void {
		$this->wpdb->var = 5;

		$count = ExampleModel::query()->where( 'status', '=', 'active' )->count();

		$this->assertSame( 5, $count );
		$call = $this->wpdb->prepared[0];
		$this->assertSame( 'SELECT COUNT(*) FROM %i WHERE %i = %s', $call['query'] );
		$this->assertSame( array( 'wp_cart_rebound_examples', 'status', 'active' ), $call['args'] );
	}

	public function test_sum_builds_aggregate_sql(): void {
		$this->wpdb->var = '12.50';

		$sum = ExampleModel::query()->where( 'status', '=', 'recovered' )->sum( 'amount' );

		$this->assertSame( 12.5, $sum );
		$call = $this->wpdb->prepared[0];
		$this->assertSame( 'SELECT SUM(%i) FROM %i WHERE %i = %s', $call['query'] );
		$this->assertSame( array( 'amount', 'wp_cart_rebound_examples', 'status', 'recovered' ), $call['args'] );
	}

	public function test_offset_applies_with_limit(): void {
		ExampleModel::query()->limit( 10 )->offset( 20 )->get();

		$call = $this->wpdb->prepared[0];
		$this->assertSame( 'SELECT * FROM %i LIMIT %d OFFSET %d', $call['query'] );
		$this->assertSame( array( 'wp_cart_rebound_examples', 10, 20 ), $call['args'] );
	}

	public function test_delete_where_deletes_matching_rows(): void {
		$this->wpdb->affected = 4;

		$deleted = ExampleModel::query()->where( 'status', '=', 'lost' )->delete_where();

		$this->assertSame( 4, $deleted );
		$this->assertSame( 'DELETE FROM %i WHERE %i = %s', $this->wpdb->prepared[0]['query'] );
	}

	public function test_delete_where_refuses_without_conditions(): void {
		$deleted = ExampleModel::query()->delete_where();

		$this->assertSame( 0, $deleted );
		$this->assertSame( array(), $this->wpdb->prepared );
	}

	public function test_update_where_updates_matching_rows(): void {
		$this->wpdb->affected = 3;

		$updated = ExampleModel::query()
			->where( 'status', '=', 'active' )
			->update_where( array( 'title' => 'done' ) );

		$this->assertSame( 3, $updated );

		$call = $this->wpdb->prepared[0];
		$this->assertSame( 'UPDATE %i SET %i = %s WHERE %i = %s', $call['query'] );
		$this->assertSame(
			array( 'wp_cart_rebound_examples', 'title', 'done', 'status', 'active' ),
			$call['args']
		);
	}

	public function test_update_where_refuses_without_conditions(): void {
		$updated = ExampleModel::query()->update_where( array( 'title' => 'x' ) );

		$this->assertSame( 0, $updated );
		$this->assertSame( array(), $this->wpdb->prepared );
	}

	public function test_update_where_ignores_non_fillable_columns(): void {
		$updated = ExampleModel::query()
			->where( 'id', '=', 1 )
			->update_where( array( 'ignored' => 'nope' ) );

		$this->assertSame( 0, $updated );
		$this->assertSame( array(), $this->wpdb->prepared );
	}
}

// phpcs:disable -- lightweight test fixtures.

class ExampleModel extends Model {

	protected $table = 'examples';

	protected $fillable = array( 'title' );
}

class FakeWpdb {

	public $prefix = 'wp_';

	public $insert_id = 0;

	public $var = 0;

	public $affected = 0;

	/** @var array<int, array{query: string, args: array<int, mixed>}> */
	public $prepared = array();

	/** @var array<int, array<string, mixed>> */
	public $results = array();

	/** @var array{table: string, data: array<string, mixed>} */
	public $inserted = array();

	public function prepare( $query, $args = array() ) {
		$this->prepared[] = array(
			'query' => $query,
			'args'  => is_array( $args ) ? $args : array( $args ),
		);

		return $query;
	}

	public function get_results( $query, $output ) {
		return $this->results;
	}

	public function insert( $table, $data ) {
		$this->inserted  = array(
			'table' => $table,
			'data'  => $data,
		);
		$this->insert_id = 42;

		return 1;
	}

	public function update( $table, $data, $where ) {
		return 1;
	}

	public function delete( $table, $where ) {
		return 1;
	}

	public function get_var( $query ) {
		return $this->var;
	}

	public function query( $query ) {
		return $this->affected;
	}

	public function get_charset_collate() {
		return '';
	}
}
