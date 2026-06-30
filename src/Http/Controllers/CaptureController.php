<?php
/**
 * Guest identity capture controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use CartRebound\Http\Requests\CaptureEmailRequest;
use CartRebound\Tracking\CartTracker;
use WP_REST_Response;

/**
 * Receives the email/name/phone a guest enters at checkout and back-fills the
 * tracked cart row. Nonce-gated only — guests have no capability, and the
 * payload is a non-sensitive cart-identity snapshot (mirrors the Store API path).
 *
 * @since 0.1.0
 */
final class CaptureController extends Controller {

	/**
	 * Cart tracker.
	 *
	 * @since 0.1.0
	 * @var CartTracker
	 */
	private $tracker;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app     Application instance.
	 * @param CartTracker $tracker Cart tracker.
	 */
	public function __construct( Application $app, CartTracker $tracker ) {
		parent::__construct( $app );
		$this->tracker = $tracker;
	}

	/**
	 * Back-fill identity onto the current cart row.
	 *
	 * @since 0.1.0
	 *
	 * @param CaptureEmailRequest $request The validated request.
	 * @return WP_REST_Response
	 */
	public function store( CaptureEmailRequest $request ): WP_REST_Response {
		$this->tracker->capture_identity( $request->validated() );

		return $this->respond( array( 'captured' => true ) );
	}
}
