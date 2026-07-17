<?php
/**
 * WordPress privacy-tools service provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\ServiceProvider;
use CartRebound\Data\CartDataCleaner;
use CartRebound\Privacy\PersonalDataEraser;
use CartRebound\Privacy\PersonalDataExporter;
use CartRebound\Privacy\PrivacyPolicy;

/**
 * Registers privacy policy, exporter, and eraser integrations with WordPress.
 *
 * @since 0.1.0
 */
final class PrivacyServiceProvider extends ServiceProvider {

	/**
	 * Register shared privacy services.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton( CartDataCleaner::class );
		$this->app->singleton( PersonalDataExporter::class );
		$this->app->singleton( PersonalDataEraser::class );
		$this->app->singleton( PrivacyPolicy::class );
	}

	/**
	 * Register WordPress privacy hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_init', array( $this->app->make( PrivacyPolicy::class ), 'register' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
	}

	/**
	 * Add Cart Rebound exporters to WordPress.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporters( $exporters ): array {
		if ( ! is_array( $exporters ) ) {
			$exporters = array();
		}

		$exporter = $this->app->make( PersonalDataExporter::class );

		$exporters['cart-rebound-carts'] = array(
			'exporter_friendly_name' => __( 'Cart Rebound cart sessions', 'cart-rebound' ),
			'callback'               => array( $exporter, 'export_carts' ),
		);

		$exporters['cart-rebound-logs'] = array(
			'exporter_friendly_name' => __( 'Cart Rebound activity logs', 'cart-rebound' ),
			'callback'               => array( $exporter, 'export_logs' ),
		);

		return $exporters;
	}

	/**
	 * Add Cart Rebound's eraser to WordPress.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_erasers( $erasers ): array {
		if ( ! is_array( $erasers ) ) {
			$erasers = array();
		}

		$erasers['cart-rebound'] = array(
			'eraser_friendly_name' => __( 'Cart Rebound cart sessions and activity logs', 'cart-rebound' ),
			'callback'             => array( $this->app->make( PersonalDataEraser::class ), 'erase' ),
		);

		return $erasers;
	}
}
