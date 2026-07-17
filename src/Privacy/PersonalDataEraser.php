<?php
/**
 * WordPress personal-data eraser.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Privacy;

defined( 'ABSPATH' ) || exit;

use CartRebound\Data\CartDataCleaner;
use CartRebound\Models\CartSession;

/**
 * Erases cart sessions and their associated logs by shopper email address.
 *
 * @since 0.1.0
 */
final class PersonalDataEraser {

	/**
	 * Rows removed per WordPress eraser request.
	 *
	 * Because rows are deleted during each call, every batch starts at offset
	 * zero; this prevents records from being skipped as the result set shrinks.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 20;

	/**
	 * Privacy-aware deletion service.
	 *
	 * @since 0.1.0
	 * @var CartDataCleaner
	 */
	private $cleaner;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param CartDataCleaner $cleaner Privacy-aware cart deletion service.
	 */
	public function __construct( CartDataCleaner $cleaner ) {
		$this->cleaner = $cleaner;
	}

	/**
	 * Erase one batch of cart data for an email address.
	 *
	 * @since 0.1.0
	 *
	 * @param string $email_address Email address being erased.
	 * @param int    $page          One-based batch number (accepted for the WordPress callback contract).
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		global $wpdb;

		unset( $page );

		$email = sanitize_email( $email_address );

		if ( ! is_email( $email ) ) {
			return $this->response( false, false, array(), true );
		}

		$table   = ( new CartSession() )->get_table();
		$user_id = $this->user_id_for_email( $email );

		if ( $user_id > 0 ) {
			$sql      = 'SELECT id FROM %i WHERE (email = %s OR user_id = %d) ORDER BY id ASC LIMIT %d';
			$bindings = array( $table, $email, $user_id, self::BATCH_SIZE );
		} else {
			$sql      = 'SELECT id FROM %i WHERE email = %s ORDER BY id ASC LIMIT %d';
			$bindings = array( $table, $email, self::BATCH_SIZE );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $bindings ), ARRAY_A );
		$rows = is_array( $rows ) ? $rows : array();

		$ids = array_map(
			static function ( array $row ): int {
				return (int) ( $row['id'] ?? 0 );
			},
			$rows
		);

		if ( array() === $ids ) {
			return $this->response( false, false, array(), true );
		}

		$result  = $this->cleaner->delete( $ids );
		$removed = $result['logs_deleted'] > 0 || $result['sessions_deleted'] > 0;

		if ( ! $result['complete'] ) {
			return $this->response(
				$removed,
				true,
				array( __( 'Some Cart Rebound data could not be erased because a database operation failed.', 'cart-rebound' ) ),
				true
			);
		}

		$remaining = $this->count_remaining( $email, $user_id );

		return $this->response( $removed, false, array(), 0 === $remaining );
	}

	/**
	 * Count matching rows after an erasure batch.
	 *
	 * @since 0.1.0
	 *
	 * @param string $email   Validated email address.
	 * @param int    $user_id Matching WordPress user id, if any.
	 * @return int
	 */
	private function count_remaining( string $email, int $user_id ): int {
		global $wpdb;

		$table = ( new CartSession() )->get_table();

		if ( $user_id > 0 ) {
			$sql      = 'SELECT COUNT(*) FROM %i WHERE (email = %s OR user_id = %d)';
			$bindings = array( $table, $email, $user_id );
		} else {
			$sql      = 'SELECT COUNT(*) FROM %i WHERE email = %s';
			$bindings = array( $table, $email );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $bindings ) );
	}

	/**
	 * Resolve the WordPress account currently using an email address.
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
	 * Build the standard WordPress eraser response.
	 *
	 * @since 0.1.0
	 *
	 * @param bool               $removed  Whether data was removed.
	 * @param bool               $retained Whether data had to be retained.
	 * @param array<int, string> $messages User-facing eraser messages.
	 * @param bool               $done     Whether all batches are complete.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	private function response( bool $removed, bool $retained, array $messages, bool $done ): array {
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}
}
