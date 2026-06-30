<?php
/**
 * Container unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use CartRebound\Core\BindingResolutionException;
use CartRebound\Core\Container;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Core\Container
 */
final class ContainerTest extends TestCase {

	private Container $container;

	protected function set_up(): void {
		parent::set_up();
		$this->container = new Container();
	}

	public function test_make_auto_wires_constructor_dependencies(): void {
		$consumer = $this->container->make( ContainerConsumer::class );

		$this->assertInstanceOf( ContainerConsumer::class, $consumer );
		$this->assertInstanceOf( ContainerService::class, $consumer->service );
	}

	public function test_singleton_returns_the_same_instance(): void {
		$this->container->singleton( ContainerService::class );

		$this->assertSame(
			$this->container->make( ContainerService::class ),
			$this->container->make( ContainerService::class )
		);
	}

	public function test_unbound_class_resolves_to_fresh_instances(): void {
		$this->assertNotSame(
			$this->container->make( ContainerService::class ),
			$this->container->make( ContainerService::class )
		);
	}

	public function test_instance_is_returned_as_is(): void {
		$service = new ContainerService();
		$this->container->instance( ContainerService::class, $service );

		$this->assertSame( $service, $this->container->make( ContainerService::class ) );
	}

	public function test_closure_binding_is_invoked(): void {
		$this->container->bind(
			'config',
			static function (): array {
				return array( 'key' => 'value' );
			}
		);

		$this->assertSame( array( 'key' => 'value' ), $this->container->make( 'config' ) );
	}

	public function test_interface_binding_resolves_to_concrete(): void {
		$this->container->bind( ContainerContract::class, ContainerImplementation::class );

		$this->assertInstanceOf(
			ContainerImplementation::class,
			$this->container->make( ContainerContract::class )
		);
	}

	public function test_has_reflects_registered_bindings(): void {
		$this->assertFalse( $this->container->has( ContainerContract::class ) );

		$this->container->bind( ContainerContract::class, ContainerImplementation::class );

		$this->assertTrue( $this->container->has( ContainerContract::class ) );
	}

	public function test_named_parameters_override_primitives(): void {
		$object = $this->container->make( ContainerNeedsPrimitive::class, array( 'name' => 'cart-rebound' ) );

		$this->assertSame( 'cart-rebound', $object->name );
	}

	public function test_unresolvable_primitive_throws(): void {
		$this->expectException( BindingResolutionException::class );

		$this->container->make( ContainerNeedsPrimitive::class );
	}

	public function test_nonexistent_class_throws(): void {
		$this->expectException( BindingResolutionException::class );

		$this->container->make( 'CartRebound\\Tests\\Unit\\DoesNotExist' );
	}

	public function test_resolved_tracks_resolution_state(): void {
		$this->assertFalse( $this->container->resolved( ContainerService::class ) );

		$this->container->make( ContainerService::class );

		$this->assertTrue( $this->container->resolved( ContainerService::class ) );
	}

	public function test_circular_dependency_throws_instead_of_recursing(): void {
		$this->expectException( BindingResolutionException::class );
		$this->expectExceptionMessageMatches( '/Circular dependency/' );

		$this->container->make( ContainerCircularA::class );
	}

	public function test_self_resolving_closure_binding_throws_instead_of_recursing(): void {
		$this->container->bind(
			'loop',
			static function ( $container ) {
				return $container->make( 'loop' );
			}
		);

		$this->expectException( BindingResolutionException::class );
		$this->expectExceptionMessageMatches( '/Circular dependency/' );

		$this->container->make( 'loop' );
	}
}

// phpcs:disable Squiz.Commenting, Generic.Files.OneObjectStructurePerFile -- lightweight test fixtures.

interface ContainerContract {
}

class ContainerImplementation implements ContainerContract {
}

class ContainerService {
}

class ContainerConsumer {

	public ContainerService $service;

	public function __construct( ContainerService $service ) {
		$this->service = $service;
	}
}

class ContainerNeedsPrimitive {

	public string $name;

	public function __construct( string $name ) {
		$this->name = $name;
	}
}

class ContainerCircularA {

	public function __construct( ContainerCircularB $b ) {
	}
}

class ContainerCircularB {

	public function __construct( ContainerCircularA $a ) {
	}
}
