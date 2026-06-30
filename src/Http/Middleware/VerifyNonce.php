<?php
/**
 * Nonce verification middleware.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;

/**
 * Verifies the `X-WP-Nonce` request header against the `wp_rest` action.
 *
 * @since 0.1.0
 */
final class VerifyNonce implements Middleware {

	/**
	 * Handle the request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The current REST request.
	 * @return true|WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'cart_rebound_invalid_nonce',
				__( 'Your session has expired. Please reload the page and try again.', 'cart-rebound' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}
}
