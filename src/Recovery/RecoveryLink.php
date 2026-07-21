<?php
/**
 * Recovery link builder.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Recovery;

defined( 'ABSPATH' ) || exit;

/**
 * Builds tokenised cart-recovery URLs.
 *
 * The URL carries only the unguessable recovery token (never the session key),
 * pointing at the WooCommerce cart page where {@see RecoveryHandler} intercepts it.
 *
 * @since 0.1.0
 */
final class RecoveryLink {

	/**
	 * Query var that flags a recovery request.
	 *
	 * @var string
	 */
	public const QUERY_FLAG = 'cart_rebound_recover';

	/**
	 * Query var carrying the recovery token.
	 *
	 * @var string
	 */
	public const QUERY_TOKEN = 'cart_rebound_token';

	/**
	 * Query var that flags an unsubscribe request.
	 *
	 * @var string
	 */
	public const QUERY_UNSUBSCRIBE = 'cart_rebound_unsubscribe';

	/**
	 * Build the recovery URL for a token.
	 *
	 * @since 0.1.0
	 *
	 * @param string $token The row's recovery token.
	 * @return string
	 */
	public function url( string $token ): string {
		$base = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );

		return add_query_arg(
			array(
				self::QUERY_FLAG  => '1',
				self::QUERY_TOKEN => rawurlencode( $token ),
			),
			$base
		);
	}

	/**
	 * Build the one-click unsubscribe URL for a token.
	 *
	 * @since 0.1.0
	 *
	 * @param string $token The row's recovery token.
	 * @return string
	 */
	public function unsubscribe_url( string $token ): string {
		return add_query_arg(
			array(
				self::QUERY_UNSUBSCRIBE => '1',
				self::QUERY_TOKEN       => rawurlencode( $token ),
			),
			home_url( '/' )
		);
	}
}
