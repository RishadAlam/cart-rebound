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
 * Enqueues Vite's live source entry in development and built assets otherwise.
 *
 * A running `pnpm dev` process creates public/hot, which points WordPress at
 * Vite's HMR client and TypeScript entry. Without that marker, the provider
 * reads the production manifest to resolve hashed file names. Both paths
 * inject runtime configuration as `window.CartRebound`.
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
	 * Vite HMR client handle.
	 *
	 * @var string
	 */
	private const VITE_CLIENT_HANDLE = 'cart-rebound-vite-client';

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

		$version    = $this->app->version();
		$base_file  = $this->app->base_path( 'cart-rebound.php' );
		$dev_server = $this->dev_server_url();

		if ( null !== $dev_server ) {
			$this->enqueue_development_assets( $dev_server, $route );

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

			wp_add_inline_script( self::HANDLE, $this->boot_data( $route ), 'before' );
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
	 * Enqueue Vite's module client and source entry for live HMR.
	 *
	 * @since 0.1.0
	 *
	 * @param string $dev_server Validated Vite origin.
	 * @param string $route      SPA route this page should open on.
	 * @return void
	 */
	private function enqueue_development_assets( string $dev_server, string $route ): void {
		add_filter( 'script_loader_tag', array( $this, 'module_script_tag' ), 10, 2 );

		wp_enqueue_script(
			self::VITE_CLIENT_HANDLE,
			$dev_server . '/@vite/client',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Vite only transforms its special HMR endpoint without a version query.
			true
		);
		wp_add_inline_script( self::VITE_CLIENT_HANDLE, $this->react_refresh_preamble( $dev_server ), 'before' );

		wp_enqueue_script(
			self::HANDLE,
			$dev_server . '/admin/main.tsx',
			array( self::VITE_CLIENT_HANDLE ),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Vite provides no-cache headers and HMR for this development-only source URL.
			true
		);

		wp_add_inline_script( self::HANDLE, $this->boot_data( $route ), 'before' );
	}

	/**
	 * Provide the React Refresh preamble Vite normally injects into index.html.
	 *
	 * @since 0.1.0
	 *
	 * @param string $dev_server Validated Vite origin.
	 * @return string
	 */
	private function react_refresh_preamble( string $dev_server ): string {
		$react_refresh_url = esc_js( $dev_server . '/@react-refresh' );

		return implode(
			"\n",
			array(
				"import RefreshRuntime from '{$react_refresh_url}';",
				'RefreshRuntime.injectIntoGlobalHook(window);',
				'window.$RefreshReg$ = () => {};',
				'window.$RefreshSig$ = () => (type) => type;',
				'window.__vite_plugin_react_preamble_installed__ = true;',
			)
		);
	}

	/**
	 * Mark Vite's development scripts as ES modules.
	 *
	 * @since 0.1.0
	 *
	 * @param string $tag    Generated script tag.
	 * @param string $handle Registered script handle.
	 * @return string
	 */
	public function module_script_tag( string $tag, string $handle ): string {
		if ( ! in_array( $handle, array( self::VITE_CLIENT_HANDLE, self::HANDLE ), true ) ) {
			return $tag;
		}

		if ( preg_match( '/\stype=(["\']).*?\1/', $tag ) ) {
			$module_tag = preg_replace( '/\stype=(["\']).*?\1/', ' type="module"', $tag, 1 );

			return is_string( $module_tag ) ? $module_tag : $tag;
		}

		return str_replace( '<script ', '<script type="module" ', $tag );
	}

	/**
	 * Read and validate the Vite server URL written by `pnpm dev`.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null Development origin, or null when Vite is not running.
	 */
	private function dev_server_url(): ?string {
		$path = $this->app->base_path( 'public/hot' );

		if ( ! is_readable( $path ) ) {
			return null;
		}

		$hot = wp_json_file_decode( $path, array( 'associative' => true ) );
		$url = is_array( $hot ) && isset( $hot['url'] ) && is_string( $hot['url'] )
			? untrailingslashit( trim( $hot['url'] ) )
			: '';

		$parts = '' !== $url ? wp_parse_url( $url ) : false;

		if (
			! is_array( $parts )
			|| ! isset( $parts['scheme'], $parts['host'] )
			|| ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| isset( $parts['query'] )
			|| isset( $parts['fragment'] )
			|| ( isset( $parts['path'] ) && ! in_array( $parts['path'], array( '', '/' ), true ) )
		) {
			return null;
		}

		return esc_url_raw( $url );
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
