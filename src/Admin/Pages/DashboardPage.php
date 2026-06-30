<?php
/**
 * Dashboard admin page.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;

/**
 * Renders the admin page that hosts the React application.
 *
 * @since 0.1.0
 */
final class DashboardPage {

	/**
	 * The application instance.
	 *
	 * @since 0.1.0
	 * @var Application
	 */
	private $app;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app The application instance.
	 */
	public function __construct( Application $app ) {
		$this->app = $app;
	}

	/**
	 * Render the page (the React mount point).
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cart-rebound' ) );
		}

		require $this->app->base_path( 'resources/views/admin/root.php' );
	}
}
