<?php
/**
 * Privacy-aware cart deletion service.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Data;

defined( 'ABSPATH' ) || exit;

use CartRebound\Cron\Scheduler;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Models\CartSession;
use CartRebound\Models\LogEntry;

/**
 * Deletes cart sessions together with their associated activity-log entries.
 *
 * Logs are removed first so a failed log deletion never leaves an orphaned
 * message containing personal data after its cart row has disappeared.
 *
 * @since 0.1.0
 */
final class CartDataCleaner {

	/**
	 * Job scheduler.
	 *
	 * @since 0.1.0
	 * @var Scheduler
	 */
	private $scheduler;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Scheduler $scheduler Job scheduler.
	 */
	public function __construct( Scheduler $scheduler ) {
		$this->scheduler = $scheduler;
	}

	/**
	 * Delete the requested cart rows and their associated logs.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, int> $ids Cart-session ids.
	 * @return array{complete: bool, logs_deleted: int, sessions_deleted: int}
	 */
	public function delete( array $ids ): array {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $ids ),
					static function ( int $id ): bool {
						return $id > 0;
					}
				)
			)
		);

		if ( array() === $ids ) {
			return array(
				'complete'         => true,
				'logs_deleted'     => 0,
				'sessions_deleted' => 0,
			);
		}

		foreach ( $ids as $id ) {
			$this->scheduler->clear_with_args( RecoveryMailer::HOOK, array( $id ) );
		}

		$expected_logs = LogEntry::query()->where_in( 'cart_id', $ids )->count();
		$logs_deleted  = LogEntry::query()->where_in( 'cart_id', $ids )->delete_where();

		if ( $logs_deleted !== $expected_logs ) {
			return array(
				'complete'         => false,
				'logs_deleted'     => $logs_deleted,
				'sessions_deleted' => 0,
			);
		}

		$expected_sessions = CartSession::query()->where_in( 'id', $ids )->count();
		$sessions_deleted  = CartSession::query()->where_in( 'id', $ids )->delete_where();

		return array(
			'complete'         => $sessions_deleted === $expected_sessions,
			'logs_deleted'     => $logs_deleted,
			'sessions_deleted' => $sessions_deleted,
		);
	}
}
