<?php
/**
 * Service provider contract.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for service providers participating in the two-phase lifecycle.
 *
 * @since 0.1.0
 */
interface ServiceProviderInterface {

	/**
	 * Register bindings into the container.
	 *
	 * Runs for every provider before any provider is booted.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void;

	/**
	 * Boot the provider once all providers have been registered.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void;
}
