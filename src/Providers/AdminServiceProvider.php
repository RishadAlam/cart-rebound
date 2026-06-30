<?php
/**
 * Admin service provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Admin\Menu;
use CartRebound\Core\ServiceProvider;

/**
 * Registers the admin menu that hosts the React dashboard.
 *
 * @since 0.1.0
 */
final class AdminServiceProvider extends ServiceProvider {

	/**
	 * Register bindings.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton( Menu::class );
	}

	/**
	 * Boot the provider.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action(
			'admin_menu',
			function (): void {
				$this->app->make( Menu::class )->register();
			}
		);
	}
}
