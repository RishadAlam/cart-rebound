<?php
/**
 * Add-on state controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Extend\Registry;
use WP_REST_Response;

/**
 * Reports which add-ons are running and what they are delivering.
 *
 * The same state is localised into the admin bundle at page load, so the app
 * renders the right thing on first paint without a request. This endpoint
 * exists for the moment after that — activating a license has to unlock the
 * screens without a page reload.
 *
 * @since 1.1.0
 */
final class AddonsController extends Controller {

	/**
	 * Get the current add-on state.
	 *
	 * @since 1.1.0
	 *
	 * @param Registry $registry Add-on registry.
	 * @return WP_REST_Response
	 */
	public function index( Registry $registry ): WP_REST_Response {
		return $this->respond( $registry->state() );
	}
}
