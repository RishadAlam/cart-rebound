<?php
/**
 * Migration: create the activity log table.
 *
 * Stores one row per notable plugin event (cart abandoned, recovered, recovery
 * email sent, cleanup) so the admin can see what the plugin has been doing.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

use CartRebound\Database\Migration;

defined( 'ABSPATH' ) || exit;

return new class() extends Migration {

	/**
	 * Create the logs table.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function up(): void {
		$table           = $this->prefix() . 'logs';
		$charset_collate = $this->charset_collate();

		$this->run_schema(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				level varchar(20) NOT NULL DEFAULT 'info',
				event varchar(50) NOT NULL DEFAULT '',
				message text NULL,
				cart_id bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY idx_created_at (created_at),
				KEY idx_level (level),
				KEY idx_event (event),
				KEY idx_cart_id (cart_id)
			) {$charset_collate};"
		);
	}

	/**
	 * Drop the logs table.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function down(): void {
		$this->drop_table( $this->prefix() . 'logs' );
	}
};
