<?php
/**
 * Activity log read/write API.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Data;

defined( 'ABSPATH' ) || exit;

use CartRebound\Models\LogEntry;

/**
 * Writes activity-log entries and reads them back for the admin log view.
 *
 * The table is self-capping: writes occasionally prune the oldest rows beyond
 * {@see MAX_ROWS} so the log cannot grow without bound.
 *
 * @since 0.1.0
 */
final class LogRepository {

	/**
	 * Maximum rows to retain.
	 *
	 * @var int
	 */
	private const MAX_ROWS = 5000;

	/**
	 * Prune, on average, once per this many writes.
	 *
	 * @var int
	 */
	private const PRUNE_EVERY = 25;

	/**
	 * Record an event.
	 *
	 * @since 0.1.0
	 *
	 * @param string $level   One of {@see LogEntry::LEVELS}.
	 * @param string $event   Short event key (e.g. `abandoned`, `recovered`).
	 * @param string $message Human-readable message.
	 * @param int    $cart_id Related cart id, if any.
	 * @return void
	 */
	public function log( string $level, string $event, string $message, int $cart_id = 0 ): void {
		LogEntry::create(
			array(
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'level'      => in_array( $level, LogEntry::LEVELS, true ) ? $level : LogEntry::LEVEL_INFO,
				'event'      => substr( $event, 0, 50 ),
				'message'    => $message,
				'cart_id'    => max( 0, $cart_id ),
			)
		);

		if ( 1 === wp_rand( 1, self::PRUNE_EVERY ) ) {
			$this->prune();
		}
	}

	/**
	 * Get a filtered, paginated page of log entries (newest first).
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $level    Level filter.
	 *     @type string $event    Event-key filter (e.g. `email_sent`).
	 *     @type int    $cart_id  Filter to a single cart.
	 *     @type int    $page     1-based page number.
	 *     @type int    $per_page Page size (max 100).
	 * }
	 * @return array<string, mixed>
	 */
	public function paginate( array $args ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = (int) ( $args['per_page'] ?? 0 );
		$per_page = $per_page > 0 ? min( 100, $per_page ) : 20;
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $this->filtered( $args )
			->order_by( 'id', 'DESC' )
			->limit( $per_page )
			->offset( $offset )
			->get();

		return array(
			'items'    => array_map( array( $this, 'present' ), $rows ),
			'total'    => $this->filtered( $args )->count(),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Delete every log entry.
	 *
	 * @since 0.1.0
	 *
	 * @return int Rows deleted.
	 */
	public function clear(): int {
		return LogEntry::query()->where( 'id', '>', 0 )->delete_where();
	}

	/**
	 * Trim the log to the most recent {@see MAX_ROWS} rows.
	 *
	 * @since 0.1.0
	 *
	 * @return int Rows deleted.
	 */
	public function prune(): int {
		$edge = LogEntry::query()
			->order_by( 'id', 'DESC' )
			->limit( 1 )
			->offset( self::MAX_ROWS )
			->get();

		$cutoff = isset( $edge[0]['id'] ) ? (int) $edge[0]['id'] : 0;

		if ( $cutoff <= 0 ) {
			return 0;
		}

		return LogEntry::query()->where( 'id', '<', $cutoff )->delete_where();
	}

	/**
	 * Build a query constrained by the level, event, and cart filters.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $args Query arguments (level, event, cart_id).
	 * @return \CartRebound\Models\QueryBuilder
	 */
	private function filtered( array $args ) {
		$query = LogEntry::query();

		$level = (string) ( $args['level'] ?? '' );

		if ( in_array( $level, LogEntry::LEVELS, true ) ) {
			$query->where( 'level', '=', $level );
		}

		$event = (string) ( $args['event'] ?? '' );

		if ( '' !== $event ) {
			$query->where( 'event', '=', $event );
		}

		$cart_id = (int) ( $args['cart_id'] ?? 0 );

		if ( $cart_id > 0 ) {
			$query->where( 'cart_id', '=', $cart_id );
		}

		return $query;
	}

	/**
	 * Normalise a raw row into the API shape.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private function present( array $row ): array {
		return array(
			'id'         => (int) ( $row['id'] ?? 0 ),
			'created_at' => (string) ( $row['created_at'] ?? '' ),
			'level'      => (string) ( $row['level'] ?? 'info' ),
			'event'      => (string) ( $row['event'] ?? '' ),
			'message'    => (string) ( $row['message'] ?? '' ),
			'cart_id'    => (int) ( $row['cart_id'] ?? 0 ),
		);
	}
}
