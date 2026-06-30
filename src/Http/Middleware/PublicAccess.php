<?php
/**
 * Public-access middleware.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;

/**
 * Explicit opt-in for an unauthenticated route.
 *
 * The kernel denies any route with an empty middleware stack (secure by
 * default). A genuinely public endpoint declares this middleware — via
 * {@see \CartRebound\Http\Route::public()} — to pass the gate intentionally.
 *
 * @since 0.1.0
 */
final class PublicAccess implements Middleware {

	/**
	 * Handle the request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The current REST request.
	 * @return true
	 */
	public function handle( WP_REST_Request $request ) {
		return true;
	}
}
