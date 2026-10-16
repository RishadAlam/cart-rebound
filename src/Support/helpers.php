<?php
/**
 * Global helper functions.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;

if ( ! function_exists( 'cart_rebound' ) ) {
	/**
	 * Resolve a service from the container, or return the application itself.
	 *
	 * @since 0.1.0
	 *
	 * @template THelper of object
	 *
	 * @param class-string<THelper>|string|null $id         Identifier to resolve, or null for the app.
	 * @param array<string, mixed>              $parameters Named overrides for constructor parameters.
	 * @return ( $id is class-string<THelper> ? THelper : ( $id is null ? Application : mixed ) )
	 */
	function cart_rebound( ?string $id = null, array $parameters = array() ) {
		$app = Application::get_instance();

		if ( null === $id ) {
			return $app;
		}

		return $app->make( $id, $parameters );
	}
}
