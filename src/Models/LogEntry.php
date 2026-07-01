<?php
/**
 * LogEntry model.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Models;

defined( 'ABSPATH' ) || exit;

/**
 * One row in `{$wpdb->prefix}cart_rebound_logs` — a single activity-log event.
 *
 * @since 0.2.0
 */
final class LogEntry extends Model {

	/**
	 * Informational event.
	 *
	 * @var string
	 */
	public const LEVEL_INFO = 'info';

	/**
	 * A positive outcome (e.g. a recovered cart).
	 *
	 * @var string
	 */
	public const LEVEL_SUCCESS = 'success';

	/**
	 * Something noteworthy that is not an error.
	 *
	 * @var string
	 */
	public const LEVEL_WARNING = 'warning';

	/**
	 * A failure (e.g. an email that could not be sent).
	 *
	 * @var string
	 */
	public const LEVEL_ERROR = 'error';

	/**
	 * The full level vocabulary.
	 *
	 * @var array<int, string>
	 */
	public const LEVELS = array(
		self::LEVEL_INFO,
		self::LEVEL_SUCCESS,
		self::LEVEL_WARNING,
		self::LEVEL_ERROR,
	);

	/**
	 * Unprefixed table suffix; resolves to `{$wpdb->prefix}cart_rebound_logs`.
	 *
	 * @var string
	 */
	protected $table = 'logs';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected $primary_key = 'id';

	/**
	 * Mass-assignable columns.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = array(
		'created_at',
		'level',
		'event',
		'message',
		'cart_id',
	);

	/**
	 * Column cast hints.
	 *
	 * @var array<string, string>
	 */
	protected $casts = array(
		'id'      => 'integer',
		'cart_id' => 'integer',
	);
}
