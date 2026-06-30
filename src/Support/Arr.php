<?php
/**
 * Array helpers.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Dot-notation array helpers.
 *
 * @since 0.1.0
 */
final class Arr {

	/**
	 * Get a value using "dot" notation.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $items    The array to read.
	 * @param string               $key      Dot-notation key.
	 * @param mixed                $fallback Value returned when the key is absent.
	 * @return mixed
	 */
	public static function get( array $items, string $key, $fallback = null ) {
		if ( array_key_exists( $key, $items ) ) {
			return $items[ $key ];
		}

		$current = $items;

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return $fallback;
			}

			$current = $current[ $segment ];
		}

		return $current;
	}

	/**
	 * Determine whether a "dot" notation key exists.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $items The array to read.
	 * @param string               $key   Dot-notation key.
	 * @return bool
	 */
	public static function has( array $items, string $key ): bool {
		if ( array_key_exists( $key, $items ) ) {
			return true;
		}

		$current = $items;

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return false;
			}

			$current = $current[ $segment ];
		}

		return true;
	}

	/**
	 * Set a value using "dot" notation and return the new array.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $items The array to modify.
	 * @param string               $key   Dot-notation key.
	 * @param mixed                $value The value to set.
	 * @return array<string, mixed>
	 */
	public static function set( array $items, string $key, $value ): array {
		$segments = explode( '.', $key );
		$last     = array_pop( $segments );

		if ( null === $last ) {
			return $items;
		}

		$pointer = &$items;

		foreach ( $segments as $segment ) {
			if ( ! isset( $pointer[ $segment ] ) || ! is_array( $pointer[ $segment ] ) ) {
				$pointer[ $segment ] = array();
			}

			$pointer = &$pointer[ $segment ];
		}

		$pointer[ $last ] = $value;

		return $items;
	}
}
