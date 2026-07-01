<?php
/**
 * Coupons lookup controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use WC_Coupon;
use WP_REST_Response;

/**
 * Lists published WooCommerce coupons so the admin can drop a coupon code into
 * the recovery-email template (the `{coupon_code}` merge tag).
 *
 * @since 0.1.0
 */
final class CouponsController extends Controller {

	/**
	 * How many coupons to list.
	 *
	 * @var int
	 */
	private const LIMIT = 100;

	/**
	 * Return published coupons as `{ code, description, amount, type }` options.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response
	 */
	public function index(): WP_REST_Response {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return $this->respond( array( 'items' => array() ) );
		}

		$posts = get_posts(
			array(
				'post_type'        => 'shop_coupon',
				'post_status'      => 'publish',
				'numberposts'      => self::LIMIT,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$items = array();

		foreach ( $posts as $post ) {
			$code = (string) $post->post_title;

			if ( '' === $code ) {
				continue;
			}

			$coupon = new WC_Coupon( $code );

			$items[] = array(
				'code'        => $code,
				'description' => (string) $post->post_excerpt,
				'amount'      => (float) $coupon->get_amount(),
				'type'        => (string) $coupon->get_discount_type(),
			);
		}

		return $this->respond( array( 'items' => $items ) );
	}
}
