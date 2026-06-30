<?php
/**
 * Core application service provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Console\CommandLoader;
use CartRebound\Core\ServiceProvider;
use CartRebound\Http\Kernel;
use CartRebound\Http\Router;
use CartRebound\Support\Config;
use CartRebound\Support\Facades\Facade;

/**
 * Registers the framework's core singletons and wires up facades.
 *
 * @since 0.1.0
 */
final class AppServiceProvider extends ServiceProvider {

	/**
	 * Register core bindings.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton( Config::class );
		$this->app->singleton( Kernel::class );
		$this->app->singleton( Router::class );

		Facade::set_facade_application( $this->app );
	}

	/**
	 * Boot the provider.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		// Translations are loaded automatically by WordPress (by plugin slug)
		// since 4.6, so no load_plugin_textdomain() call is needed — and
		// calling it is flagged by Plugin Check for wordpress.org plugins.

		if ( defined( 'WP_CLI' ) ) {
			$this->app->make( CommandLoader::class )->register();
		}
	}
}
