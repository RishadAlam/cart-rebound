<?php
/**
 * REST router.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use CartRebound\Http\Requests\FormRequest;
use Closure;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;
use WP_Error;
use WP_REST_Request;

/**
 * Fluent wrapper around register_rest_route().
 *
 * Routes are collected as {@see Route} objects and registered in bulk on the
 * `rest_api_init` hook. The permission callback always runs the middleware
 * pipeline, so no route is ever publicly callable by accident.
 *
 * @since 0.1.0
 */
final class Router {

	/**
	 * REST namespace, e.g. "cart-rebound/v1".
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const REST_NAMESPACE = 'cart-rebound/v1';

	/**
	 * The application/container instance.
	 *
	 * @since 0.1.0
	 * @var Application
	 */
	private $app;

	/**
	 * The middleware kernel.
	 *
	 * @since 0.1.0
	 * @var Kernel
	 */
	private $kernel;

	/**
	 * Registered routes.
	 *
	 * @since 0.1.0
	 * @var array<int, Route>
	 */
	private $routes = array();

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app    The application instance.
	 * @param Kernel      $kernel The middleware kernel.
	 */
	public function __construct( Application $app, Kernel $kernel ) {
		$this->app    = $app;
		$this->kernel = $kernel;
	}

	/**
	 * Register a GET route.
	 *
	 * @since 0.1.0
	 *
	 * @param string                                    $endpoint Endpoint pattern.
	 * @param Closure|array{0: class-string, 1: string} $handler  Route handler.
	 * @return Route
	 */
	public function get( string $endpoint, $handler ): Route {
		return $this->add_route( 'GET', $endpoint, $handler );
	}

	/**
	 * Register a POST route.
	 *
	 * @since 0.1.0
	 *
	 * @param string                                    $endpoint Endpoint pattern.
	 * @param Closure|array{0: class-string, 1: string} $handler  Route handler.
	 * @return Route
	 */
	public function post( string $endpoint, $handler ): Route {
		return $this->add_route( 'POST', $endpoint, $handler );
	}

	/**
	 * Register a PUT route.
	 *
	 * @since 0.1.0
	 *
	 * @param string                                    $endpoint Endpoint pattern.
	 * @param Closure|array{0: class-string, 1: string} $handler  Route handler.
	 * @return Route
	 */
	public function put( string $endpoint, $handler ): Route {
		return $this->add_route( 'PUT', $endpoint, $handler );
	}

	/**
	 * Register a PATCH route.
	 *
	 * @since 0.1.0
	 *
	 * @param string                                    $endpoint Endpoint pattern.
	 * @param Closure|array{0: class-string, 1: string} $handler  Route handler.
	 * @return Route
	 */
	public function patch( string $endpoint, $handler ): Route {
		return $this->add_route( 'PATCH', $endpoint, $handler );
	}

	/**
	 * Register a DELETE route.
	 *
	 * @since 0.1.0
	 *
	 * @param string                                    $endpoint Endpoint pattern.
	 * @param Closure|array{0: class-string, 1: string} $handler  Route handler.
	 * @return Route
	 */
	public function delete( string $endpoint, $handler ): Route {
		return $this->add_route( 'DELETE', $endpoint, $handler );
	}

	/**
	 * Register all collected routes with WordPress.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		foreach ( $this->routes as $route ) {
			$endpoint = $route->endpoint();

			if ( '' === $endpoint || '0' === $endpoint ) {
				continue;
			}

			register_rest_route(
				self::REST_NAMESPACE,
				$endpoint,
				array(
					'methods'             => $route->method(),
					'callback'            => function ( WP_REST_Request $request ) use ( $route ) {
						return $this->guarded(
							function () use ( $route, $request ) {
								return $this->dispatch( $route, $request );
							}
						);
					},
					'permission_callback' => function ( WP_REST_Request $request ) use ( $route ) {
						return $this->guarded(
							function () use ( $route, $request ) {
								return $this->kernel->run( $route->get_middleware(), $request );
							}
						);
					},
				)
			);
		}
	}

	/**
	 * Get the REST namespace.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_namespace(): string {
		return self::REST_NAMESPACE;
	}

	/**
	 * Create and store a route.
	 *
	 * @since 0.1.0
	 *
	 * @param string                                    $method   HTTP method.
	 * @param string                                    $endpoint Endpoint pattern.
	 * @param Closure|array{0: class-string, 1: string} $handler  Route handler.
	 * @return Route
	 */
	private function add_route( string $method, string $endpoint, $handler ): Route {
		$route = new Route( $method, $endpoint, $handler );

		$this->routes[] = $route;

		return $route;
	}

	/**
	 * Dispatch a matched route to its handler.
	 *
	 * @since 0.1.0
	 *
	 * @param Route           $route   The matched route.
	 * @param WP_REST_Request $request The current REST request.
	 * @return mixed
	 */
	private function dispatch( Route $route, WP_REST_Request $request ) {
		$handler = $route->handler();

		if ( $handler instanceof Closure ) {
			return $handler( $request );
		}

		$controller = $this->app->make( $handler[0] );
		$method     = $handler[1];

		$arguments = $this->resolve_handler_arguments( $controller, $method, $request );

		if ( $arguments instanceof WP_Error ) {
			return $arguments;
		}

		return $controller->$method( ...$arguments );
	}

	/**
	 * Run a route callback, converting any uncaught exception into a 500.
	 *
	 * @since 0.1.0
	 *
	 * @param callable $callback The callback to run.
	 * @return mixed
	 */
	private function guarded( callable $callback ) {
		try {
			return $callback();
		} catch ( Throwable $exception ) {
			return $this->server_error( $exception );
		}
	}

	/**
	 * Resolve a controller method's arguments from the request.
	 *
	 * @since 0.1.0
	 *
	 * @param object          $controller The controller instance.
	 * @param string          $method     The handler method name.
	 * @param WP_REST_Request $request    The current REST request.
	 * @return array<int, mixed>|WP_Error Positional arguments, or a validation WP_Error.
	 */
	private function resolve_handler_arguments( object $controller, string $method, WP_REST_Request $request ) {
		$reflection = new ReflectionMethod( $controller, $method );
		$arguments  = array();

		foreach ( $reflection->getParameters() as $parameter ) {
			$argument = $this->resolve_argument( $parameter, $request );

			if ( $argument instanceof WP_Error ) {
				return $argument;
			}

			$arguments[] = $argument;
		}

		return $arguments;
	}

	/**
	 * Resolve a single controller-method parameter.
	 *
	 * Priority: a {@see FormRequest} is constructed and validated (its WP_Error
	 * is returned on failure); a `WP_REST_Request` receives the request; any
	 * other class is built by the container; an untyped/builtin parameter
	 * receives the request. Union/intersection types are scanned in the same
	 * priority order, so a FormRequest is never skipped.
	 *
	 * @since 0.1.0
	 *
	 * @param ReflectionParameter $parameter The parameter to resolve.
	 * @param WP_REST_Request     $request   The current REST request.
	 * @return mixed The resolved argument, or a WP_Error from FormRequest validation.
	 */
	private function resolve_argument( ReflectionParameter $parameter, WP_REST_Request $request ) {
		$class_names = $this->parameter_class_names( $parameter );

		foreach ( $class_names as $class_name ) {
			if ( is_subclass_of( $class_name, FormRequest::class ) ) {
				$form_request = new $class_name( $request );
				$validated    = $form_request->validate();

				if ( is_wp_error( $validated ) ) {
					return $validated;
				}

				return $form_request;
			}
		}

		foreach ( $class_names as $class_name ) {
			if ( WP_REST_Request::class === $class_name || is_subclass_of( $class_name, WP_REST_Request::class ) ) {
				return $request;
			}
		}

		foreach ( $class_names as $class_name ) {
			return $this->app->make( $class_name );
		}

		return $request;
	}

	/**
	 * Extract the non-builtin class names declared by a parameter's type.
	 *
	 * Handles single named types as well as PHP 8 union/intersection types
	 * (detected via getTypes() so the code still parses on PHP 7.4).
	 *
	 * @since 0.1.0
	 *
	 * @param ReflectionParameter $parameter The parameter to inspect.
	 * @return array<int, string>
	 */
	private function parameter_class_names( ReflectionParameter $parameter ): array {
		$type = $parameter->getType();

		if ( $type instanceof ReflectionNamedType ) {
			return $type->isBuiltin() ? array() : array( $type->getName() );
		}

		if ( null !== $type && method_exists( $type, 'getTypes' ) ) {
			$names = array();

			foreach ( $type->getTypes() as $inner ) {
				if ( $inner instanceof ReflectionNamedType && ! $inner->isBuiltin() ) {
					$names[] = $inner->getName();
				}
			}

			return $names;
		}

		return array();
	}

	/**
	 * Convert an uncaught exception into a generic 500 response.
	 *
	 * The exception message is never exposed to the client; details are written
	 * to the error log only when WP_DEBUG is enabled.
	 *
	 * @since 0.1.0
	 *
	 * @param Throwable $exception The caught exception.
	 * @return WP_Error
	 */
	private function server_error( Throwable $exception ): WP_Error {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'CartRebound route error: %s', $exception->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return new WP_Error(
			'cart_rebound_server_error',
			__( 'An unexpected error occurred while handling the request.', 'cart-rebound' ),
			array( 'status' => 500 )
		);
	}
}
