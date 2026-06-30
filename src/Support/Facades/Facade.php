<?php
/**
 * Abstract facade.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Support\Facades;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use CartRebound\Core\BindingResolutionException;

/**
 * Base class providing static proxy access to a container-resolved service.
 *
 * @since 0.1.0
 */
abstract class Facade {

	/**
	 * The application used to resolve facade roots.
	 *
	 * @since 0.1.0
	 * @var Application|null
	 */
	private static $app;

	/**
	 * Set the application instance used by all facades.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app The application instance.
	 * @return void
	 */
	public static function set_facade_application( Application $app ): void {
		self::$app = $app;
	}

	/**
	 * Get the container identifier the facade proxies to.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	abstract protected static function get_facade_accessor(): string;

	/**
	 * Proxy static calls to the resolved instance.
	 *
	 * @since 0.1.0
	 *
	 * @param string            $method    Method being called.
	 * @param array<int, mixed> $arguments Call arguments.
	 * @return mixed
	 *
	 * @throws BindingResolutionException When the facade is used before being bound.
	 */
	public static function __callStatic( string $method, array $arguments ) {
		if ( null === self::$app ) {
			throw new BindingResolutionException(
				esc_html__( 'The facade application has not been set.', 'cart-rebound' )
			);
		}

		$accessor = static::get_facade_accessor();
		$instance = self::$app->make( $accessor );

		if ( ! is_object( $instance ) ) {
			throw new BindingResolutionException(
				esc_html( sprintf( 'Facade accessor [%s] did not resolve to an object.', $accessor ) )
			);
		}

		return $instance->$method( ...$arguments );
	}
}
