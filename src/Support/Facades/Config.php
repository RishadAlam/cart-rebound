<?php
/**
 * Config facade.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Support\Facades;

defined( 'ABSPATH' ) || exit;

use CartRebound\Support\Config as ConfigRepository;

/**
 * Static proxy to the configuration repository.
 *
 * @since 0.1.0
 *
 * @method static mixed get( string $key, mixed $fallback = null )
 * @method static bool has( string $key )
 * @method static void set( string $key, mixed $value )
 * @method static array<string, mixed> all()
 */
final class Config extends Facade {

	/**
	 * Get the container identifier the facade proxies to.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	protected static function get_facade_accessor(): string {
		return ConfigRepository::class;
	}
}
