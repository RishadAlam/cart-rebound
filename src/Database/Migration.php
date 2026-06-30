<?php
/**
 * Abstract migration.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for schema migrations.
 *
 * Subclasses implement {@see up()} and {@see down()} and use the helpers here
 * to build dbDelta-friendly schema.
 *
 * @since 0.1.0
 */
abstract class Migration {

	/**
	 * Apply the migration.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	abstract public function up(): void;

	/**
	 * Reverse the migration.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	abstract public function down(): void;

	/**
	 * Get the table-name prefix (WordPress prefix + cart_rebound_).
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	protected function prefix(): string {
		global $wpdb;

		return $wpdb->prefix . 'cart_rebound_';
	}

	/**
	 * Get the database charset/collate clause.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	protected function charset_collate(): string {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}

	/**
	 * Run a CREATE TABLE statement through dbDelta().
	 *
	 * @since 0.1.0
	 *
	 * @param string $sql The CREATE TABLE statement.
	 * @return void
	 */
	protected function run_schema( string $sql ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}

	/**
	 * Drop a table (used by down()).
	 *
	 * @since 0.1.0
	 *
	 * @param string $table Fully-qualified table name.
	 * @return void
	 */
	protected function drop_table( string $table ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time schema teardown on uninstall; table name bound via %i.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}
