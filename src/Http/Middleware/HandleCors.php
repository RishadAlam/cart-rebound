<?php
/**
 * CORS middleware.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;

/**
 * Per-route Origin gate.
 *
 * This is an OPT-IN middleware: it only runs on routes that declare the `cors`
 * alias (`->middleware('cors')`), so it is not a site-wide CORS policy. On those
 * routes, requests with no `Origin` header (same-origin / non-browser) pass
 * straight through; a cross-origin request is allowed only when its origin is in
 * the allow-list, which defaults to the site's own origin and can be extended via
 * the `cart_rebound_allowed_cors_origins` filter. Emitting `Access-Control-*`
 * response headers remains WordPress core's responsibility (it reflects the
 * origin on `rest_pre_serve_request`); this middleware only blocks disallowed
 * origins.
 *
 * @since 0.1.0
 */
final class HandleCors implements Middleware {

	/**
	 * Handle the request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The current REST request.
	 * @return true|WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$origin = $request->get_header( 'Origin' );

		if ( ! is_string( $origin ) || '' === $origin ) {
			return true;
		}

		if ( in_array( $this->normalize_origin( $origin ), $this->allowed_origins(), true ) ) {
			return true;
		}

		return new WP_Error(
			'cart_rebound_cors_forbidden',
			__( 'Cross-origin requests from this origin are not allowed.', 'cart-rebound' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Build the list of allowed (normalised) origins.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, string>
	 */
	private function allowed_origins(): array {
		$origins = array( (string) home_url(), (string) site_url() );

		/**
		 * Filter the REST origins allowed by the CORS middleware.
		 *
		 * Values may be full URLs or bare origins; each is normalised to
		 * `scheme://host[:port]` before comparison.
		 *
		 * @since 0.1.0
		 *
		 * @param array<int, string> $origins Allowed origins or URLs.
		 */
		$origins = (array) apply_filters( 'cart_rebound_allowed_cors_origins', $origins );

		$normalized = array_map( array( $this, 'normalize_origin' ), $origins );

		return array_values( array_unique( array_filter( $normalized ) ) );
	}

	/**
	 * Reduce a URL to its `scheme://host[:port]` origin, lower-cased.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url The URL or origin to normalise.
	 * @return string
	 */
	private function normalize_origin( string $url ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = isset( $parts['scheme'] ) ? (string) $parts['scheme'] : 'https';
		$origin = $scheme . '://' . strtolower( (string) $parts['host'] );

		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}

		return $origin;
	}
}
