<?php
/**
 * Container contract.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal PSR-11 style container contract.
 *
 * @since 0.1.0
 */
interface ContainerInterface {

	/**
	 * Resolve an entry from the container by its identifier.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Identifier (usually a fully-qualified class name).
	 * @return mixed
	 */
	public function get( string $id );

	/**
	 * Determine whether the container can resolve the given identifier.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Identifier (usually a fully-qualified class name).
	 * @return bool
	 */
	public function has( string $id ): bool;
}
