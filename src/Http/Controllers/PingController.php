<?php
/**
 * Example ping controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Minimal example controller used by the boilerplate's `/ping` route.
 *
 * @since 0.1.0
 */
final class PingController extends Controller {

	/**
	 * Respond to GET /ping.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The current REST request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		return $this->respond(
			array(
				'pong'    => true,
				'version' => $this->app->version(),
			)
		);
	}
}
