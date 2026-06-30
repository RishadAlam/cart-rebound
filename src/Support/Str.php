<?php
/**
 * String helpers.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Small string-casing helpers used by code generators and naming utilities.
 *
 * @since 0.1.0
 */
final class Str {

	/**
	 * Convert a value to StudlyCase.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value The input string.
	 * @return string
	 */
	public static function studly( string $value ): string {
		$words = str_replace( array( '-', '_' ), ' ', $value );
		$words = ucwords( $words );

		return str_replace( ' ', '', $words );
	}

	/**
	 * Convert a value to camelCase.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value The input string.
	 * @return string
	 */
	public static function camel( string $value ): string {
		return lcfirst( self::studly( $value ) );
	}

	/**
	 * Convert a value to snake_case.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value The input string.
	 * @return string
	 */
	public static function snake( string $value ): string {
		$value = (string) preg_replace( '/\s+/u', '', ucwords( $value ) );
		$value = (string) preg_replace( '/(.)(?=[A-Z])/u', '$1_', $value );

		return strtolower( $value );
	}

	/**
	 * Convert a value to kebab-case.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value The input string.
	 * @return string
	 */
	public static function kebab( string $value ): string {
		return str_replace( '_', '-', self::snake( $value ) );
	}
}
