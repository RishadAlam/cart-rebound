<?php
/**
 * Suggested site privacy-policy content.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Privacy;

defined( 'ABSPATH' ) || exit;

use CartRebound\Support\Settings;

/**
 * Adds Cart Rebound's disclosure to WordPress's privacy-policy guide.
 *
 * @since 0.1.0
 */
final class PrivacyPolicy {

	/**
	 * Settings store.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register suggested privacy-policy text with WordPress.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$unrecovered_days = max( 1, (int) $this->settings->get( 'cleanup_days' ) );
		$converted_days   = max( 1, (int) $this->settings->get( 'converted_cleanup_days' ) );

		$content  = '<p>' . esc_html__( 'Cart Rebound stores cart contents, totals, coupon codes, a session identifier, and cart lifecycle timestamps for signed-in shoppers. When guest tracking is enabled, it also stores this information for logged-out shoppers. Email address, name, and phone number are stored when a shopper provides them during checkout.', 'cart-rebound' ) . '</p>';
		$content .= '<p>' . esc_html__( 'A first-party cart_rebound_ref cookie containing an unguessable, plugin-specific browser identifier keeps the cart session linked across visits. It is HTTP-only, uses SameSite=Lax, and expires after approximately 30 days.', 'cart-rebound' ) . '</p>';
		$content .= '<p>' . esc_html__( 'When recovery emails are enabled, Cart Rebound sends a recovery message through this site’s configured WordPress email system and records related activity in the plugin log. Cart Rebound does not itself send cart data to an external tracking service, although the site’s email delivery provider may process message data.', 'cart-rebound' ) . '</p>';
		$content .= '<p>' . sprintf(
			/* translators: 1: unrecovered cart retention days, 2: converted cart retention days. */
			esc_html__( 'Active and unrecovered cart records are retained for %1$d days, while recovered and completed cart records are retained for %2$d days. Associated activity logs are deleted with each cart record. Site administrators can change both retention periods in Cart Rebound settings.', 'cart-rebound' ),
			$unrecovered_days,
			$converted_days
		) . '</p>';
		$content .= '<p>' . esc_html__( 'Cart Rebound data can be exported or erased using the personal-data tools built into WordPress.', 'cart-rebound' ) . '</p>';

		wp_add_privacy_policy_content(
			esc_html__( 'Cart Rebound', 'cart-rebound' ),
			wp_kses_post( $content )
		);
	}
}
