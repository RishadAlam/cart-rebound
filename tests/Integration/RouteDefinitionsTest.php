<?php
/**
 * Route-definition security tests.
 *
 * Loads the plugin's actual route files through the Route facade and asserts the
 * nonce / capability gating each route declares.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Integration;

use Brain\Monkey\Functions;
use CartRebound\Core\Application;
use CartRebound\Http\Kernel;
use CartRebound\Http\Router;
use CartRebound\Support\Facades\Facade;
use CartRebound\Tests\TestCase;
use WP_Error;
use WP_REST_Request;

/**
 * @coversNothing
 */
final class RouteDefinitionsTest extends TestCase {

	/**
	 * Load a route file and capture every registered route's args.
	 *
	 * @param string $relative Route file path relative to the plugin root.
	 * @return array<string, array<string, mixed>>
	 */
	private function load_routes( string $relative ): array {
		$app    = Application::get_instance( dirname( __DIR__ ) );
		$kernel = new Kernel( $app );
		$router = new Router( $app, $kernel );

		$app->instance( Router::class, $router );
		Facade::set_facade_application( $app );

		$captured = array();
		Functions\when( 'register_rest_route' )->alias(
			static function ( $namespace, $route, $args ) use ( &$captured ): bool {
				unset( $namespace );
				$captured[ $route ] = $args;

				return true;
			}
		);

		require dirname( __DIR__, 2 ) . '/' . $relative;
		$router->register_routes();

		return $captured;
	}

	public function test_capture_route_allows_with_valid_nonce(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$captured = $this->load_routes( 'routes/api.php' );

		$this->assertArrayHasKey( 'capture', $captured );
		$this->assertSame( 'POST', $captured['capture']['methods'] );

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid' );

		$this->assertTrue( $captured['capture']['permission_callback']( $request ) );
	}

	public function test_capture_route_rejects_missing_nonce(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$captured = $this->load_routes( 'routes/api.php' );
		$result   = $captured['capture']['permission_callback']( new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function test_admin_carts_route_requires_capability(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( false );

		$captured = $this->load_routes( 'routes/admin.php' );

		$this->assertArrayHasKey( 'carts', $captured );

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid' );

		$result = $captured['carts']['permission_callback']( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_admin_settings_route_allows_with_nonce_and_capability(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );

		$captured = $this->load_routes( 'routes/admin.php' );

		$this->assertArrayHasKey( 'settings', $captured );

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid' );

		$this->assertTrue( $captured['settings']['permission_callback']( $request ) );
	}
}
