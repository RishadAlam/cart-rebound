<?php
/**
 * Middleware contract.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;

/**
 * Contract for REST middleware run inside the permission callback.
 *
 * @since 0.1.0
 */
interface Middleware {

	/**
	 * Handle the request.
	 *
	 * Return true to allow the request through, or a WP_Error (with an HTTP
	 * status in its data) to reject it.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The current REST request.
	 * @return true|WP_Error
	 */
	public function handle( WP_REST_Request $request );
}
