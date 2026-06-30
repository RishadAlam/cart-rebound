<?php
/**
 * REST route value object.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http;

defined( 'ABSPATH' ) || exit;

use Closure;

/**
 * Immutable-ish description of a single REST route and its middleware stack.
 *
 * @since 0.1.0
 */
final class Route {

	/**
	 * HTTP method (GET, POST, PUT, PATCH, DELETE).
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $method;

	/**
	 * Route endpoint, relative to the REST namespace.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $endpoint;

	/**
	 * Route handler: a closure or a [ controller-class, method ] pair.
	 *
	 * @since 0.1.0
	 * @var Closure|array{0: class-string, 1: string}
	 */
	private $handler;

	/**
	 * Middleware definitions to run in the permission callback.
	 *
	 * @since 0.1.0
	 * @var array<int, string>
	 */
	private $middleware = array();

	/**
	 * Whether the route is intentionally public (no authorization required).
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	private $is_public = false;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string                                    $method   HTTP method.
	 * @param string                                    $endpoint Endpoint pattern.
	 * @param Closure|array{0: class-string, 1: string} $handler  Route handler.
	 */
	public function __construct( string $method, string $endpoint, $handler ) {
		$this->method   = $method;
		$this->endpoint = $endpoint;
		$this->handler  = $handler;
	}

	/**
	 * Attach one or more middleware definitions.
	 *
	 * @since 0.1.0
	 *
	 * @param string|array<int, string> $middleware Middleware alias(es), e.g. 'nonce' or 'can:manage_options'.
	 * @return Route
	 */
	public function middleware( $middleware ): Route {
		$additional = is_array( $middleware ) ? $middleware : array( $middleware );

		$this->middleware = array_merge( $this->middleware, $additional );

		return $this;
	}

	/**
	 * Mark the route as intentionally public.
	 *
	 * Routes with no middleware are denied by the kernel (secure by default);
	 * this attaches the `public` middleware so a genuinely public, unauthenticated
	 * endpoint passes that gate explicitly.
	 *
	 * @since 0.1.0
	 *
	 * @return Route
	 */
	public function public(): Route {
		$this->is_public = true;

		return $this->middleware( 'public' );
	}

	/**
	 * Whether the route was explicitly marked public.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_public(): bool {
		return $this->is_public;
	}

	/**
	 * Get the HTTP method.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function method(): string {
		return $this->method;
	}

	/**
	 * Get the endpoint pattern.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function endpoint(): string {
		return $this->endpoint;
	}

	/**
	 * Get the route handler.
	 *
	 * @since 0.1.0
	 *
	 * @return Closure|array{0: class-string, 1: string}
	 */
	public function handler() {
		return $this->handler;
	}

	/**
	 * Get the middleware stack.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, string>
	 */
	public function get_middleware(): array {
		return $this->middleware;
	}
}
