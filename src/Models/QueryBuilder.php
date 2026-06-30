<?php
/**
 * Fluent query builder over $wpdb.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Models;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;

/**
 * A minimal, safe query builder.
 *
 * Every query is assembled from literal fragments plus `%i` / `%s` / `%d`
 * placeholders and run through $wpdb->prepare(); no caller value is ever
 * concatenated into SQL.
 *
 * @since 0.1.0
 */
final class QueryBuilder {

	/**
	 * Allowed comparison operators mapped to their literal prepared fragment.
	 *
	 * This single map is the only source of truth for which operators are
	 * supported (validated in {@see where()}) and how each compiles.
	 *
	 * @since 0.1.0
	 * @var array<string, string>
	 */
	private const FRAGMENTS = array(
		'='    => '%i = %s',
		'!='   => '%i != %s',
		'<>'   => '%i != %s',
		'>'    => '%i > %s',
		'<'    => '%i < %s',
		'>='   => '%i >= %s',
		'<='   => '%i <= %s',
		'LIKE' => '%i LIKE %s',
	);

	/**
	 * The model this builder targets.
	 *
	 * @since 0.1.0
	 * @var Model
	 */
	private $model;

	/**
	 * WHERE conditions.
	 *
	 * @since 0.1.0
	 * @var array<int, array<string, mixed>>
	 */
	private $wheres = array();

	/**
	 * Result limit (0 = no limit).
	 *
	 * @since 0.1.0
	 * @var int
	 */
	private $limit = 0;

	/**
	 * Result offset (0 = none; only applied alongside a positive limit).
	 *
	 * @since 0.1.0
	 * @var int
	 */
	private $offset = 0;

	/**
	 * ORDER BY column.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $order_column = '';

	/**
	 * ORDER BY direction.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $order_direction = 'ASC';

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Model $model The model to query.
	 */
	public function __construct( Model $model ) {
		$this->model = $model;
	}

	/**
	 * Add a WHERE condition.
	 *
	 * @since 0.1.0
	 *
	 * @param string $column   Column name.
	 * @param string $operator Comparison operator (one of {@see OPERATORS}).
	 * @param mixed  $value    Scalar (or null) value to compare against.
	 * @return QueryBuilder
	 *
	 * @throws InvalidArgumentException When the operator is unsupported or the value is not scalar.
	 */
	public function where( string $column, string $operator, $value ): QueryBuilder {
		$normalized = strtoupper( $operator );

		if ( ! isset( self::FRAGMENTS[ $normalized ] ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'Unsupported WHERE operator [%s].', $operator ) )
			);
		}

		if ( null !== $value && ! is_scalar( $value ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'WHERE value for column [%s] must be scalar; use where_in() for value sets.', $column ) )
			);
		}

		$this->wheres[] = array(
			'type'     => 'basic',
			'column'   => $column,
			'operator' => $normalized,
			'value'    => $value,
		);

		return $this;
	}

	/**
	 * Add a `WHERE column IN (...)` condition.
	 *
	 * An empty value set produces a clause that matches nothing (a safe `1 = 0`),
	 * never an invalid empty `IN ()`.
	 *
	 * @since 0.1.0
	 *
	 * @param string            $column Column name.
	 * @param array<int, mixed> $values Scalar values for the set.
	 * @return QueryBuilder
	 *
	 * @throws InvalidArgumentException When any value is not scalar.
	 */
	public function where_in( string $column, array $values ): QueryBuilder {
		foreach ( $values as $value ) {
			if ( null !== $value && ! is_scalar( $value ) ) {
				throw new InvalidArgumentException(
					esc_html( sprintf( 'where_in() values for column [%s] must be scalar.', $column ) )
				);
			}
		}

		$this->wheres[] = array(
			'type'   => 'in',
			'column' => $column,
			'values' => array_values( $values ),
		);

		return $this;
	}

	/**
	 * Limit the number of results.
	 *
	 * @since 0.1.0
	 *
	 * @param int $limit Maximum rows to return.
	 * @return QueryBuilder
	 */
	public function limit( int $limit ): QueryBuilder {
		$this->limit = max( 0, $limit );

		return $this;
	}

	/**
	 * Order the results.
	 *
	 * @since 0.1.0
	 *
	 * @param string $column    Column to order by.
	 * @param string $direction ASC or DESC.
	 * @return QueryBuilder
	 */
	public function order_by( string $column, string $direction = 'ASC' ): QueryBuilder {
		$this->order_column    = $column;
		$this->order_direction = 'DESC' === strtoupper( $direction ) ? 'DESC' : 'ASC';

		return $this;
	}

	/**
	 * Execute the query and return all matching rows.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get(): array {
		global $wpdb;

		$bindings = array( $this->model->get_table() );
		$sql      = 'SELECT * FROM %i';
		$sql     .= $this->compile_wheres( $bindings );
		$sql     .= $this->compile_order( $bindings );

		if ( $this->limit > 0 ) {
			$sql       .= ' LIMIT %d';
			$bindings[] = $this->limit;

			if ( $this->offset > 0 ) {
				$sql       .= ' OFFSET %d';
				$bindings[] = $this->offset;
			}
		}

		/*
		 * $sql is assembled only from literal fragments and %i/%s/%d
		 * placeholders; every caller value is bound through $wpdb->prepare(),
		 * so this dynamic-but-prepared query is injection-safe. Result caching
		 * is the caller's concern for a generic builder over a custom table.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $bindings ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Execute the query and return the first matching row.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function first() {
		$this->limit( 1 );

		$rows = $this->get();

		return $rows[0] ?? null;
	}

	/**
	 * Find a row by primary key.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $id Primary key value.
	 * @return array<string, mixed>|null
	 */
	public function find( $id ) {
		return $this->where( $this->model->get_primary_key(), '=', $id )->first();
	}

	/**
	 * Insert a new row.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $attributes Column => value pairs.
	 * @return int|null The new row id, or null on failure.
	 */
	public function create( array $attributes ) {
		global $wpdb;

		$data = $this->only_fillable( $attributes );

		if ( array() === $data ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom-table write via $wpdb->insert(); values are escaped by core.
		$inserted = $wpdb->insert( $this->model->get_table(), $data );

		return false === $inserted ? null : (int) $wpdb->insert_id;
	}

	/**
	 * Update a row by primary key.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string           $id         Primary key value.
	 * @param array<string, mixed> $attributes Column => value pairs.
	 * @return bool
	 */
	public function update( $id, array $attributes ): bool {
		global $wpdb;

		$data = $this->only_fillable( $attributes );

		if ( array() === $data ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom-table write via $wpdb->update(); values are escaped by core.
		$updated = $wpdb->update(
			$this->model->get_table(),
			$data,
			array( $this->model->get_primary_key() => $id )
		);

		return false !== $updated;
	}

	/**
	 * Delete a row by primary key.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $id Primary key value.
	 * @return bool
	 */
	public function delete( $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom-table delete via $wpdb->delete(); value is escaped by core.
		$deleted = $wpdb->delete(
			$this->model->get_table(),
			array( $this->model->get_primary_key() => $id )
		);

		return false !== $deleted;
	}

	/**
	 * Set the result offset.
	 *
	 * Only takes effect together with a positive {@see limit()} (SQL OFFSET
	 * requires a LIMIT). Intended for page-based admin listings.
	 *
	 * @since 0.1.0
	 *
	 * @param int $offset Number of leading rows to skip.
	 * @return QueryBuilder
	 */
	public function offset( int $offset ): QueryBuilder {
		$this->offset = max( 0, $offset );

		return $this;
	}

	/**
	 * Count the rows matching the current constraints.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function count(): int {
		global $wpdb;

		$bindings = array( $this->model->get_table() );
		$sql      = 'SELECT COUNT(*) FROM %i';
		$sql     .= $this->compile_wheres( $bindings );

		/*
		 * Assembled only from literal fragments and %i/%s placeholders, bound
		 * through $wpdb->prepare(); an aggregate read over a custom table.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$value = $wpdb->get_var( $wpdb->prepare( $sql, $bindings ) );

		return null === $value ? 0 : (int) $value;
	}

	/**
	 * Sum a numeric column across the rows matching the current constraints.
	 *
	 * @since 0.1.0
	 *
	 * @param string $column Column to sum.
	 * @return float
	 */
	public function sum( string $column ): float {
		global $wpdb;

		$bindings = array( $column, $this->model->get_table() );
		$sql      = 'SELECT SUM(%i) FROM %i';
		$sql     .= $this->compile_wheres( $bindings );

		/*
		 * Assembled only from literal fragments and %i/%s placeholders, bound
		 * through $wpdb->prepare(); an aggregate read over a custom table.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$value = $wpdb->get_var( $wpdb->prepare( $sql, $bindings ) );

		return null === $value ? 0.0 : (float) $value;
	}

	/**
	 * Delete every row matching the current constraints.
	 *
	 * Refuses to run without at least one WHERE condition, so it can never wipe
	 * the whole table by accident.
	 *
	 * @since 0.1.0
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_where(): int {
		global $wpdb;

		if ( array() === $this->wheres ) {
			return 0;
		}

		$bindings = array( $this->model->get_table() );
		$sql      = 'DELETE FROM %i';
		$sql     .= $this->compile_wheres( $bindings );

		/*
		 * Assembled only from literal fragments and %i/%s placeholders, bound
		 * through $wpdb->prepare(); a guarded bulk delete over a custom table.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$deleted = $wpdb->query( $wpdb->prepare( $sql, $bindings ) );

		return is_int( $deleted ) ? $deleted : (int) $deleted;
	}

	/**
	 * Build the WHERE clause and append its bindings.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, mixed> $bindings Bindings collected so far (by reference).
	 * @return string
	 */
	private function compile_wheres( array &$bindings ): string {
		if ( array() === $this->wheres ) {
			return '';
		}

		$clauses = array();

		foreach ( $this->wheres as $where ) {
			if ( 'in' === $where['type'] ) {
				$clauses[] = $this->where_in_fragment( $where, $bindings );
				continue;
			}

			$clauses[]  = $this->where_fragment( $where['operator'] );
			$bindings[] = $where['column'];
			$bindings[] = $where['value'];
		}

		return ' WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Build an `IN (...)` fragment and append its bindings.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $where    The IN condition.
	 * @param array<int, mixed>    $bindings Bindings collected so far (by reference).
	 * @return string
	 */
	private function where_in_fragment( array $where, array &$bindings ): string {
		$values = is_array( $where['values'] ) ? $where['values'] : array();

		if ( array() === $values ) {
			return '1 = 0';
		}

		$bindings[]   = $where['column'];
		$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );

		foreach ( $values as $value ) {
			$bindings[] = $value;
		}

		return '%i IN (' . $placeholders . ')';
	}

	/**
	 * Map an already-validated operator to its literal SQL fragment.
	 *
	 * @since 0.1.0
	 *
	 * @param string $operator The (upper-cased) operator, guaranteed present in {@see FRAGMENTS} by {@see where()}.
	 * @return string
	 */
	private function where_fragment( string $operator ): string {
		return self::FRAGMENTS[ $operator ];
	}

	/**
	 * Build the ORDER BY clause and append its binding.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, mixed> $bindings Bindings collected so far (by reference).
	 * @return string
	 */
	private function compile_order( array &$bindings ): string {
		if ( '' === $this->order_column ) {
			return '';
		}

		$bindings[] = $this->order_column;

		return 'DESC' === $this->order_direction ? ' ORDER BY %i DESC' : ' ORDER BY %i ASC';
	}

	/**
	 * Reduce attributes to the model's fillable columns.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $attributes Raw attributes.
	 * @return array<string, mixed>
	 */
	private function only_fillable( array $attributes ): array {
		$fillable = $this->model->get_fillable();

		if ( array() === $fillable ) {
			return $attributes;
		}

		return array_intersect_key( $attributes, array_flip( $fillable ) );
	}
}
