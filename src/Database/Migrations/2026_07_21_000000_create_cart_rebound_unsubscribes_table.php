<?php
/**
 * Migration: create the email suppression (unsubscribe) table.
 *
 * One row per address that opted out of recovery emails. Kept in its own table
 * (keyed by email) rather than a flag on the cart row so a suppression survives
 * cart cleanup and applies to every future cart for that address.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

use CartRebound\Database\Migration;

defined( 'ABSPATH' ) || exit;

return new class() extends Migration {

	/**
	 * Create the unsubscribes table.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function up(): void {
		$table           = $this->prefix() . 'unsubscribes';
		$charset_collate = $this->charset_collate();

		$this->run_schema(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(100) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uq_email (email)
			) {$charset_collate};"
		);
	}

	/**
	 * Drop the unsubscribes table.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function down(): void {
		$this->drop_table( $this->prefix() . 'unsubscribes' );
	}
};
