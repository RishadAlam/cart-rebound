<?php
/**
 * The application kernel: container plus provider lifecycle.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Core;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Contracts\ContainerInterface;

/**
 * The CartRebound application.
 *
 * Extends the container and orchestrates the two-phase service-provider
 * lifecycle (register all, then boot all). Implemented as a singleton so the
 * same instance is shared between the bootstrap file and runtime hooks.
 *
 * @since 0.1.0
 */
final class Application extends Container {

	/**
	 * The shared application instance.
	 *
	 * @since 0.1.0
	 * @var Application|null
	 */
	private static $instance;

	/**
	 * Absolute base path of the plugin (no trailing slash).
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $base_path;

	/**
	 * Registered service providers.
	 *
	 * @since 0.1.0
	 * @var array<int, ServiceProvider>
	 */
	private $service_providers = array();

	/**
	 * Whether the application has booted.
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string $base_path Absolute base path of the plugin.
	 */
	private function __construct( string $base_path ) {
		$this->base_path = rtrim( $base_path, '/\\' );

		$this->instance( Container::class, $this );
		$this->instance( self::class, $this );
		$this->instance( ContainerInterface::class, $this );
	}

	/**
	 * Get (or lazily create) the shared application instance.
	 *
	 * @since 0.1.0
	 *
	 * @param string|null $base_path Base path, required on first access.
	 * @return Application
	 *
	 * @throws BindingResolutionException When accessed before initialisation.
	 */
	public static function get_instance( ?string $base_path = null ): Application {
		if ( null === self::$instance ) {
			if ( null === $base_path ) {
				throw new BindingResolutionException(
					'The application must be created with a base path on first access.'
				);
			}

			self::$instance = new self( $base_path );
		}

		return self::$instance;
	}

	/**
	 * Register the configured providers and boot the application.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function bootstrap(): void {
		if ( $this->booted ) {
			return;
		}

		$this->register_configured_providers();
		$this->boot();
	}

	/**
	 * Register a service provider.
	 *
	 * @since 0.1.0
	 *
	 * @param ServiceProvider $provider Provider instance to register.
	 * @return ServiceProvider
	 */
	public function register( ServiceProvider $provider ): ServiceProvider {
		$provider->register();

		$this->service_providers[] = $provider;

		if ( $this->booted ) {
			$provider->boot();
		}

		return $provider;
	}

	/**
	 * Boot all registered providers.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		foreach ( $this->service_providers as $provider ) {
			$provider->boot();
		}

		$this->booted = true;
	}

	/**
	 * Get an absolute path relative to the plugin base path.
	 *
	 * @since 0.1.0
	 *
	 * @param string $path Optional sub-path.
	 * @return string
	 */
	public function base_path( string $path = '' ): string {
		if ( '' === $path ) {
			return $this->base_path;
		}

		return $this->base_path . DIRECTORY_SEPARATOR . ltrim( $path, '/\\' );
	}

	/**
	 * Get an absolute path inside the config directory.
	 *
	 * @since 0.1.0
	 *
	 * @param string $path Optional file inside config/.
	 * @return string
	 */
	public function config_path( string $path = '' ): string {
		return $this->base_path( '' === $path ? 'config' : 'config' . DIRECTORY_SEPARATOR . ltrim( $path, '/\\' ) );
	}

	/**
	 * Get an absolute path inside the routes directory.
	 *
	 * @since 0.1.0
	 *
	 * @param string $path Optional file inside routes/.
	 * @return string
	 */
	public function routes_path( string $path = '' ): string {
		return $this->base_path( '' === $path ? 'routes' : 'routes' . DIRECTORY_SEPARATOR . ltrim( $path, '/\\' ) );
	}

	/**
	 * Get the plugin version.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function version(): string {
		if ( defined( 'CART_REBOUND_VERSION' ) ) {
			return (string) constant( 'CART_REBOUND_VERSION' );
		}

		return '0.0.0';
	}

	/**
	 * Determine whether the application has booted.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_booted(): bool {
		return $this->booted;
	}

	/**
	 * Register all providers declared in config/app.php.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function register_configured_providers(): void {
		$config_file = $this->config_path( 'app.php' );

		if ( ! is_readable( $config_file ) ) {
			return;
		}

		/**
		 * Application configuration.
		 *
		 * @var array<string, mixed> $config
		 */
		$config = require $config_file;

		$providers = ( isset( $config['providers'] ) && is_array( $config['providers'] ) )
			? $config['providers']
			: array();

		foreach ( $providers as $provider ) {
			if ( is_string( $provider ) && is_subclass_of( $provider, ServiceProvider::class ) ) {
				$this->register( $this->make( $provider ) );
			}
		}
	}
}
