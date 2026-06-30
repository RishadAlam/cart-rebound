<?php
/**
 * Routing integration tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Integration;

use Brain\Monkey\Functions;
use CartRebound\Core\Application;
use CartRebound\Http\Controllers\Controller;
use CartRebound\Http\Controllers\PingController;
use CartRebound\Http\Kernel;
use CartRebound\Http\Requests\FormRequest;
use CartRebound\Http\Router;
use CartRebound\Tests\TestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exercises the Router → Kernel → Controller pipeline end to end.
 */
final class RoutingTest extends TestCase {

	private function make_router(): Router {
		$router = $this->router();

		$router->get( 'ping', array( PingController::class, 'index' ) )->middleware( 'nonce' );

		return $router;
	}

	private function router(): Router {
		$app    = Application::get_instance( __DIR__ );
		$kernel = new Kernel( $app );

		return new Router( $app, $kernel );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function capture_routes( Router $router ): array {
		$captured = array();

		Functions\when( 'register_rest_route' )->alias(
			static function ( $namespace, $route, $args ) use ( &$captured ): bool {
				unset( $namespace );
				$captured[ $route ] = $args;

				return true;
			}
		);

		$router->register_routes();

		return $captured;
	}

	public function test_ping_route_registers_and_dispatches(): void {
		$captured = $this->capture_routes( $this->make_router() );

		$this->assertArrayHasKey( 'ping', $captured );
		$this->assertSame( 'GET', $captured['ping']['methods'] );
		$this->assertIsCallable( $captured['ping']['callback'] );
		$this->assertIsCallable( $captured['ping']['permission_callback'] );

		$response = $captured['ping']['callback']( new WP_REST_Request() );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame(
			array(
				'pong'    => true,
				'version' => '0.1.0',
			),
			$response->get_data()
		);
	}

	public function test_permission_callback_rejects_missing_nonce(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$captured = $this->capture_routes( $this->make_router() );

		$result = $captured['ping']['permission_callback']( new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( array( 'status' => 401 ), $result->get_error_data() );
	}

	public function test_permission_callback_allows_valid_nonce(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$captured = $this->capture_routes( $this->make_router() );

		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'valid-nonce' );

		$this->assertTrue( $captured['ping']['permission_callback']( $request ) );
	}

	public function test_route_without_middleware_is_denied_by_default(): void {
		$router = $this->router();
		$router->get( 'open', static fn() => null );

		$captured = $this->capture_routes( $router );
		$result   = $captured['open']['permission_callback']( new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'cart_rebound_no_authorization', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}

	public function test_public_route_is_allowed(): void {
		$router = $this->router();
		$router->get( 'open', static fn() => null )->public();

		$captured = $this->capture_routes( $router );

		$this->assertTrue( $captured['open']['permission_callback']( new WP_REST_Request() ) );
	}

	public function test_capability_middleware_denies_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$router = $this->router();
		$router->post( 'settings', static fn() => null )->middleware( 'can:manage_options' );

		$captured = $this->capture_routes( $router );
		$result   = $captured['settings']['permission_callback']( new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}

	public function test_capability_middleware_allows_with_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$router = $this->router();
		$router->post( 'settings', static fn() => null )->middleware( 'can:manage_options' );

		$captured = $this->capture_routes( $router );

		$this->assertTrue( $captured['settings']['permission_callback']( new WP_REST_Request() ) );
	}

	public function test_form_request_validation_failure_short_circuits_dispatch(): void {
		$router = $this->router();
		$router->post( 'things', array( ThingController::class, 'store' ) )->public();

		$captured = $this->capture_routes( $router );
		$result   = $captured['things']['callback']( new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'cart_rebound_validation_failed', $result->get_error_code() );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	public function test_form_request_passes_validated_input_to_controller(): void {
		Functions\when( 'sanitize_text_field' )->returnArg();

		$router = $this->router();
		$router->post( 'things', array( ThingController::class, 'store' ) )->public();

		$captured = $this->capture_routes( $router );

		$request = new WP_REST_Request();
		$request->set_param( 'title', 'Hello' );

		$response = $captured['things']['callback']( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( array( 'title' => 'Hello' ), $response->get_data() );
	}

	public function test_kernel_denies_empty_middleware_stack(): void {
		$app    = Application::get_instance( __DIR__ );
		$kernel = new Kernel( $app );

		$result = $kernel->run( array(), new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'cart_rebound_no_authorization', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}

	public function test_kernel_allows_public_alias(): void {
		$app    = Application::get_instance( __DIR__ );
		$kernel = new Kernel( $app );

		$this->assertTrue( $kernel->run( array( 'public' ), new WP_REST_Request() ) );
	}

	public function test_controller_parameter_is_resolved_from_container(): void {
		$router = $this->router();
		$router->get( 'svc', array( ServiceController::class, 'index' ) )->public();

		$captured = $this->capture_routes( $router );
		$response = $captured['svc']['callback']( new WP_REST_Request() );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( array( 'ok' => true ), $response->get_data() );
	}
}

// phpcs:disable -- lightweight test fixtures.

final class StoreThingRequest extends FormRequest {

	public function rules(): array {
		return array( 'title' => 'required|string' );
	}
}

final class ThingController extends Controller {

	public function store( StoreThingRequest $request ): WP_REST_Response {
		return new WP_REST_Response( $request->validated(), 201 );
	}
}

final class GreetService {

	public function ok(): bool {
		return true;
	}
}

final class ServiceController extends Controller {

	public function index( GreetService $service ): WP_REST_Response {
		return new WP_REST_Response( array( 'ok' => $service->ok() ), 200 );
	}
}
