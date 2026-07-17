<?php
/**
 * Activity log controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use CartRebound\Data\LogRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Admin read/clear endpoints for the activity log.
 *
 * @since 0.1.0
 */
final class LogController extends Controller {

	/**
	 * Log repository.
	 *
	 * @since 0.1.0
	 * @var LogRepository
	 */
	private $logs;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application   $app  Application instance.
	 * @param LogRepository $logs Log repository.
	 */
	public function __construct( Application $app, LogRepository $logs ) {
		parent::__construct( $app );
		$this->logs = $logs;
	}

	/**
	 * List log entries with optional level filter + paging.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		return $this->respond(
			$this->logs->paginate(
				array(
					'level'    => sanitize_key( (string) $request->get_param( 'level' ) ),
					'event'    => sanitize_key( (string) $request->get_param( 'event' ) ),
					'cart_id'  => (int) $request->get_param( 'cart_id' ),
					'page'     => (int) $request->get_param( 'page' ),
					'per_page' => (int) $request->get_param( 'per_page' ),
				)
			)
		);
	}

	/**
	 * Clear the whole log.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response
	 */
	public function clear(): WP_REST_Response {
		return $this->respond( array( 'cleared' => $this->logs->clear() ) );
	}
}
