<?php
/**
 * Abstract service provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Core;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Contracts\ServiceProviderInterface;

/**
 * Base class for service providers.
 *
 * Subclasses override {@see register()} to bind services and {@see boot()} to
 * wire WordPress hooks once every provider has been registered.
 *
 * @since 0.1.0
 */
abstract class ServiceProvider implements ServiceProviderInterface {

	/**
	 * The application instance.
	 *
	 * @since 0.1.0
	 * @var Application
	 */
	protected $app;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app The application instance.
	 */
	public function __construct( Application $app ) {
		$this->app = $app;
	}

	/**
	 * Register bindings into the container.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void {
	}

	/**
	 * Boot the provider once all providers have been registered.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
	}
}
