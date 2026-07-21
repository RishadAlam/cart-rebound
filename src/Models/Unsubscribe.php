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
	 * Add an address to the suppression list (idempotent).
	 *
	 * @since 0.1.0
	 *
	 * @param string $email Email address.
	 * @return bool True when the address is suppressed after the call.
	 */
	public static function suppress( string $email ): bool {
		$email = sanitize_email( $email );

		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}

		if ( self::is_suppressed( $email ) ) {
			return true;
		}

		$id = self::create(
			array(
				'email'      => $email,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		if ( is_int( $id ) ) {
			return true;
		}

		// The insert can fail because a concurrent request won the UNIQUE(email)
		// race; a row that exists now still means the address is suppressed, so
		// report the end state rather than this call's insert result.
		return is_array( self::query()->where( 'email', '=', $email )->first() );
	}
}
