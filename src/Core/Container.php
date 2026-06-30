<?php
/**
 * Dependency-injection container with reflection auto-wiring.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Core;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Contracts\ContainerInterface;
use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

/**
 * A small Laravel-inspired service container.
 *
 * Supports explicit bindings, shared singletons, pre-built instances and
 * constructor auto-wiring via reflection.
 *
 * @since 0.1.0
 */
class Container implements ContainerInterface {

	/**
	 * Registered bindings keyed by identifier.
	 *
	 * @since 0.1.0
	 * @var array<string, array{concrete: Closure|string, shared: bool}>
	 */
	protected $bindings = array();

	/**
	 * Shared resolved instances keyed by identifier.
	 *
	 * @since 0.1.0
	 * @var array<string, mixed>
	 */
	protected $instances = array();

	/**
	 * Identifiers that have been resolved at least once.
	 *
	 * @since 0.1.0
	 * @var array<string, bool>
	 */
	protected $resolved = array();

	/**
	 * Concrete classes currently being built, used to detect circular dependencies.
	 *
	 * @since 0.1.0
	 * @var array<string, bool>
	 */
	protected $build_stack = array();

	/**
	 * Register a binding with the container.
	 *
	 * @since 0.1.0
	 *
	 * @param string              $id       Identifier to bind.
	 * @param Closure|string|null $concrete Concrete implementation or factory closure.
	 * @param bool                $shared   Whether the binding should be shared (singleton).
	 * @return void
	 */
	public function bind( string $id, $concrete = null, bool $shared = false ): void {
		$concrete = $concrete ?? $id;

		$this->bindings[ $id ] = array(
			'concrete' => $concrete,
			'shared'   => $shared,
		);
	}

	/**
	 * Register a shared (singleton) binding.
	 *
	 * @since 0.1.0
	 *
	 * @param string              $id       Identifier to bind.
	 * @param Closure|string|null $concrete Concrete implementation or factory closure.
	 * @return void
	 */
	public function singleton( string $id, $concrete = null ): void {
		$this->bind( $id, $concrete, true );
	}

	/**
	 * Register an existing instance as shared in the container.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id       Identifier to bind.
	 * @param object $instance The instance to store.
	 * @return object
	 */
	public function instance( string $id, object $instance ): object {
		$this->instances[ $id ] = $instance;
		$this->resolved[ $id ]  = true;

		return $instance;
	}

	/**
	 * Resolve the given identifier from the container.
	 *
	 * @since 0.1.0
	 *
	 * @template TMake of object
	 *
	 * @param class-string<TMake>|string $id         Identifier to resolve.
	 * @param array<string, mixed>       $parameters Named overrides for constructor parameters.
	 * @return ( $id is class-string<TMake> ? TMake : mixed )
	 *
	 * @throws BindingResolutionException When the binding cannot be resolved.
	 */
	public function make( string $id, array $parameters = array() ) {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( isset( $this->build_stack[ $id ] ) ) {
			throw new BindingResolutionException(
				esc_html(
					sprintf(
						'Circular dependency detected while resolving [%s] (chain: %s).',
						$id,
						implode( ' -> ', array_keys( $this->build_stack ) ) . ' -> ' . $id
					)
				)
			);
		}

		$this->build_stack[ $id ] = true;

		try {
			$concrete = $this->get_concrete( $id );

			if ( $concrete instanceof Closure || $concrete === $id ) {
				$object = $this->build( $concrete, $parameters );
			} else {
				$object = $this->make( $concrete, $parameters );
			}

			if ( $this->is_shared( $id ) ) {
				$this->instances[ $id ] = $object;
			}

			$this->resolved[ $id ] = true;

			return $object;
		} finally {
			unset( $this->build_stack[ $id ] );
		}
	}

	/**
	 * Resolve an entry from the container by its identifier.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Identifier to resolve.
	 * @return mixed
	 *
	 * @throws BindingResolutionException When the binding cannot be resolved.
	 */
	public function get( string $id ) {
		return $this->make( $id );
	}

	/**
	 * Determine whether the container has a binding or instance for the identifier.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Identifier to check.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Determine whether the identifier has been resolved.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Identifier to check.
	 * @return bool
	 */
	public function resolved( string $id ): bool {
		return isset( $this->resolved[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Resolve the concrete type for a given identifier.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Identifier to resolve.
	 * @return Closure|string
	 */
	protected function get_concrete( string $id ) {
		if ( isset( $this->bindings[ $id ] ) ) {
			return $this->bindings[ $id ]['concrete'];
		}

		return $id;
	}

	/**
	 * Instantiate a concrete type, recursively resolving dependencies.
	 *
	 * @since 0.1.0
	 *
	 * @param Closure|string       $concrete   Concrete factory or class name.
	 * @param array<string, mixed> $parameters Named overrides for constructor parameters.
	 * @return mixed
	 *
	 * @throws BindingResolutionException When the class cannot be instantiated.
	 */
	protected function build( $concrete, array $parameters = array() ) {
		if ( $concrete instanceof Closure ) {
			return $concrete( $this, $parameters );
		}

		if ( ! class_exists( $concrete ) ) {
			throw new BindingResolutionException(
				esc_html( sprintf( 'Target class [%s] does not exist.', $concrete ) )
			);
		}

		$reflector = new ReflectionClass( $concrete );

		if ( ! $reflector->isInstantiable() ) {
			throw new BindingResolutionException(
				esc_html( sprintf( 'Target [%s] is not instantiable.', $concrete ) )
			);
		}

		$constructor = $reflector->getConstructor();

		if ( null === $constructor ) {
			return new $concrete();
		}

		$dependencies = $this->resolve_dependencies( $constructor->getParameters(), $parameters );

		return $reflector->newInstanceArgs( $dependencies );
	}

	/**
	 * Resolve a list of constructor parameters.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, ReflectionParameter> $reflection_parameters Constructor parameters.
	 * @param array<string, mixed>            $parameters            Named overrides.
	 * @return array<int, mixed>
	 *
	 * @throws BindingResolutionException When a dependency cannot be resolved.
	 */
	protected function resolve_dependencies( array $reflection_parameters, array $parameters ): array {
		$results = array();

		foreach ( $reflection_parameters as $parameter ) {
			$name = $parameter->getName();

			if ( array_key_exists( $name, $parameters ) ) {
				$results[] = $parameters[ $name ];
				continue;
			}

			$class_name = $this->class_name_from_type( $parameter->getType() );

			$results[] = null === $class_name ? $this->resolve_primitive( $parameter ) : $this->make( $class_name );
		}

		return $results;
	}

	/**
	 * Extract a resolvable class name from a reflection type, if any.
	 *
	 * @since 0.1.0
	 *
	 * @param ReflectionType|null $type Reflection type.
	 * @return class-string|null
	 */
	protected function class_name_from_type( ?ReflectionType $type ): ?string {
		if ( ! $type instanceof ReflectionNamedType || $type->isBuiltin() ) {
			return null;
		}

		$name = $type->getName();

		if ( 'self' === $name || 'static' === $name || 'parent' === $name ) {
			return null;
		}

		if ( ! class_exists( $name ) && ! interface_exists( $name ) ) {
			return null;
		}

		return $name;
	}

	/**
	 * Resolve a primitive (non-class) constructor parameter.
	 *
	 * @since 0.1.0
	 *
	 * @param ReflectionParameter $parameter The parameter to resolve.
	 * @return mixed
	 *
	 * @throws BindingResolutionException When the primitive has no resolvable value.
	 */
	protected function resolve_primitive( ReflectionParameter $parameter ) {
		if ( $parameter->isDefaultValueAvailable() ) {
			return $parameter->getDefaultValue();
		}

		if ( $parameter->allowsNull() ) {
			return null;
		}

		$declaring_class = $parameter->getDeclaringClass();

		throw new BindingResolutionException(
			esc_html(
				sprintf(
					'Unresolvable dependency [$%s] in class [%s].',
					$parameter->getName(),
					null !== $declaring_class ? $declaring_class->getName() : 'unknown'
				)
			)
		);
	}

	/**
	 * Determine whether a binding should be treated as shared.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Identifier to check.
	 * @return bool
	 */
	protected function is_shared( string $id ): bool {
		if ( isset( $this->instances[ $id ] ) ) {
			return true;
		}

		return isset( $this->bindings[ $id ] ) && true === $this->bindings[ $id ]['shared'];
	}
}
