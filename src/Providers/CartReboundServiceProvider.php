<?php
/**
 * Core service provider: bindings + WooCommerce integration glue.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use CartRebound\Core\ServiceProvider;
use CartRebound\Cron\AbandonmentDetector;
use CartRebound\Cron\Janitor;
use CartRebound\Cron\Scheduler;
use CartRebound\Data\CartRepository;
use CartRebound\Events\EventDispatcher;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Recovery\OrderLinker;
use CartRebound\Recovery\RecoveryHandler;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Support\Settings;
use CartRebound\Tracking\CartTracker;
use CartRebound\Tracking\SessionManager;
use WP_User;

/**
 * Binds every plugin service as a singleton and wires the cross-cutting
 * WooCommerce concerns (HPOS compatibility, the active-plugin guard notice, and
 * merging a guest cart into the user identity on login).
 *
 * @since 0.1.0
 */
final class CartReboundServiceProvider extends ServiceProvider {

	/**
	 * Bind services into the container.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		$singletons = array(
			Settings::class,
			RecoveryLink::class,
			Scheduler::class,
			EventDispatcher::class,
			SessionManager::class,
			CartTracker::class,
			CartRepository::class,
			AbandonmentDetector::class,
			Janitor::class,
			RecoveryHandler::class,
			OrderLinker::class,
			RecoveryMailer::class,
		);

		foreach ( $singletons as $service ) {
			$this->app->singleton( $service );
		}
	}

	/**
	 * Wire WordPress + WooCommerce hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_woocommerce_notice' ) );
		add_action( 'wp_login', array( $this, 'merge_guest_cart_on_login' ), 10, 2 );
	}

	/**
	 * Declare High-Performance Order Storage compatibility.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) && defined( 'CART_REBOUND_FILE' ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', CART_REBOUND_FILE, true );
		}
	}

	/**
	 * Show an admin notice when WooCommerce is not active.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function maybe_render_woocommerce_notice(): void {
		if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Cart Rebound requires WooCommerce to be installed and active.', 'cart-rebound' );
		echo '</p></div>';
	}

	/**
	 * Merge a guest's tracked cart into the user identity on login.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $user_login The username.
	 * @param WP_User $user       The authenticated user.
	 * @return void
	 */
	public function merge_guest_cart_on_login( string $user_login, WP_User $user ): void {
		$this->app->make( SessionManager::class )->merge_guest_into_user( (int) $user->ID );
	}
}
