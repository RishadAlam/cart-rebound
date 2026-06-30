<?php
/**
 * Configuration repository.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Support;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;

/**
 * Loads and exposes the PHP files in the config/ directory via dot notation.
 *
 * @since 0.1.0
 */
final class Config {

	/**
	 * Loaded configuration, keyed by file name.
	 *
	 * @since 0.1.0
	 * @var array<string, mixed>
	 */
	private $items = array();

	/**
	 * Constructor. Eagerly loads the known config files.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app The application instance.
	 */
	public function __construct( Application $app ) {
		foreach ( array( 'app', 'database', 'routes' ) as $name ) {
			$file = $app->config_path( $name . '.php' );

			if ( ! is_readable( $file ) ) {
				continue;
			}

			$loaded = require $file;

			if ( is_array( $loaded ) ) {
				$this->items[ $name ] = $loaded;
			}
		}
	}

	/**
	 * Get a configuration value using dot notation.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key      Dot-notation key, e.g. "database.charset".
	 * @param mixed  $fallback Value returned when the key is absent.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		return Arr::get( $this->items, $key, $fallback );
	}

	/**
	 * Determine whether a configuration key exists.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key Dot-notation key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return Arr::has( $this->items, $key );
	}

	/**
	 * Set a configuration value at runtime.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key   Dot-notation key.
	 * @param mixed  $value The value to set.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$this->items = Arr::set( $this->items, $key, $value );
	}

	/**
	 * Get all loaded configuration.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->items;
	}
}
