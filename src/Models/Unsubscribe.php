<?php
/**
 * Unsubscribe model.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Models;

defined( 'ABSPATH' ) || exit;

/**
 * One row in `{$wpdb->prefix}cart_rebound_unsubscribes` — an address that opted
 * out of recovery emails.
 *
 * @since 0.1.0
 */
final class Unsubscribe extends Model {

	/**
	 * Unprefixed table suffix; resolves to `{$wpdb->prefix}cart_rebound_unsubscribes`.
	 *
	 * @var string
	 */
	protected $table = 'unsubscribes';

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
		'email',
		'created_at',
	);

	/**
	 * Column cast hints.
	 *
	 * @var array<string, string>
	 */
	protected $casts = array(
		'id' => 'integer',
	);

	/**
	 * Whether an address has unsubscribed from recovery emails.
	 *
	 * @since 0.1.0
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function is_suppressed( string $email ): bool {
		$email = sanitize_email( $email );

		if ( '' === $email ) {
			return false;
		}

		return is_array( self::query()->where( 'email', '=', $email )->first() );
	}

	/**
	 * Suppression result: this call added the address.
	 *
	 * @var string
	 */
	public const ADDED = 'added';

	/**
	 * Suppression result: the address was already suppressed.
	 *
	 * @var string
	 */
	public const EXISTING = 'existing';

	/**
	 * Add an address to the suppression list (idempotent).
	 *
	 * @since 0.1.0
	 *
	 * @param string $email Email address.
	 * @return string {@see ADDED} or {@see EXISTING} when the address is suppressed after the call, '' when it could not be.
	 */
	public static function suppress( string $email ): string {
		$email = sanitize_email( $email );

		if ( '' === $email || ! is_email( $email ) ) {
			return '';
		}

		if ( self::is_suppressed( $email ) ) {
			return self::EXISTING;
		}

		$id = self::create(
			array(
				'email'      => $email,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		if ( is_int( $id ) ) {
			return self::ADDED;
		}

		// A concurrent insert may win the UNIQUE(email) race, so report the end state, not this insert's result.
		return is_array( self::query()->where( 'email', '=', $email )->first() ) ? self::EXISTING : '';
	}
}
