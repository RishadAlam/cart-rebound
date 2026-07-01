<?php
/**
 * CartSession model.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Represents one tracked cart row in `{$wpdb->prefix}cart_rebound_sessions`.
 *
 * Rows are plain associative arrays (the query layer does no hydration); this
 * class is configuration only — table name, writable columns, and the lifecycle
 * status vocabulary used across the plugin.
 *
 * @since 0.1.0
 */
final class CartSession extends Model {

	/**
	 * Newly created / still-active cart.
	 *
	 * @var string
	 */
	public const STATUS_ACTIVE = 'active';

	/**
	 * Idle past the threshold; abandonment event fired.
	 *
	 * @var string
	 */
	public const STATUS_ABANDONED = 'abandoned';

	/**
	 * Abandoned cart that converted to an order.
	 *
	 * @var string
	 */
	public const STATUS_RECOVERED = 'recovered';

	/**
	 * Cart that converted without ever being abandoned.
	 *
	 * @var string
	 */
	public const STATUS_COMPLETED = 'completed';

	/**
	 * Abandoned cart purged after the cleanup window with no order.
	 *
	 * @var string
	 */
	public const STATUS_LOST = 'lost';

	/**
	 * The full lifecycle status vocabulary, in funnel order.
	 *
	 * @var array<int, string>
	 */
	public const STATUSES = array(
		self::STATUS_ACTIVE,
		self::STATUS_ABANDONED,
		self::STATUS_RECOVERED,
		self::STATUS_COMPLETED,
		self::STATUS_LOST,
	);

	/**
	 * Unprefixed table suffix; resolves to `{$wpdb->prefix}cart_rebound_sessions`.
	 *
	 * @var string
	 */
	protected $table = 'sessions';

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
		'session_key',
		'user_id',
		'email',
		'first_name',
		'last_name',
		'phone',
		'cart_contents',
		'cart_total',
		'currency',
		'items_count',
		'coupons',
		'checkout_url',
		'status',
		'recovery_token',
		'abandonment_notified',
		'email_sent',
		'order_id',
		'recovered_amount',
		'created_at',
		'last_activity',
		'abandoned_at',
		'recovered_at',
		'completed_at',
	);

	/**
	 * Column cast hints (informational; the query layer returns raw strings).
	 *
	 * @var array<string, string>
	 */
	protected $casts = array(
		'id'                   => 'integer',
		'user_id'              => 'integer',
		'items_count'          => 'integer',
		'abandonment_notified' => 'boolean',
		'email_sent'           => 'boolean',
		'order_id'             => 'integer',
		'cart_total'           => 'float',
		'recovered_amount'     => 'float',
	);
}
