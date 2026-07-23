<?php
/**
 * Dashboard stats controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use CartRebound\Data\CartRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Returns aggregate dashboard statistics.
 *
 * @since 0.1.0
 */
final class StatsController extends Controller {

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
	 * Return the stats payload.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response
	 */
	public function index(): WP_REST_Response {
		return $this->respond( $this->carts->get_stats() );
	}

	/**
	 * Return the daily recoverable/recovered revenue series for the chart.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function timeseries( WP_REST_Request $request ): WP_REST_Response {
		$days = (int) $request->get_param( 'days' );

		return $this->respond(
			array( 'items' => $this->carts->get_timeseries( $days > 0 ? $days : 30 ) )
		);
	}

	/**
	 * Return the per-product abandonment/recovery report.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function products( WP_REST_Request $request ): WP_REST_Response {
		$days  = (int) $request->get_param( 'days' );
		$limit = (int) $request->get_param( 'limit' );

		return $this->respond(
			array(
				'items' => $this->carts->get_product_report(
					$days > 0 ? $days : 30,
					$limit > 0 ? $limit : 5
				),
			)
		);
	}
}
