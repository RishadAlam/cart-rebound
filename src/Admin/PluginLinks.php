<?php
/**
 * Links to the plugin's documentation and support.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Settings, documentation and support links to the Plugins screen and maps
 * each admin page to its documentation article.
 *
 * @since 1.1.4
 */
final class PluginLinks {

	/**
	 * Official site.
	 *
	 * @var string
	 */
	public const SITE_URL = 'https://cart-rebound.com/';

	/**
	 * Documentation entry point for a new store.
	 *
	 * @var string
	 */
	public const DOCS_URL = 'https://cart-rebound.com/docs/quick-start';

	/**
	 * WordPress.org support forum.
	 *
	 * @var string
	 */
	public const SUPPORT_URL = 'https://wordpress.org/support/plugin/cart-rebound/';

	/**
	 * Where bugs and feature requests are filed.
	 *
	 * @var string
	 */
	public const ISSUES_URL = 'https://github.com/RishadAlam/cart-rebound/issues';

	/**
	 * Documentation article for each admin route.
	 *
	 * @var array<string, string>
	 */
	public const ROUTE_DOCS = array(
		'/'          => 'https://cart-rebound.com/docs/dashboard',
		'/carts'     => 'https://cart-rebound.com/docs/manage-carts',
		'/templates' => 'https://cart-rebound.com/docs/email-templates',
		'/logs'      => 'https://cart-rebound.com/docs/activity-log',
		'/settings'  => 'https://cart-rebound.com/docs/settings',
	);

	/**
	 * Hook the Plugins screen filters.
	 *
	 * @since 1.1.4
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'plugin_action_links_' . $this->basename(), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Put a Settings link before Deactivate.
	 *
	 * @since 1.1.4
	 *
	 * @param array<int|string, string> $links Existing action links.
	 * @return array<int|string, string>
	 */
	public function action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . Menu::SLUG . '-settings' ) ),
			esc_html__( 'Settings', 'cart-rebound' )
		);

		return array_merge( array( 'settings' => $settings ), $links );
	}

	/**
	 * Add documentation and support links under the plugin description.
	 *
	 * @since 1.1.4
	 *
	 * @param array<int|string, string> $links Existing row meta links.
	 * @param string                    $file  Plugin basename of the current row.
	 * @return array<int|string, string>
	 */
	public function row_meta( array $links, string $file ): array {
		if ( $this->basename() !== $file ) {
			return $links;
		}

		$extra = array(
			'docs'     => array( self::DOCS_URL, __( 'Documentation', 'cart-rebound' ) ),
			'support'  => array( self::SUPPORT_URL, __( 'Support', 'cart-rebound' ) ),
			'requests' => array( self::ISSUES_URL, __( 'Feature requests', 'cart-rebound' ) ),
			'review'   => array( ReviewPrompt::REVIEW_URL, __( 'Rate Cart Rebound', 'cart-rebound' ) ),
		);

		foreach ( $extra as $key => $link ) {
			$links[ $key ] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $link[0] ),
				esc_html( $link[1] )
			);
		}

		return $links;
	}

	/**
	 * Documentation URL for each admin route.
	 *
	 * @since 1.1.4
	 *
	 * @return array<string, string>
	 */
	public function route_docs(): array {
		return self::ROUTE_DOCS;
	}

	/**
	 * The plugin's basename, as WordPress keys the Plugins screen rows.
	 *
	 * @since 1.1.4
	 *
	 * @return string
	 */
	private function basename(): string {
		return defined( 'CART_REBOUND_FILE' ) ? plugin_basename( CART_REBOUND_FILE ) : 'cart-rebound/cart-rebound.php';
	}
}
