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
		// No load_plugin_textdomain(): WordPress 4.6+ loads translations by slug, and
		// Plugin Check flags the call for wordpress.org plugins.

		if ( defined( 'WP_CLI' ) ) {
			$this->app->make( CommandLoader::class )->register();
		}
	}
}
