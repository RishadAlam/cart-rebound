<?php
/**
 * Development asset loader unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Tests\TestCase;

require_once dirname( __DIR__, 2 ) . '/development/assets.php';

/**
 * @covers ::cart_rebound_development_assets_enabled
 * @covers ::cart_rebound_dev_server_url
 */
final class DevelopmentAssetsTest extends TestCase {

	/**
	 * Isolated directories created by filesystem-backed tests.
	 *
	 * @var array<int, string>
	 */
	private $temporary_paths = array();

	/**
	 * Remove isolated marker files after each test.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		foreach ( $this->temporary_paths as $base_path ) {
			$hot_path = $base_path . '/public/hot';

			if ( is_file( $hot_path ) ) {
				unlink( $hot_path );
			}

			if ( is_dir( $base_path . '/public' ) ) {
				rmdir( $base_path . '/public' );
			}

			if ( is_dir( $base_path ) ) {
				rmdir( $base_path );
			}
		}

		parent::tear_down();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_hmr_is_disabled_in_a_production_environment(): void {
		define( 'CART_REBOUND_ENABLE_HMR', true );
		define( 'WP_DEBUG', true );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );

		$this->assertFalse( \cart_rebound_development_assets_enabled() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_hmr_accepts_only_the_expected_loopback_vite_url(): void {
		$base_path = $this->base_path_with_hot_url( 'http://localhost:5173' );

		$this->assertSame( 'http://localhost:5173', \cart_rebound_dev_server_url( $base_path ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_hmr_rejects_a_remote_javascript_origin(): void {
		$base_path = $this->base_path_with_hot_url( 'https://cdn.example.com:5173' );

		$this->assertNull( \cart_rebound_dev_server_url( $base_path ) );
	}

	/**
	 * Create an isolated plugin path with a public/hot marker.
	 *
	 * @param string $url Marker URL.
	 * @return string
	 */
	private function base_path_with_hot_url( string $url ): string {
		define( 'CART_REBOUND_ENABLE_HMR', true );
		define( 'WP_DEBUG', true );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'local' );
		Functions\when( 'untrailingslashit' )->alias(
			static function ( string $value ): string {
				return rtrim( $value, '/' );
			}
		);
		Functions\when( 'wp_json_file_decode' )->alias(
			static function ( string $path ): array {
				$decoded = json_decode( (string) file_get_contents( $path ), true );

				return is_array( $decoded ) ? $decoded : array();
			}
		);
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'esc_url_raw' )->returnArg();

		$base_path = sys_get_temp_dir() . '/cart-rebound-assets-' . uniqid( '', true );
		$hot_path  = $base_path . '/public/hot';
		$this->temporary_paths[] = $base_path;

		mkdir( dirname( $hot_path ), 0777, true );
		file_put_contents( $hot_path, (string) json_encode( array( 'url' => $url ) ) );

		return $base_path;
	}
}
