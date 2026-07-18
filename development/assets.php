<?php
/**
 * Local Vite asset loader.
 *
 * Composer loads this file only through autoload-dev. The production archive
 * excludes the development directory and uses compiled assets exclusively.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'cart_rebound_use_compiled_admin_assets', 'cart_rebound_load_development_assets', 10, 3 );
}

/**
 * Replace compiled admin assets with the local Vite entry when HMR is enabled.
 *
 * @param bool   $use_compiled_assets Current compiled-asset decision.
 * @param string $base_path           Plugin base path.
 * @param string $boot_data           Sanitized JavaScript bootstrap assignment.
 * @return bool Whether the provider should continue with compiled assets.
 */
function cart_rebound_load_development_assets(
	bool $use_compiled_assets,
	string $base_path,
	string $boot_data
): bool {
	if ( ! $use_compiled_assets ) {
		return false;
	}

	return ! cart_rebound_enqueue_development_assets( $base_path, $boot_data );
}

/**
 * Enqueue the local Vite client and source entry when HMR is explicitly on.
 *
 * @param string $base_path Plugin base path.
 * @param string $boot_data Sanitized JavaScript bootstrap assignment.
 * @return bool Whether development assets were enqueued.
 */
function cart_rebound_enqueue_development_assets( string $base_path, string $boot_data ): bool {
	$dev_server = cart_rebound_dev_server_url( $base_path );

	if ( null === $dev_server ) {
		return false;
	}

	add_filter( 'script_loader_tag', 'cart_rebound_module_script_tag', 10, 2 );

	wp_enqueue_script(
		'cart-rebound-vite-client',
		$dev_server . '/@vite/client',
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Vite transforms this development endpoint only without a query string.
		true
	);

	wp_enqueue_script(
		'cart-rebound-admin',
		$dev_server . '/admin/main.tsx',
		array( 'cart-rebound-vite-client' ),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Vite controls cache headers for this development-only source URL.
		true
	);
	wp_add_inline_script( 'cart-rebound-admin', $boot_data, 'before' );

	return true;
}

/**
 * Resolve the exact loopback Vite URL written by pnpm dev.
 *
 * HMR requires an explicit constant, debug mode, and a non-production
 * WordPress environment. No other origin, scheme, or port is accepted.
 *
 * @param string $base_path Plugin base path.
 * @return string|null Validated server origin, or null when HMR is unavailable.
 */
function cart_rebound_dev_server_url( string $base_path ): ?string {
	if ( ! cart_rebound_development_assets_enabled() ) {
		return null;
	}

	$path = rtrim( $base_path, '/\\' ) . '/public/hot';

	if ( ! is_readable( $path ) ) {
		return null;
	}

	$hot = wp_json_file_decode( $path, array( 'associative' => true ) );
	$url = is_array( $hot ) && isset( $hot['url'] ) && is_string( $hot['url'] )
		? untrailingslashit( trim( $hot['url'] ) )
		: '';

	if ( 'http://localhost:5173' !== $url ) {
		return null;
	}

	return $url;
}

/**
 * Determine whether local Vite development assets may be loaded.
 *
 * @return bool
 */
function cart_rebound_development_assets_enabled(): bool {
	if ( ! defined( 'CART_REBOUND_ENABLE_HMR' ) || true !== constant( 'CART_REBOUND_ENABLE_HMR' ) ) {
		return false;
	}

	if ( ! defined( 'WP_DEBUG' ) || true !== constant( 'WP_DEBUG' ) ) {
		return false;
	}

	return in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
}

/**
 * Mark Vite scripts as modules and inject the React Refresh preamble.
 *
 * @param string $tag    Generated script tag.
 * @param string $handle Registered script handle.
 * @return string
 */
function cart_rebound_module_script_tag( string $tag, string $handle ): string {
	$handles = array( 'cart-rebound-vite-client', 'cart-rebound-admin' );

	if ( ! in_array( $handle, $handles, true ) ) {
		return $tag;
	}

	if ( preg_match( '/\stype=(["\']).*?\1/', $tag ) ) {
		$module_tag = preg_replace( '/\stype=(["\']).*?\1/', ' type="module"', $tag, 1 );
		$tag        = is_string( $module_tag ) ? $module_tag : $tag;
	} else {
		$tag = str_replace( '<script ', '<script type="module" ', $tag );
	}

	if ( 'cart-rebound-vite-client' !== $handle ) {
		return $tag;
	}

	return '<script type="module" id="cart-rebound-react-refresh-preamble">'
		. cart_rebound_react_refresh_preamble( 'http://localhost:5173' )
		. '</script>'
		. "\n"
		. $tag;
}

/**
 * Provide the React Refresh preamble Vite normally injects into index.html.
 *
 * @param string $dev_server Validated loopback Vite origin.
 * @return string
 */
function cart_rebound_react_refresh_preamble( string $dev_server ): string {
	$refresh_url = wp_json_encode( $dev_server . '/@react-refresh' );
	$refresh_url = false === $refresh_url ? '""' : $refresh_url;

	return implode(
		"\n",
		array(
			"import RefreshRuntime from {$refresh_url};",
			'RefreshRuntime.injectIntoGlobalHook(window);',
			'window.$RefreshReg$ = () => {};',
			'window.$RefreshSig$ = () => (type) => type;',
			'window.__vite_plugin_react_preamble_installed__ = true;',
		)
	);
}
