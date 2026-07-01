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
	 * Captured top-level page hook suffix.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Map of every registered page hook suffix → the SPA route it opens.
	 *
	 * @since 0.1.0
	 * @var array<string, string>
	 */
	private $page_hooks = array();

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
		$capability = 'manage_woocommerce';

		$hook = add_menu_page(
			__( 'Cart Rebound', 'cart-rebound' ),
			__( 'Cart Rebound', 'cart-rebound' ),
			$capability,
			self::SLUG,
			array( $this->dashboard, 'render' ),
			'dashicons-cart',
			58
		);

		$this->page_hook  = is_string( $hook ) ? $hook : '';
		$this->page_hooks = array();

		if ( '' !== $this->page_hook ) {
			$this->page_hooks[ $this->page_hook ] = '/';
		}

		/*
		 * Every submenu item mounts the same single-page app; the route is
		 * seeded into the hash router at load (see AssetServiceProvider). The
		 * first item reuses the parent slug, relabelling the auto-created entry
		 * from "Cart Rebound" to "Dashboard".
		 */
		$submenus = array(
			self::SLUG                => array( __( 'Dashboard', 'cart-rebound' ), '/' ),
			self::SLUG . '-carts'     => array( __( 'Carts', 'cart-rebound' ), '/carts' ),
			self::SLUG . '-templates' => array( __( 'Templates', 'cart-rebound' ), '/templates' ),
			self::SLUG . '-settings'  => array( __( 'Settings', 'cart-rebound' ), '/settings' ),
		);

		foreach ( $submenus as $slug => $meta ) {
			$sub_hook = add_submenu_page(
				self::SLUG,
				$meta[0],
				$meta[0],
				$capability,
				$slug,
				array( $this->dashboard, 'render' )
			);

			if ( is_string( $sub_hook ) && '' !== $sub_hook ) {
				$this->page_hooks[ $sub_hook ] = $meta[1];
			}
		}
	}

	/**
	 * Get the captured top-level page hook suffix.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_page_hook(): string {
		return $this->page_hook;
	}

	/**
	 * Get every page hook suffix the plugin owns (top level + submenus).
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, string>
	 */
	public function get_page_hooks(): array {
		return array_keys( $this->page_hooks );
	}

	/**
	 * Resolve the SPA route a given page hook should open on.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook Page hook suffix.
	 * @return string The route (defaults to the dashboard root).
	 */
	public function route_for_hook( string $hook ): string {
		return $this->page_hooks[ $hook ] ?? '/';
	}
}
