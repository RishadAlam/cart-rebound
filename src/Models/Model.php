<?php
/**
 * Base model.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for models backed by a custom database table.
 *
 * Subclasses declare their table suffix and fillable columns; the table name is
 * automatically prefixed with the WordPress prefix and the `cart_rebound_`
 * namespace (e.g. `wp_cart_rebound_examples`).
 *
 * @since 0.1.0
 */
abstract class Model {

	/**
	 * Unprefixed table suffix (e.g. "examples").
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $table = '';

	/**
	 * Primary key column.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $primary_key = 'id';

	/**
	 * Mass-assignable columns. Empty allows all.
	 *
	 * @since 0.1.0
	 * @var array<int, string>
	 */
	protected $fillable = array();

	/**
	 * Column => cast-type map.
	 *
	 * @since 0.1.0
	 * @var array<string, string>
	 */
	protected $casts = array();

	/**
	 * Constructor.
	 *
	 * Marked final so {@see query()} can safely use `new static()`.
	 *
	 * @since 0.1.0
	 */
	final public function __construct() {
	}

	/**
	 * Get the fully-qualified table name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'cart_rebound_' . $this->table;
	}

	/**
	 * Get the primary key column name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_primary_key(): string {
		return $this->primary_key;
	}

	/**
	 * Get the fillable columns.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, string>
	 */
	public function get_fillable(): array {
		return $this->fillable;
	}

	/**
	 * Get the cast map.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string>
	 */
	public function get_casts(): array {
		return $this->casts;
	}

	/**
	 * Start a new query for this model.
	 *
	 * @since 0.1.0
	 *
	 * @return QueryBuilder
	 */
	public static function query(): QueryBuilder {
		return new QueryBuilder( new static() );
	}

	/**
	 * Find a record by primary key.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $id Primary key value.
	 * @return array<string, mixed>|null
	 */
	public static function find( $id ) {
		return static::query()->find( $id );
	}

	/**
	 * Begin a constrained query.
	 *
	 * @since 0.1.0
	 *
	 * @param string $column   Column name.
	 * @param string $operator Comparison operator.
	 * @param mixed  $value    Value to compare against.
	 * @return QueryBuilder
	 */
	public static function where( string $column, string $operator, $value ): QueryBuilder {
		return static::query()->where( $column, $operator, $value );
	}

	/**
	 * Begin a query constrained to a set of values.
	 *
	 * @since 0.1.0
	 *
	 * @param string            $column Column name.
	 * @param array<int, mixed> $values Scalar values for the set.
	 * @return QueryBuilder
	 */
	public static function where_in( string $column, array $values ): QueryBuilder {
		return static::query()->where_in( $column, $values );
	}

	/**
	 * Create a new record.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $attributes Column => value pairs.
	 * @return int|null The new row id, or null on failure.
	 */
	public static function create( array $attributes ) {
		return static::query()->create( $attributes );
	}

	/**
	 * Update a record by primary key.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string           $id         Primary key value.
	 * @param array<string, mixed> $attributes Column => value pairs.
	 * @return bool
	 */
	public static function update( $id, array $attributes ): bool {
		return static::query()->update( $id, $attributes );
	}

	/**
	 * Delete a record by primary key.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $id Primary key value.
	 * @return bool
	 */
	public static function delete( $id ): bool {
		return static::query()->delete( $id );
	}
}
