<?php

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Admin\PluginLinks;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Admin\PluginLinks
 */
final class PluginLinksTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		Functions\when( 'admin_url' )->alias(
			static function ( $path = '' ) {
				return 'https://shop.test/wp-admin/' . $path;
			}
		);
	}

	public function test_action_links_put_settings_first(): void {
		$links = ( new PluginLinks() )->action_links( array( 'deactivate' => '<a>Deactivate</a>' ) );

		$this->assertSame( array( 'settings', 'deactivate' ), array_keys( $links ) );
		$this->assertStringContainsString( 'admin.php?page=cart-rebound-settings', $links['settings'] );
	}

	public function test_row_meta_adds_docs_and_support_for_this_plugin_only(): void {
		$links = new PluginLinks();

		$own = $links->row_meta( array( 'Version 1.1.3' ), 'cart-rebound/cart-rebound.php' );

		$this->assertSame( array( 0, 'docs', 'support', 'requests', 'review' ), array_keys( $own ) );
		$this->assertStringContainsString( PluginLinks::DOCS_URL, $own['docs'] );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $own['support'] );

		$other = $links->row_meta( array( 'Version 2.0' ), 'akismet/akismet.php' );

		$this->assertSame( array( 'Version 2.0' ), $other );
	}

	public function test_every_admin_route_has_an_https_docs_page(): void {
		$docs = ( new PluginLinks() )->route_docs();

		$this->assertSame( array( '/', '/carts', '/templates', '/logs', '/settings' ), array_keys( $docs ) );

		foreach ( $docs as $url ) {
			$this->assertStringStartsWith( 'https://cart-rebound.com/docs/', $url );
		}
	}
}
