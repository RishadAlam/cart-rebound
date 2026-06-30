<?php
/**
 * Base REST controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use WP_REST_Response;

/**
 * Base class for REST controllers.
 *
 * Controllers are resolved from the container, so any dependency they type-hint
 * in their constructor is auto-wired.
 *
 * @since 0.1.0
 */
abstract class Controller {

	/**
	 * The application/container instance.
	 *
	 * @since 0.1.0
	 * @var Application
	 */
	protected $app;

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
	 * Build a JSON REST response.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $data   Response payload.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function respond( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}
}
