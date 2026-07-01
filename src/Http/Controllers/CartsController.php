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
use CartRebound\Cron\AbandonmentDetector;
use CartRebound\Data\CartRepository;
use CartRebound\Http\Requests\BulkActionRequest;
use CartRebound\Http\Requests\MarkRecoveredRequest;
use CartRebound\Http\Requests\UpdateStatusRequest;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Models\CartSession;
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
	 * Abandonment detector (reused for notifying manual abandons).
	 *
	 * @since 0.1.0
	 * @var AbandonmentDetector
	 */
	private $detector;

	/**
	 * Recovery mailer (for the on-demand "send email" action).
	 *
	 * @since 0.1.0
	 * @var RecoveryMailer
	 */
	private $mailer;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application         $app      Application instance.
	 * @param CartRepository      $carts    Cart repository.
	 * @param AbandonmentDetector $detector Abandonment detector.
	 * @param RecoveryMailer      $mailer   Recovery mailer.
	 */
	public function __construct( Application $app, CartRepository $carts, AbandonmentDetector $detector, RecoveryMailer $mailer ) {
		parent::__construct( $app );
		$this->carts    = $carts;
		$this->detector = $detector;
		$this->mailer   = $mailer;
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
			'orderby'  => sanitize_key( (string) $request->get_param( 'orderby' ) ),
			'order'    => sanitize_key( (string) $request->get_param( 'order' ) ),
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
	 * Manually change a cart's lifecycle status.
	 *
	 * A manual move to `abandoned` runs through the detector so the abandonment
	 * event fires and a recovery email is queued; every other status is a plain
	 * data transition.
	 *
	 * @since 0.1.0
	 *
	 * @param UpdateStatusRequest $request The validated request.
	 * @return WP_REST_Response
	 */
	public function update_status( UpdateStatusRequest $request ): WP_REST_Response {
		$data   = $request->validated();
		$status = (string) $data['status'];

		if ( ! in_array( $status, CartSession::STATUSES, true ) ) {
			return $this->respond( array( 'message' => __( 'Unknown status.', 'cart-rebound' ) ), 422 );
		}

		$updated = $this->apply_status( (int) $data['id'], $status );

		return $this->respond( array( 'updated' => $updated ) );
	}

	/**
	 * Run a bulk action (delete or set-status) over a set of cart ids.
	 *
	 * @since 0.1.0
	 *
	 * @param BulkActionRequest $request The validated request.
	 * @return WP_REST_Response
	 */
	public function bulk( BulkActionRequest $request ): WP_REST_Response {
		$action = (string) $request->validated()['action'];
		$ids    = $this->id_list( $request->param( 'ids' ) );

		if ( array() === $ids ) {
			return $this->respond( array( 'message' => __( 'No carts selected.', 'cart-rebound' ) ), 422 );
		}

		if ( 'delete' === $action ) {
			return $this->respond( array( 'affected' => $this->carts->delete_carts( $ids ) ) );
		}

		if ( 'status' === $action ) {
			$status = sanitize_key( (string) $request->param( 'status' ) );

			if ( ! in_array( $status, CartSession::STATUSES, true ) ) {
				return $this->respond( array( 'message' => __( 'Unknown status.', 'cart-rebound' ) ), 422 );
			}

			$affected = 0;

			foreach ( $ids as $id ) {
				if ( $this->apply_status( $id, $status ) ) {
					++$affected;
				}
			}

			return $this->respond( array( 'affected' => $affected ) );
		}

		return $this->respond( array( 'message' => __( 'Unsupported bulk action.', 'cart-rebound' ) ), 422 );
	}

	/**
	 * Send the recovery email for a cart immediately (admin action).
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function send_email( WP_REST_Request $request ): WP_REST_Response {
		$template_id = sanitize_text_field( (string) $request->get_param( 'template_id' ) );
		$sent        = $this->mailer->send_now( (int) $request->get_param( 'id' ), $template_id );

		return $this->respond( array( 'sent' => $sent ) );
	}

	/**
	 * Apply a status change, routing a notifying abandon through the detector.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $id     Cart id.
	 * @param string $status Target status (already validated).
	 * @return bool
	 */
	private function apply_status( int $id, string $status ): bool {
		if ( CartSession::STATUS_ABANDONED === $status ) {
			$cart = $this->carts->get_cart( $id );

			if ( ! is_array( $cart ) ) {
				return false;
			}

			// Already abandoned: a successful no-op (mirrors update_status()),
			// not a failure — so bulk counts and the single response stay honest.
			if ( CartSession::STATUS_ABANDONED === (string) $cart['status'] ) {
				return true;
			}

			// Fire the abandonment event + queue the recovery email only for a
			// cart that has NOT converted. Regressing a paid, order-linked cart
			// must never email the customer or inflate the abandoned counter, so
			// it falls through to a silent status change.
			if ( 0 === (int) $cart['order_id'] ) {
				return $this->detector->abandon( $id );
			}
		}

		return $this->carts->update_status( $id, $status );
	}

	/**
	 * Coerce a raw `ids` param into a de-duplicated list of positive ints.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $raw Raw ids parameter.
	 * @return array<int, int>
	 */
	private function id_list( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();

		foreach ( $raw as $value ) {
			$id = (int) $value;

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
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
