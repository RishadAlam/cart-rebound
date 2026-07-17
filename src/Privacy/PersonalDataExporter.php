<?php
/**
 * WordPress personal-data exporters.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Privacy;

defined( 'ABSPATH' ) || exit;

use CartRebound\Models\CartSession;
use CartRebound\Models\LogEntry;

/**
 * Exports cart sessions and related activity logs by shopper email address.
 *
 * @since 0.1.0
 */
final class PersonalDataExporter {

	/**
	 * Rows returned per WordPress exporter request.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 20;

	/**
	 * Export cart-session data for an email address.
	 *
	 * Security credentials (the recovery token and checkout URL) are
	 * intentionally excluded from the portable export.
	 *
	 * @since 0.1.0
	 *
	 * @param string $email_address Email address being exported.
	 * @param int    $page          One-based batch number.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export_carts( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$email = $this->valid_email( $email_address );

		if ( '' === $email ) {
			return $this->empty_result();
		}

		$table   = ( new CartSession() )->get_table();
		$limit   = self::BATCH_SIZE + 1;
		$offset  = $this->offset( $page );
		$user_id = $this->user_id_for_email( $email );

		if ( $user_id > 0 ) {
			$sql      = 'SELECT * FROM %i WHERE (email = %s OR user_id = %d) ORDER BY id ASC LIMIT %d OFFSET %d';
			$bindings = array( $table, $email, $user_id, $limit, $offset );
		} else {
			$sql      = 'SELECT * FROM %i WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d';
			$bindings = array( $table, $email, $limit, $offset );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $bindings ), ARRAY_A );
		$rows = is_array( $rows ) ? $rows : array();

		$done = count( $rows ) <= self::BATCH_SIZE;
		$rows = array_slice( $rows, 0, self::BATCH_SIZE );

		return array(
			'data' => array_map( array( $this, 'export_cart' ), $rows ),
			'done' => $done,
		);
	}

	/**
	 * Export activity logs associated with carts for an email address.
	 *
	 * @since 0.1.0
	 *
	 * @param string $email_address Email address being exported.
	 * @param int    $page          One-based batch number.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export_logs( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$email = $this->valid_email( $email_address );

		if ( '' === $email ) {
			return $this->empty_result();
		}

		$logs_table     = ( new LogEntry() )->get_table();
		$sessions_table = ( new CartSession() )->get_table();
		$limit          = self::BATCH_SIZE + 1;
		$offset         = $this->offset( $page );
		$user_id        = $this->user_id_for_email( $email );

		if ( $user_id > 0 ) {
			$sql      = 'SELECT logs.* FROM %i AS logs INNER JOIN %i AS sessions ON logs.cart_id = sessions.id WHERE (sessions.email = %s OR sessions.user_id = %d) ORDER BY logs.id ASC LIMIT %d OFFSET %d';
			$bindings = array( $logs_table, $sessions_table, $email, $user_id, $limit, $offset );
		} else {
			$sql      = 'SELECT logs.* FROM %i AS logs INNER JOIN %i AS sessions ON logs.cart_id = sessions.id WHERE sessions.email = %s ORDER BY logs.id ASC LIMIT %d OFFSET %d';
			$bindings = array( $logs_table, $sessions_table, $email, $limit, $offset );
		}

		/*
		 * Both table identifiers and every value are bound through prepare().
		 * This is a paginated read over the plugin's custom tables.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is assembled from the two literal prepared templates above.
		$prepared_sql = $wpdb->prepare( $sql, $bindings );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom-table privacy export; query was prepared above.
		$rows = $wpdb->get_results( $prepared_sql, ARRAY_A );

		$rows = is_array( $rows ) ? $rows : array();
		$done = count( $rows ) <= self::BATCH_SIZE;
		$rows = array_slice( $rows, 0, self::BATCH_SIZE );

		return array(
			'data' => array_map( array( $this, 'export_log' ), $rows ),
			'done' => $done,
		);
	}

	/**
	 * Convert a cart row to WordPress's exporter item structure.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Cart-session row.
	 * @return array<string, mixed>
	 */
	private function export_cart( array $row ): array {
		$id = (int) ( $row['id'] ?? 0 );

		return array(
			'group_id'    => 'cart-rebound-carts',
			'group_label' => __( 'Cart Rebound cart sessions', 'cart-rebound' ),
			'item_id'     => 'cart-rebound-cart-' . $id,
			'data'        => array(
				$this->field( __( 'Cart ID', 'cart-rebound' ), $id ),
				$this->field( __( 'Session identifier', 'cart-rebound' ), (string) ( $row['session_key'] ?? '' ) ),
				$this->field( __( 'WordPress user ID', 'cart-rebound' ), (int) ( $row['user_id'] ?? 0 ) ),
				$this->field( __( 'Email address', 'cart-rebound' ), (string) ( $row['email'] ?? '' ) ),
				$this->field( __( 'First name', 'cart-rebound' ), (string) ( $row['first_name'] ?? '' ) ),
				$this->field( __( 'Last name', 'cart-rebound' ), (string) ( $row['last_name'] ?? '' ) ),
				$this->field( __( 'Phone number', 'cart-rebound' ), (string) ( $row['phone'] ?? '' ) ),
				$this->field( __( 'Cart contents', 'cart-rebound' ), (string) ( $row['cart_contents'] ?? '[]' ) ),
				$this->field( __( 'Cart total', 'cart-rebound' ), (string) ( $row['cart_total'] ?? '0' ) ),
				$this->field( __( 'Currency', 'cart-rebound' ), (string) ( $row['currency'] ?? '' ) ),
				$this->field( __( 'Item count', 'cart-rebound' ), (int) ( $row['items_count'] ?? 0 ) ),
				$this->field( __( 'Coupons', 'cart-rebound' ), (string) ( $row['coupons'] ?? '[]' ) ),
				$this->field( __( 'Status', 'cart-rebound' ), (string) ( $row['status'] ?? '' ) ),
				$this->field( __( 'Order ID', 'cart-rebound' ), (int) ( $row['order_id'] ?? 0 ) ),
				$this->field( __( 'Recovered amount', 'cart-rebound' ), (string) ( $row['recovered_amount'] ?? '0' ) ),
				$this->field( __( 'Created at', 'cart-rebound' ), (string) ( $row['created_at'] ?? '' ) ),
				$this->field( __( 'Last activity', 'cart-rebound' ), (string) ( $row['last_activity'] ?? '' ) ),
				$this->field( __( 'Abandoned at', 'cart-rebound' ), (string) ( $row['abandoned_at'] ?? '' ) ),
				$this->field( __( 'Recovered at', 'cart-rebound' ), (string) ( $row['recovered_at'] ?? '' ) ),
				$this->field( __( 'Completed at', 'cart-rebound' ), (string) ( $row['completed_at'] ?? '' ) ),
			),
		);
	}

	/**
	 * Convert a log row to WordPress's exporter item structure.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Activity-log row.
	 * @return array<string, mixed>
	 */
	private function export_log( array $row ): array {
		$id = (int) ( $row['id'] ?? 0 );

		return array(
			'group_id'    => 'cart-rebound-logs',
			'group_label' => __( 'Cart Rebound activity logs', 'cart-rebound' ),
			'item_id'     => 'cart-rebound-log-' . $id,
			'data'        => array(
				$this->field( __( 'Log ID', 'cart-rebound' ), $id ),
				$this->field( __( 'Cart ID', 'cart-rebound' ), (int) ( $row['cart_id'] ?? 0 ) ),
				$this->field( __( 'Created at', 'cart-rebound' ), (string) ( $row['created_at'] ?? '' ) ),
				$this->field( __( 'Level', 'cart-rebound' ), (string) ( $row['level'] ?? '' ) ),
				$this->field( __( 'Event', 'cart-rebound' ), (string) ( $row['event'] ?? '' ) ),
				$this->field( __( 'Message', 'cart-rebound' ), (string) ( $row['message'] ?? '' ) ),
			),
		);
	}

	/**
	 * Build one named exporter value.
	 *
	 * @since 0.1.0
	 *
	 * @param string     $name  Human-readable field name.
	 * @param int|string $value Exported value.
	 * @return array{name: string, value: int|string}
	 */
	private function field( string $name, $value ): array {
		return array(
			'name'  => $name,
			'value' => $value,
		);
	}

	/**
	 * Sanitise and validate an exporter email address.
	 *
	 * @since 0.1.0
	 *
	 * @param string $email_address Raw email address.
	 * @return string
	 */
	private function valid_email( string $email_address ): string {
		$email = sanitize_email( $email_address );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Resolve the WordPress account currently using an email address.
	 *
	 * Matching by user id as well as email ensures historical carts remain
	 * exportable after a registered shopper changes their account email.
	 *
	 * @since 0.1.0
	 *
	 * @param string $email Validated email address.
	 * @return int WordPress user id, or zero for a guest/unmatched address.
	 */
	private function user_id_for_email( string $email ): int {
		$user = get_user_by( 'email', $email );

		return is_object( $user ) ? absint( $user->ID ) : 0;
	}

	/**
	 * Convert a page number to a row offset.
	 *
	 * @since 0.1.0
	 *
	 * @param int $page One-based page number.
	 * @return int
	 */
	private function offset( int $page ): int {
		return ( max( 1, $page ) - 1 ) * self::BATCH_SIZE;
	}

	/**
	 * Return a completed exporter response with no data.
	 *
	 * @since 0.1.0
	 *
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	private function empty_result(): array {
		return array(
			'data' => array(),
			'done' => true,
		);
	}
}
