<?php
/**
 * Asset service provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Admin\Menu;
use CartRebound\Core\ServiceProvider;

/**
 * Enqueues the compiled admin assets resolved through Vite's manifest.
 *
 * @since 0.1.0
 */
final class AssetServiceProvider extends ServiceProvider {

	/**
	 * Script/style handle.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const HANDLE = 'cart-rebound-admin';

	/**
	 * Boot the provider.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue the admin bundle, but only on the plugin's own page.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$menu = $this->app->make( Menu::class );

		if ( ! in_array( $hook_suffix, $menu->get_page_hooks(), true ) ) {
			return;
		}

		$route = $menu->route_for_hook( $hook_suffix );

		// Make the WordPress media library available so the template editor can
		// open the media frame to insert images.
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		$version   = $this->app->version();
		$base_file = $this->app->base_path( 'cart-rebound.php' );
		$boot_data = $this->boot_data( $route );

		/**
		 * Filter whether the compiled admin assets should be loaded.
		 *
		 * Development tooling can enqueue its own local assets and return false.
		 * The callback is supplied only through Composer's development autoloader
		 * and is absent from release archives.
		 *
		 * @since 0.1.0
		 *
		 * @param bool   $use_compiled_assets Whether to enqueue compiled assets.
		 * @param string $base_path           Plugin base path.
		 * @param string $boot_data           Sanitized JavaScript bootstrap data.
		 */
		$use_compiled_assets = (bool) apply_filters(
			'cart_rebound_use_compiled_admin_assets',
			true,
			$this->app->base_path(),
			$boot_data
		);

		if ( ! $use_compiled_assets ) {
			return;
		}

		$entry = $this->entry();

		if ( null === $entry ) {
			return;
		}

		if ( isset( $entry['file'] ) && is_string( $entry['file'] ) ) {
			wp_enqueue_script(
				self::HANDLE,
				plugins_url( 'public/build/' . $entry['file'], $base_file ),
				array( 'react', 'react-dom', 'wp-i18n' ),
				$version,
				true
			);

			wp_add_inline_script( self::HANDLE, $boot_data, 'before' );
			wp_set_script_translations( self::HANDLE, 'cart-rebound' );
		}

		if ( isset( $entry['css'] ) && is_array( $entry['css'] ) ) {
			foreach ( $entry['css'] as $index => $css ) {
				if ( ! is_string( $css ) ) {
					continue;
				}

				wp_enqueue_style(
					self::HANDLE . '-' . $index,
					plugins_url( 'public/build/' . $css, $base_file ),
					array(),
					$version
				);
			}
		}
	}

	/**
	 * Locate the entry chunk in the Vite manifest.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>|null
	 */
	private function entry(): ?array {
		$path = $this->app->base_path( 'public/build/.vite/manifest.json' );

		if ( ! is_readable( $path ) ) {
			return null;
		}

		$manifest = wp_json_file_decode( $path, array( 'associative' => true ) );

		if ( ! is_array( $manifest ) ) {
			return null;
		}

		$entry = null;

		foreach ( $manifest as $item ) {
			if ( is_array( $item ) && isset( $item['isEntry'] ) && true === $item['isEntry'] ) {
				$entry = $item;
				break;
			}
		}

		if ( null === $entry ) {
			return null;
		}

		$css = ( isset( $entry['css'] ) && is_array( $entry['css'] ) ) ? $entry['css'] : array();

		// With cssCodeSplit disabled Vite emits the stylesheet as a standalone
		// manifest asset instead of nesting it under the JavaScript entry.
		foreach ( $manifest as $item ) {
			$file = is_array( $item ) && isset( $item['file'] ) && is_string( $item['file'] )
				? $item['file']
				: '';

			if ( '.css' === substr( $file, -4 ) ) {
				$css[] = $file;
			}
		}

		$entry['css'] = array_values( array_unique( $css ) );

		return $entry;
	}

	/**
	 * Build the `window.CartRebound` bootstrap script.
	 *
	 * Also seeds the hash router with the submenu's route before the app module
	 * evaluates, so each WordPress submenu item deep-links straight to its tab.
	 * An existing hash (in-app navigation) is respected.
	 *
	 * @since 0.1.0
	 *
	 * @param string $route The SPA route this page should open on.
	 * @return string
	 */
	private function boot_data( string $route ): string {
		$data = array(
			'apiUrl'       => esc_url_raw( rest_url( 'cart-rebound/v1' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'initialRoute' => $route,
			'currentUser'  => array(
				'id'   => get_current_user_id(),
				'caps' => $this->current_user_caps(),
			),
		);

		$json = wp_json_encode( $data );
		$js   = 'window.CartRebound = ' . ( false === $json ? '{}' : $json ) . ';';

		if ( '/' !== $route ) {
			$hash = wp_json_encode( '#' . $route );
			$js  .= 'if(!window.location.hash||"#/"===window.location.hash){window.location.hash=' . ( false === $hash ? '"#/"' : $hash ) . ';}';
		}

		return $js;
	}

	/**
	 * Collect the current user's granted capabilities.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, string>
	 */
	private function current_user_caps(): array {
		$user = wp_get_current_user();
		$caps = array();

		foreach ( $user->allcaps as $capability => $granted ) {
			if ( $granted ) {
				$caps[] = (string) $capability;
			}
		}

		return $caps;
	}
}
