<?php
/**
 * Route facade.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Support\Facades;

defined( 'ABSPATH' ) || exit;

use CartRebound\Http\Router;

/**
 * Static proxy to the {@see Router} singleton.
 *
 * @since 0.1.0
 *
 * @method static \CartRebound\Http\Route get( string $endpoint, \Closure|array{0: class-string, 1: string} $handler )
 * @method static \CartRebound\Http\Route post( string $endpoint, \Closure|array{0: class-string, 1: string} $handler )
 * @method static \CartRebound\Http\Route put( string $endpoint, \Closure|array{0: class-string, 1: string} $handler )
 * @method static \CartRebound\Http\Route patch( string $endpoint, \Closure|array{0: class-string, 1: string} $handler )
 * @method static \CartRebound\Http\Route delete( string $endpoint, \Closure|array{0: class-string, 1: string} $handler )
 * @method static string get_namespace()
 */
final class Route extends Facade {

	/**
	 * Get the container identifier the facade proxies to.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	protected static function get_facade_accessor(): string {
		return Router::class;
	}
}
