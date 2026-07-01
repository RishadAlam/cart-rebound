<?php
/**
 * Activity-log event subscriber.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Events;

defined( 'ABSPATH' ) || exit;

use CartRebound\Data\LogRepository;
use CartRebound\Models\LogEntry;

/**
 * Listens to the plugin's public events and records them in the activity log.
 *
 * Hooking the public actions (rather than writing from the emitters) keeps the
 * dispatcher/mailer free of logging concerns.
 *
 * @since 0.2.0
 */
final class LogSubscriber {

	/**
	 * Log repository.
	 *
	 * @since 0.2.0
	 * @var LogRepository
	 */
	private $logs;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param LogRepository $logs Log repository.
	 */
	public function __construct( LogRepository $logs ) {
		$this->logs = $logs;
	}

	/**
	 * Record a cart abandonment.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $payload The cart_rebound_abandoned payload.
	 * @return void
	 */
	public function on_abandoned( $payload ): void {
		if ( ! is_array( $payload ) ) {
			return;
		}

		$this->logs->log(
			LogEntry::LEVEL_WARNING,
			'abandoned',
			sprintf(
				/* translators: 1: shopper email, 2: formatted amount. */
				__( 'Cart abandoned by %1$s (%2$s).', 'cart-rebound' ),
				$this->who( $payload ),
				$this->money( $payload, 'cart_total' )
			),
			(int) ( $payload['cart_id'] ?? 0 )
		);
	}

	/**
	 * Record a cart recovery.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $payload The cart_rebound_recovered payload.
	 * @return void
	 */
	public function on_recovered( $payload ): void {
		if ( ! is_array( $payload ) ) {
			return;
		}

		$order  = (int) ( $payload['order_id'] ?? 0 );
		$method = (string) ( $payload['recovery_method'] ?? '' );

		$via = '' !== $method
			/* translators: %s: recovery method, e.g. email_link or direct. */
			? sprintf( __( ' via %s', 'cart-rebound' ), $method )
			: '';

		$this->logs->log(
			LogEntry::LEVEL_SUCCESS,
			'recovered',
			sprintf(
				/* translators: 1: shopper email, 2: formatted amount, 3: order id, 4: " via <method>" or empty. */
				__( 'Cart recovered by %1$s (%2$s) — order #%3$d%4$s.', 'cart-rebound' ),
				$this->who( $payload ),
				$this->money( $payload, 'recovered_amount' ),
				$order,
				$via
			),
			(int) ( $payload['cart_id'] ?? 0 )
		);
	}

	/**
	 * Record a recovery email being sent.
	 *
	 * @since 0.2.0
	 *
	 * @param int   $cart_id  The cart id.
	 * @param mixed $row      The cart row (for the recipient).
	 * @param mixed $template The template that was sent.
	 * @return void
	 */
	public function on_email_sent( $cart_id, $row = array(), $template = array() ): void {
		$email     = is_array( $row ) ? (string) ( $row['email'] ?? '' ) : '';
		$recipient = '' !== $email ? $email : __( 'the shopper', 'cart-rebound' );
		$name      = is_array( $template ) ? (string) ( $template['name'] ?? '' ) : '';

		$message = '' !== $name
			? sprintf(
				/* translators: 1: template name, 2: recipient email. */
				__( 'Recovery email “%1$s” sent to %2$s.', 'cart-rebound' ),
				$name,
				$recipient
			)
			: sprintf(
				/* translators: %s: recipient email. */
				__( 'Recovery email sent to %s.', 'cart-rebound' ),
				$recipient
			);

		$this->logs->log( LogEntry::LEVEL_INFO, 'email_sent', $message, (int) $cart_id );
	}

	/**
	 * Resolve a display name for the shopper from a payload.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return string
	 */
	private function who( array $payload ): string {
		$email = (string) ( $payload['customer_email'] ?? '' );

		return '' !== $email ? $email : __( 'a guest', 'cart-rebound' );
	}

	/**
	 * Format a money field from a payload with its currency.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @param string               $key     Amount key.
	 * @return string
	 */
	private function money( array $payload, string $key ): string {
		$amount   = number_format_i18n( (float) ( $payload[ $key ] ?? 0 ), 2 );
		$currency = (string) ( $payload['currency'] ?? '' );

		return trim( $amount . ' ' . $currency );
	}
}
