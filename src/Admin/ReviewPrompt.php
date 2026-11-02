<?php
/**
 * Review request shown after the plugin recovers a cart.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Admin;

defined( 'ABSPATH' ) || exit;

use CartRebound\Models\CartSession;
use WC_Order;

/**
 * Asks for an honest WordPress.org review, only after the first paid recovery and only
 * inside the plugin's own screens and the admin recovery email.
 *
 * One store-wide answer covers the banner and the email, and a permanent answer always wins.
 *
 * @since 1.1.3
 */
final class ReviewPrompt {

	/**
	 * Option key holding the prompt state.
	 *
	 * @var string
	 */
	public const OPTION = 'cart_rebound_review_prompt';

	/**
	 * Where the store manager writes the review.
	 *
	 * @var string
	 */
	public const REVIEW_URL = 'https://wordpress.org/support/plugin/cart-rebound/reviews/#new-post';

	/**
	 * The store manager opened the review page.
	 *
	 * @var string
	 */
	public const CHOICE_REVIEW = 'review';

	/**
	 * The store manager wants a reminder later.
	 *
	 * @var string
	 */
	public const CHOICE_LATER = 'later';

	/**
	 * The store manager never wants to be asked.
	 *
	 * @var string
	 */
	public const CHOICE_DISMISS = 'dismiss';

	/**
	 * Every answer the REST endpoint accepts.
	 *
	 * @var array<int, string>
	 */
	public const CHOICES = array( self::CHOICE_REVIEW, self::CHOICE_LATER, self::CHOICE_DISMISS );

	/**
	 * How many days "Remind me later" pauses the banner and email requests.
	 *
	 * @var int
	 */
	public const SNOOZE_DAYS = 14;

	/**
	 * Minimum rolling gap between two recovery emails that carry the review request.
	 *
	 * @var int
	 */
	private const EMAIL_INTERVAL_SECONDS = 30 * DAY_IN_SECONDS;

	/**
	 * Waiting for the first recovered cart.
	 *
	 * @var string
	 */
	private const STATUS_IDLE = 'idle';

	/**
	 * Armed by a recovery and waiting for an answer.
	 *
	 * @var string
	 */
	private const STATUS_READY = 'ready';

	/**
	 * Paused until the snooze ends.
	 *
	 * @var string
	 */
	private const STATUS_SNOOZED = 'snoozed';

	/**
	 * Closed for good because the review page was opened (not proof a review was submitted).
	 *
	 * @var string
	 */
	private const STATUS_REVIEW_CLICKED = 'review_clicked';

	/**
	 * Closed for good at the store manager's request.
	 *
	 * @var string
	 */
	private const STATUS_DISMISSED = 'dismissed';

	/**
	 * The emailed_at value a successful claim replaced, so a failed send can hand it back.
	 *
	 * @since 1.1.3
	 * @var int|null
	 */
	private $claimed_from = null;

	/**
	 * Arm the prompt with the order that just recovered a cart.
	 *
	 * Called only from the paid-recovery flow, which is idempotent per order.
	 *
	 * @since 1.1.3
	 *
	 * @param WC_Order $order The paid order that recovered the cart.
	 * @return void
	 */
	public function record_recovery( WC_Order $order ): void {
		$this->mutate(
			function ( array $state ) use ( $order ): ?array {
				if ( $this->is_closed( $state ) ) {
					return null;
				}

				$state['amount']       = (float) $order->get_total();
				$state['currency']     = (string) $order->get_currency();
				$state['order_number'] = (string) $order->get_order_number();

				if ( self::STATUS_IDLE === $state['status'] ) {
					$state['status'] = self::STATUS_READY;
				}

				return $state;
			}
		);
	}

	/**
	 * Stand the prompt down when a refund or cancellation leaves no recovered cart to point to.
	 *
	 * @since 1.1.3
	 *
	 * @return void
	 */
	public function recovery_reversed(): void {
		if ( $this->recovered_carts() > 0 ) {
			return;
		}

		$this->mutate(
			function ( array $state ): ?array {
				if ( $this->is_closed( $state ) || self::STATUS_IDLE === $state['status'] ) {
					return null;
				}

				return array_merge( $this->normalise( array() ), array( 'emailed_at' => $state['emailed_at'] ) );
			}
		);
	}

	/**
	 * Whether the banner should show right now.
	 *
	 * @since 1.1.3
	 *
	 * @return bool
	 */
	public function is_due(): bool {
		return $this->due( $this->state() );
	}

	/**
	 * Reserve the review request for the admin recovery email about to be sent.
	 *
	 * Reads the stored state fresh and swaps it atomically, so a dismissal saved by
	 * another request wins and two simultaneous emails cannot both carry the request.
	 *
	 * @since 1.1.3
	 *
	 * @return bool True when this email should carry the request.
	 */
	public function claim_email(): bool {
		$raw   = $this->raw_option();
		$state = $this->normalise( null === $raw ? array() : maybe_unserialize( $raw ) );

		if ( null === $raw || ! $this->due( $state ) || time() - $state['emailed_at'] < self::EMAIL_INTERVAL_SECONDS ) {
			return false;
		}

		$claimed               = $state;
		$claimed['emailed_at'] = time();

		if ( ! $this->swap( $raw, $claimed ) ) {
			return false;
		}

		$this->claimed_from = $state['emailed_at'];

		return true;
	}

	/**
	 * Hand back a claim whose email was never sent, so the next email may carry the request.
	 *
	 * @since 1.1.3
	 *
	 * @return void
	 */
	public function release_email(): void {
		if ( null === $this->claimed_from ) {
			return;
		}

		$emailed_at         = $this->claimed_from;
		$this->claimed_from = null;

		$this->mutate(
			static function ( array $state ) use ( $emailed_at ): array {
				$state['emailed_at'] = $emailed_at;

				return $state;
			}
		);
	}

	/**
	 * The review request for the admin dashboard, or null when it is not due.
	 *
	 * @since 1.1.3
	 *
	 * @return array{recoveries: int, recoveriesText: string, amount: string, orderNumber: string, reviewUrl: string, snoozeDays: int}|null
	 */
	public function for_admin(): ?array {
		$state = $this->state();

		if ( ! $this->due( $state ) ) {
			return null;
		}

		$recoveries = $this->recoveries();

		return array(
			'recoveries'     => $recoveries,
			'recoveriesText' => number_format_i18n( $recoveries ),
			'amount'         => $state['amount'] > 0 ? $this->money( $state['amount'], $state['currency'] ) : '',
			'orderNumber'    => $state['order_number'],
			'reviewUrl'      => self::REVIEW_URL,
			'snoozeDays'     => self::SNOOZE_DAYS,
		);
	}

	/**
	 * Record the store manager's answer.
	 *
	 * A permanent answer is never replaced, so a stale "remind me later" cannot reopen it.
	 *
	 * @since 1.1.3
	 *
	 * @param string $choice One of the CHOICE_* constants.
	 * @return bool False for an unknown choice or when the answer could not be saved.
	 */
	public function respond( string $choice ): bool {
		if ( ! in_array( $choice, self::CHOICES, true ) ) {
			return false;
		}

		return $this->mutate(
			function ( array $state ) use ( $choice ): ?array {
				if ( $this->is_closed( $state ) ) {
					return null;
				}

				if ( self::CHOICE_LATER === $choice ) {
					// Nothing has been asked yet, so there is nothing to put off.
					if ( self::STATUS_IDLE === $state['status'] ) {
						return null;
					}

					$state['status']        = self::STATUS_SNOOZED;
					$state['snoozed_until'] = time() + self::SNOOZE_DAYS * DAY_IN_SECONDS;
				} else {
					$state['status'] = self::CHOICE_REVIEW === $choice ? self::STATUS_REVIEW_CLICKED : self::STATUS_DISMISSED;
				}

				return $state;
			}
		);
	}

	/**
	 * Recovered carts, counted exactly as the dashboard's "Recovered orders" card counts them.
	 *
	 * @since 1.1.3
	 *
	 * @return int
	 */
	public function recoveries(): int {
		return max( 1, $this->recovered_carts() );
	}

	/**
	 * How many tracked carts are currently recovered.
	 *
	 * @since 1.1.3
	 *
	 * @return int
	 */
	private function recovered_carts(): int {
		return CartSession::query()->where( 'status', '=', CartSession::STATUS_RECOVERED )->count();
	}

	/**
	 * Whether a state is due for the request; the filter may only veto it.
	 *
	 * @since 1.1.3
	 *
	 * @param array{status: string, snoozed_until: int} $state Prompt state.
	 * @return bool
	 */
	private function due( array $state ): bool {
		$due = self::STATUS_READY === $state['status']
			|| ( self::STATUS_SNOOZED === $state['status'] && time() >= $state['snoozed_until'] );

		/**
		 * Filter whether Cart Rebound may ask for a review, in the dashboard and by email.
		 *
		 * Returning false suppresses the request. Returning true cannot override a saved
		 * answer or a snooze.
		 *
		 * @since 1.1.3
		 *
		 * @param bool $due Whether the request is due.
		 */
		return $due && (bool) apply_filters( 'cart_rebound_show_review_prompt', $due );
	}

	/**
	 * Whether the store manager has answered for good.
	 *
	 * @since 1.1.3
	 *
	 * @param array{status: string} $state Prompt state.
	 * @return bool
	 */
	private function is_closed( array $state ): bool {
		return in_array( $state['status'], array( self::STATUS_REVIEW_CLICKED, self::STATUS_DISMISSED ), true );
	}

	/**
	 * The stored prompt state merged over its defaults.
	 *
	 * @since 1.1.3
	 *
	 * @return array{status: string, snoozed_until: int, emailed_at: int, amount: float, currency: string, order_number: string}
	 */
	private function state(): array {
		return $this->normalise( get_option( self::OPTION, array() ) );
	}

	/**
	 * Coerce a stored value into a complete prompt state.
	 *
	 * @since 1.1.3
	 *
	 * @param mixed $stored Stored option value.
	 * @return array{status: string, snoozed_until: int, emailed_at: int, amount: float, currency: string, order_number: string}
	 */
	private function normalise( $stored ): array {
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'status'        => sanitize_key( (string) ( $stored['status'] ?? self::STATUS_IDLE ) ),
			'snoozed_until' => (int) ( $stored['snoozed_until'] ?? 0 ),
			'emailed_at'    => (int) ( $stored['emailed_at'] ?? 0 ),
			'amount'        => (float) ( $stored['amount'] ?? 0 ),
			'currency'      => (string) ( $stored['currency'] ?? '' ),
			'order_number'  => (string) ( $stored['order_number'] ?? '' ),
		);
	}

	/**
	 * Apply a change to the stored state without overwriting a concurrent write.
	 *
	 * @since 1.1.3
	 *
	 * @param callable $change Receives the current state and returns the new one, or null to leave it as it is.
	 * @return bool True when the change is stored or none was needed.
	 *
	 * @phpstan-param callable(array{status: string, snoozed_until: int, emailed_at: int, amount: float, currency: string, order_number: string}): ?array<string, mixed> $change
	 */
	private function mutate( callable $change ): bool {
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$raw   = $this->raw_option();
			$state = $this->normalise( null === $raw ? array() : maybe_unserialize( $raw ) );
			$next  = $change( $state );

			if ( null === $next || $next === $state ) {
				return true;
			}

			if ( null === $raw ? add_option( self::OPTION, $next, '', false ) : $this->swap( $raw, $next ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The stored option exactly as the database holds it, bypassing the object cache.
	 *
	 * @since 1.1.3
	 *
	 * @return string|null Null when the option does not exist.
	 */
	private function raw_option(): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- must see writes from concurrent requests, which the cache hides.
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION ) );

		return null === $raw ? null : (string) $raw;
	}

	/**
	 * Replace the stored option only if it still holds the expected raw value.
	 *
	 * @since 1.1.3
	 *
	 * @param string               $expected Raw value read before the change.
	 * @param array<string, mixed> $state    New prompt state.
	 * @return bool True when this request won the swap.
	 */
	private function swap( string $expected, array $state ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap; update_option() cannot condition on the old value.
		$swapped = $wpdb->update(
			$wpdb->options,
			array( 'option_value' => maybe_serialize( $state ) ),
			array(
				'option_name'  => self::OPTION,
				'option_value' => $expected,
			)
		);

		wp_cache_delete( self::OPTION, 'options' );

		return 1 === $swapped;
	}

	/**
	 * Format an amount as plain-text store currency.
	 *
	 * @since 1.1.3
	 *
	 * @param float  $amount   The amount.
	 * @param string $currency Currency code; the store default when empty.
	 * @return string
	 */
	private function money( float $amount, string $currency ): string {
		if ( ! function_exists( 'wc_price' ) ) {
			return number_format_i18n( $amount, 2 );
		}

		$args = '' !== $currency ? array( 'currency' => $currency ) : array();

		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, $args ) ), ENT_QUOTES, 'UTF-8' );
	}
}
