<?php
/**
 * Migration: create the cart sessions table.
 *
 * Stores one row per tracked cart (logged-in or guest), its snapshot, lifecycle
 * status, recovery token, and order/recovery attribution. Schema work runs once
 * on activation via the Migrator — never per request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

use CartRebound\Database\Migration;

defined( 'ABSPATH' ) || exit;

return new class() extends Migration {

	/**
	 * Create the cart sessions table.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function up(): void {
		$table           = $this->prefix() . 'sessions';
		$charset_collate = $this->charset_collate();

		$this->run_schema(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				session_key varchar(64) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				email varchar(100) NOT NULL DEFAULT '',
				first_name varchar(100) NOT NULL DEFAULT '',
				last_name varchar(100) NOT NULL DEFAULT '',
				phone varchar(40) NOT NULL DEFAULT '',
				cart_contents longtext NULL,
				cart_total decimal(19,4) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT '',
				items_count int(11) NOT NULL DEFAULT 0,
				coupons text NULL,
				checkout_url text NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				recovery_token char(32) NOT NULL DEFAULT '',
				abandonment_notified tinyint(1) NOT NULL DEFAULT 0,
				email_sent tinyint(1) NOT NULL DEFAULT 0,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				recovered_amount decimal(19,4) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				last_activity datetime NOT NULL,
				abandoned_at datetime NULL DEFAULT NULL,
				recovered_at datetime NULL DEFAULT NULL,
				completed_at datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uq_session_key (session_key),
				KEY idx_email (email),
				KEY idx_user_id (user_id),
				KEY idx_status_activity (status, last_activity),
				KEY idx_order_id (order_id),
				KEY idx_recovery_token (recovery_token)
			) {$charset_collate};"
		);
	}

	/**
	 * Drop the cart sessions table.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function down(): void {
		$this->drop_table( $this->prefix() . 'sessions' );
	}
};
