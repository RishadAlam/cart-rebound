<?php
/**
 * Route service provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\ServiceProvider;
use CartRebound\Http\Router;

/**
 * Loads the REST routes and registers them on `rest_api_init`.
 *
 * @since 0.1.0
 */
final class RouteServiceProvider extends ServiceProvider {

	/**
	 * Boot the provider.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action(
			'rest_api_init',
			function (): void {
				$router = $this->app->make( Router::class );
				$files  = glob( $this->app->routes_path() . '/*.php' );

				if ( false === $files ) {
					$files = array();
				}

				// Deterministic load order regardless of filesystem ordering.
				sort( $files );

				foreach ( $files as $route_file ) {
					if ( is_readable( $route_file ) ) {
						require $route_file;
					}
				}

				$router->register_routes();
			}
		);
	}
}
