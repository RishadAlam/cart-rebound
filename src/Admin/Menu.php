<?php
/**
 * Admin menu registration.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Admin;

defined( 'ABSPATH' ) || exit;

use CartRebound\Admin\Pages\DashboardPage;

/**
 * Registers the plugin's top-level admin menu page.
 *
 * @since 0.1.0
 */
final class Menu {

	/**
	 * Admin page slug.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const SLUG = 'cart-rebound';

	/**
	 * The dashboard page renderer.
	 *
	 * @since 0.1.0
	 * @var DashboardPage
	 */
	private $dashboard;

	/**
	 * Captured page hook suffix.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param DashboardPage $dashboard The dashboard page renderer.
	 */
	public function __construct( DashboardPage $dashboard ) {
		$this->dashboard = $dashboard;
	}

	/**
	 * Register the admin menu page.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		$hook = add_menu_page(
			__( 'Cart Rebound', 'cart-rebound' ),
			__( 'Cart Rebound', 'cart-rebound' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this->dashboard, 'render' ),
			'dashicons-screenoptions',
			58
		);

		$this->page_hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Get the captured page hook suffix.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_page_hook(): string {
		return $this->page_hook;
	}
}
