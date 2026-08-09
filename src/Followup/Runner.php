<?php
/**
 * Follow-up plan runner.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Followup;

defined( 'ABSPATH' ) || exit;

use CartRebound\Cron\Scheduler;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Models\CartSession;
use CartRebound\Models\Unsubscribe;
use CartRebound\Support\Settings;

/**
 * Owns the whole life of a cart's follow-up sequence.
 *
 * Everything that is easy to get wrong lives here and only here: whether a cart
 * is still worth mailing, that a step runs exactly once, that a step which
 * arrives out of order is dropped, that a converted or unsubscribed cart stops
 * receiving mail, and that a failed send does not stall the steps behind it.
 * An add-on contributes the plan — a schedule and templates — and never touches
 * any of that.
 *
 * Idempotency is a monotonic cursor: `sessions.followup_step` holds the index
 * of the step that may run next, and a step advances it with a compare-and-set.
 * A duplicated job therefore finds the cursor already moved and does nothing.
 *
 * @since 1.1.0
 */
final class Runner {

	/**
	 * Single-action hook that runs one step. Args: cart id, step index.
	 *
	 * @var string
	 */
	public const HOOK = 'cart_rebound_followup_step';

	/**
	 * Settings store.
	 *
	 * @since 1.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Job scheduler.
	 *
	 * @since 1.1.0
	 * @var Scheduler
	 */
	private $scheduler;

	/**
	 * Recovery mailer.
	 *
	 * @since 1.1.0
	 * @var RecoveryMailer
	 */
	private $mailer;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param Settings       $settings  Settings store.
	 * @param Scheduler      $scheduler Job scheduler.
	 * @param RecoveryMailer $mailer    Recovery mailer.
	 */
	public function __construct( Settings $settings, Scheduler $scheduler, RecoveryMailer $mailer ) {
		$this->settings  = $settings;
		$this->scheduler = $scheduler;
		$this->mailer    = $mailer;
	}

	/**
	 * Build the follow-up plan for a cart.
	 *
	 * The free plugin's plan is the single recovery email it has always sent —
	 * one step, at the configured delay, using the default template — or no plan
	 * at all when recovery email is switched off.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $row The cart row.
	 * @return Plan
	 */
	public function plan_for( array $row ): Plan {
		// The recovery-email setting is the master switch, enforced here rather
		// than left to the filter: a merchant who turns follow-ups off gets no
		// follow-ups, whatever add-on is installed.
		if ( ! $this->settings->get( 'recovery_email_enabled' ) ) {
			return Plan::none();
		}

		$plan = Plan::single( (int) $this->settings->get( 'email_delay_minutes' ) );

		/**
		 * Filter the follow-up plan for an abandoned cart.
		 *
		 * Return a {@see Plan} with as many {@see Step}s as the sequence needs;
		 * each step carries a delay measured in minutes after abandonment, a
		 * template id, and any payload the add-on wants back at render time.
		 *
		 * The runner still owns delivery. It re-checks on every step that the
		 * cart is still abandoned, still has items, still has a mailable address
		 * that has not unsubscribed, and that this exact step has not already
		 * run — so a plan can never resurrect a converted cart or double-send.
		 *
		 * Returning an empty plan suppresses follow-ups for the cart entirely.
		 *
		 * @since 1.1.0
		 *
		 * @param Plan                 $plan The plan the free plugin would run.
		 * @param array<string, mixed> $row  The abandoned cart row.
		 */
		$filtered = apply_filters( 'cart_rebound_followup_plan', $plan, $row );

		return $filtered instanceof Plan ? $filtered : $plan;
	}

	/**
	 * Queue the first step of a freshly abandoned cart's plan.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $row The cart row, as just abandoned.
	 * @return void
	 */
	public function start( array $row ): void {
		$cart_id = (int) ( $row['id'] ?? 0 );

		if ( $cart_id <= 0 || '' === (string) ( $row['email'] ?? '' ) ) {
			return;
		}

		$first = $this->plan_for( $row )->step( 0 );

		if ( null === $first ) {
			return;
		}

		$this->schedule( $cart_id, $first, $row );
	}

	/**
	 * Run one step of a cart's plan.
	 *
	 * @since 1.1.0
	 *
	 * @param int $cart_id    Cart id.
	 * @param int $step_index Zero-based step index.
	 * @return void
	 */
	public function run( int $cart_id, int $step_index ): void {
		$row = CartSession::find( $cart_id );

		if ( ! is_array( $row ) || ! $this->still_mailable( $row ) ) {
			return;
		}

		$plan = $this->plan_for( $row );
		$step = $plan->step( $step_index );

		if ( null === $step ) {
			return;
		}

		// Compare-and-set the cursor before sending. A duplicated or replayed job
		// finds the cursor already past this step and stops here, so the send
		// below runs at most once however many times the job is delivered.
		if ( ! $this->advance_cursor( $cart_id, $step_index ) ) {
			return;
		}

		$sent = $this->mailer->send_step( $row, $step );

		if ( $sent ) {
			/**
			 * Fires after a follow-up step is delivered.
			 *
			 * @since 1.1.0
			 *
			 * @param int                  $cart_id The cart id.
			 * @param Step                 $step    The step that was sent.
			 * @param array<string, mixed> $row     The cart row as it was read.
			 */
			do_action( 'cart_rebound_followup_sent', $cart_id, $step, $row );
		} else {
			/**
			 * Fires when a follow-up step could not be delivered.
			 *
			 * The sequence deliberately continues: one refused hand-off to
			 * wp_mail (a transient SMTP failure, say) should not strand every
			 * later step for that cart.
			 *
			 * @since 1.1.0
			 *
			 * @param int                  $cart_id The cart id.
			 * @param Step                 $step    The step that failed.
			 * @param string               $error   The transport's reason, when it gave one.
			 * @param array<string, mixed> $row     The cart row as it was read.
			 */
			do_action( 'cart_rebound_followup_failed', $cart_id, $step, $this->mailer->get_last_error(), $row );
		}

		$next = $plan->next_after( $step_index );

		if ( null !== $next ) {
			$this->schedule( $cart_id, $next, $row );
		}
	}

	/**
	 * Drop every follow-up still queued for a cart.
	 *
	 * Called wherever the funnel ends for a cart — an order is placed, it is
	 * recovered, the shopper unsubscribes — so no add-on has to discover those
	 * moments for itself.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $cart_id Cart id.
	 * @param string $reason  Why the follow-ups were dropped ('converted', 'order_placed', 'unsubscribed', …).
	 * @return void
	 */
	public function cancel( int $cart_id, string $reason ): void {
		if ( $cart_id <= 0 ) {
			return;
		}

		for ( $index = 0; $index < Plan::MAX_STEPS; $index++ ) {
			$this->scheduler->clear_with_args( self::HOOK, array( $cart_id, $index ) );
		}

		// Follow-ups queued by 1.0 used the mailer's own hook; a cart abandoned
		// before the upgrade still has one of those pending.
		$this->scheduler->clear_with_args( RecoveryMailer::HOOK, array( $cart_id ) );

		/**
		 * Fires when a cart's queued follow-ups are dropped.
		 *
		 * Add-ons that queue work of their own alongside the plan (a scheduled
		 * SMS, a coupon expiry sweep) clear it here.
		 *
		 * @since 1.1.0
		 *
		 * @param int    $cart_id The cart id.
		 * @param string $reason  Why the follow-ups were dropped.
		 */
		do_action( 'cart_rebound_followups_cancelled', $cart_id, $reason );
	}

	/**
	 * Schedule one step against the cart's abandonment time.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $cart_id Cart id.
	 * @param Step                 $step    The step to schedule.
	 * @param array<string, mixed> $row     The cart row.
	 * @return void
	 */
	private function schedule( int $cart_id, Step $step, array $row ): void {
		$this->scheduler->schedule_single(
			$this->due_at( $step, $row ),
			self::HOOK,
			array( $cart_id, $step->index() )
		);
	}

	/**
	 * Resolve the timestamp a step is due at.
	 *
	 * Delays run from `abandoned_at`, so a step that was queued late still lands
	 * on the merchant's stated schedule rather than sliding by however long the
	 * previous step took. A step whose moment has already passed runs on the
	 * next queue pass rather than being back-dated.
	 *
	 * @since 1.1.0
	 *
	 * @param Step                 $step The step to place.
	 * @param array<string, mixed> $row  The cart row.
	 * @return int
	 */
	private function due_at( Step $step, array $row ): int {
		$abandoned = (string) ( $row['abandoned_at'] ?? '' );
		$anchor    = '' !== $abandoned ? strtotime( $abandoned . ' UTC' ) : false;

		if ( false === $anchor ) {
			$anchor = time();
		}

		return max( time() + MINUTE_IN_SECONDS, $anchor + ( $step->delay_minutes() * MINUTE_IN_SECONDS ) );
	}

	/**
	 * Move the cart's step cursor from $step_index to the next one, atomically.
	 *
	 * @since 1.1.0
	 *
	 * @param int $cart_id    Cart id.
	 * @param int $step_index The step index expected to be current.
	 * @return bool True when this call is the one that claimed the step.
	 */
	private function advance_cursor( int $cart_id, int $step_index ): bool {
		$claimed = CartSession::query()
			->where( 'id', '=', $cart_id )
			->where( 'followup_step', '=', $step_index )
			->update_where( array( 'followup_step' => $step_index + 1 ) );

		return $claimed > 0;
	}

	/**
	 * Whether a cart still deserves the next follow-up.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $row The cart row.
	 * @return bool
	 */
	private function still_mailable( array $row ): bool {
		if ( CartSession::STATUS_ABANDONED !== (string) ( $row['status'] ?? '' ) ) {
			return false;
		}

		if ( (int) ( $row['items_count'] ?? 0 ) <= 0 ) {
			return false;
		}

		$email = (string) ( $row['email'] ?? '' );

		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}

		// Suppression is a DB lookup, so it runs last, after the row-field guards
		// have already rejected the cheap cases.
		return ! Unsubscribe::is_suppressed( $email );
	}
}
