<?php
/**
 * Carts admin controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use CartRebound\Data\CartRepository;
use CartRebound\Http\Requests\MarkRecoveredRequest;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Admin read/manage endpoints for tracked carts.
 *
 * @since 0.1.0
 */
final class CartsController extends Controller {

	/**
	 * Cart repository.
	 *
	 * @since 0.1.0
	 * @var CartRepository
	 */
	private $carts;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application    $app   Application instance.
	 * @param CartRepository $carts Cart repository.
	 */
	public function __construct( Application $app, CartRepository $carts ) {
		parent::__construct( $app );
		$this->carts = $carts;
	}

	/**
	 * List carts with optional filters + paging.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			'status'   => $this->status_arg( $request->get_param( 'status' ) ),
			'email'    => sanitize_text_field( (string) $request->get_param( 'email' ) ),
			'page'     => (int) $request->get_param( 'page' ),
			'per_page' => (int) $request->get_param( 'per_page' ),
		);

		return $this->respond( $this->carts->get_carts( $args ) );
	}

	/**
	 * Show a single cart.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$cart = $this->carts->get_cart( (int) $request->get_param( 'id' ) );

		if ( null === $cart ) {
			return $this->respond( array( 'message' => __( 'Cart not found.', 'cart-rebound' ) ), 404 );
		}

		return $this->respond( $cart );
	}

	/**
	 * Delete a cart.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$deleted = $this->carts->delete_cart( (int) $request->get_param( 'id' ) );

		return $this->respond( array( 'deleted' => $deleted ) );
	}

	/**
	 * Manually mark a cart recovered against an order.
	 *
	 * @since 0.1.0
	 *
	 * @param MarkRecoveredRequest $request The validated request.
	 * @return WP_REST_Response
	 */
	public function mark_recovered( MarkRecoveredRequest $request ): WP_REST_Response {
		$data    = $request->validated();
		$updated = $this->carts->mark_recovered( (int) $data['id'], (int) $data['order_id'] );

		return $this->respond( array( 'updated' => $updated ) );
	}

	/**
	 * Normalise the status filter to a string or list of strings.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $raw Raw status parameter.
	 * @return string|array<int, string>
	 */
	private function status_arg( $raw ) {
		if ( is_array( $raw ) ) {
			return array_map( 'sanitize_text_field', array_map( 'strval', $raw ) );
		}

		return sanitize_text_field( (string) $raw );
	}
}
