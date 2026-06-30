<?php
/**
 * Capability check middleware.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Middleware;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;

/**
 * Ensures the current user has a required capability.
 *
 * Created from the `can:<capability>` route middleware alias.
 *
 * @since 0.1.0
 */
final class RequireCapability implements Middleware {

	/**
	 * Required capability.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $capability;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string $capability Capability the current user must have.
	 */
	public function __construct( string $capability ) {
		$this->capability = $capability;
	}

	/**
	 * Handle the request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The current REST request.
	 * @return true|WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		if ( ! current_user_can( $this->capability ) ) {
			return new WP_Error(
				'cart_rebound_forbidden',
				__( 'You do not have permission to perform this action.', 'cart-rebound' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
