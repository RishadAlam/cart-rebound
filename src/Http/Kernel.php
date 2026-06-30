<?php
/**
 * Middleware pipeline kernel.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use CartRebound\Core\BindingResolutionException;
use CartRebound\Http\Middleware\HandleCors;
use CartRebound\Http\Middleware\Middleware;
use CartRebound\Http\Middleware\PublicAccess;
use CartRebound\Http\Middleware\RequireCapability;
use CartRebound\Http\Middleware\VerifyNonce;
use WP_Error;
use WP_REST_Request;

/**
 * Resolves middleware aliases and runs them as a short-circuiting pipeline.
 *
 * @since 0.1.0
 */
final class Kernel {

	/**
	 * The application/container instance.
	 *
	 * @since 0.1.0
	 * @var Application
	 */
	private $app;

	/**
	 * Middleware alias map.
	 *
	 * @since 0.1.0
	 * @var array<string, class-string<Middleware>>
	 */
	private $aliases = array(
		'nonce'  => VerifyNonce::class,
		'cors'   => HandleCors::class,
		'public' => PublicAccess::class,
	);

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
	 * Run the middleware stack for a request.
	 *
	 * @since 0.1.0
	 *
	 * An empty stack is denied (secure by default): a route must declare at
	 * least one middleware, or opt into public access via the `public` alias.
	 *
	 * @param array<int, string> $middleware Middleware definitions.
	 * @param WP_REST_Request    $request    The current REST request.
	 * @return true|WP_Error The first failing WP_Error, or true if all pass.
	 *
	 * @throws BindingResolutionException When a middleware definition cannot be resolved.
	 */
	public function run( array $middleware, WP_REST_Request $request ) {
		if ( array() === $middleware ) {
			return new WP_Error(
				'cart_rebound_no_authorization',
				__( 'This route has no authorization middleware. Add ->middleware(...) or mark it ->public() explicitly.', 'cart-rebound' ),
				array( 'status' => 403 )
			);
		}

		foreach ( $middleware as $definition ) {
			$result = $this->resolve( $definition )->handle( $request );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Resolve a middleware definition into an instance.
	 *
	 * @since 0.1.0
	 *
	 * @param string $definition Alias, `can:<capability>`, or class name.
	 * @return Middleware
	 *
	 * @throws BindingResolutionException When the definition does not resolve to middleware.
	 */
	private function resolve( string $definition ): Middleware {
		if ( 0 === strpos( $definition, 'can:' ) ) {
			return new RequireCapability( substr( $definition, 4 ) );
		}

		$class_name = $this->aliases[ $definition ] ?? $definition;

		$instance = $this->app->make( $class_name );

		if ( ! $instance instanceof Middleware ) {
			throw new BindingResolutionException(
				esc_html( sprintf( 'Middleware [%s] must implement the Middleware contract.', $definition ) )
			);
		}

		return $instance;
	}
}
