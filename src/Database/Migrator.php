<?php
/**
 * Migration runner.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Database;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;

/**
 * Discovers, runs and reverses migrations.
 *
 * Each file in src/Database/Migrations returns an anonymous {@see Migration}
 * instance. Applied migrations are tracked in the `cart_rebound_migrations` option
 * (an array of file basenames), so re-activating the plugin only runs new ones.
 *
 * @since 0.1.0
 */
final class Migrator {

	private const OPTION = 'cart_rebound_migrations';

	/**
	 * The application instance.
	 *
	 * @since 0.1.0
	 * @var Application
	 */
	private $app;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app The application instance.
	 */
	public function __construct( Application $app ) {
		$this->app = $app;
	}

	/**
	 * Run all pending migrations.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function run(): void {
		$ran = $this->ran_migrations();

		foreach ( $this->migrations() as $name => $migration ) {
			if ( in_array( $name, $ran, true ) ) {
				continue;
			}

			$migration->up();
			$ran[] = $name;

			// Persist after each migration so a later failure cannot orphan
			// the migrations that already succeeded in this batch.
			update_option( self::OPTION, array_values( array_unique( $ran ) ) );
		}
	}

	/**
	 * Reverse every applied migration (newest first) and forget them.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function rollback(): void {
		$ran = $this->ran_migrations();

		foreach ( array_reverse( $this->migrations(), true ) as $name => $migration ) {
			if ( in_array( $name, $ran, true ) ) {
				$migration->down();
			}
		}

		delete_option( self::OPTION );
	}

	/**
	 * Get the list of applied migration names.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, string>
	 */
	private function ran_migrations(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		return array_values( array_filter( $stored, 'is_string' ) );
	}

	/**
	 * Discover migrations keyed (and sorted) by file basename.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, Migration>
	 */
	private function migrations(): array {
		$migrations = array();
		$files      = glob( $this->app->base_path( 'src/Database/Migrations' ) . '/*.php' );

		if ( false === $files ) {
			return $migrations;
		}

		sort( $files );

		foreach ( $files as $file ) {
			$migration = require $file;

			if ( $migration instanceof Migration ) {
				$migrations[ basename( $file, '.php' ) ] = $migration;
			}
		}

		return $migrations;
	}
}
