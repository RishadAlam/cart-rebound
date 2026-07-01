<?php
/**
 * Orders lookup controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use WC_Order;
use WP_REST_Response;

/**
 * Lists recent WooCommerce orders so the admin can pick one when manually
 * reconciling a cart to an order (the "mark recovered" order picker).
 *
 * @since 0.1.0
 */
final class OrdersController extends Controller {

	/**
	 * How many recent orders to offer in the picker.
	 *
	 * @var int
	 */
	private const LIMIT = 50;

	/**
	 * Return the most recent orders as lightweight picker options.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response
	 */
	public function index(): WP_REST_Response {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $this->respond( array( 'items' => array() ) );
		}

		$orders = wc_get_orders(
			array(
				'limit'   => self::LIMIT,
				'orderby' => 'date',
				'order'   => 'DESC',
				'type'    => 'shop_order',
			)
		);

		$items = array();

		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$created = $order->get_date_created();

			$items[] = array(
				'id'       => $order->get_id(),
				'number'   => (string) $order->get_order_number(),
				'email'    => (string) $order->get_billing_email(),
				'total'    => (float) $order->get_total(),
				'currency' => (string) $order->get_currency(),
				'status'   => (string) $order->get_status(),
				'date'     => null !== $created ? $created->date( 'Y-m-d H:i' ) : '',
			);
		}

		return $this->respond( array( 'items' => $items ) );
	}
}
