<?php
/**
 * The ordered follow-up plan for one abandoned cart.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Followup;

defined( 'ABSPATH' ) || exit;

/**
 * An ordered, contiguous list of {@see Step}s.
 *
 * The free plugin always produces a plan of exactly one step — the single
 * recovery email it has always sent. An add-on returns a longer plan from the
 * `cart_rebound_followup_plan` filter; it supplies the schedule and the
 * templates, and nothing else. Sending, the eligibility guards, idempotency,
 * and cancellation stay in {@see Runner}, so an add-on cannot get them wrong.
 *
 * Steps are re-indexed on construction and sorted by delay, which makes the
 * step index a stable, monotonic cursor the runner can compare-and-set against.
 *
 * @since 1.1.0
 */
final class Plan {

	/**
	 * Hard ceiling on steps in a single plan.
	 *
	 * Bounds the work the runner does when it cancels a cart's queued steps,
	 * and stops a misbehaving add-on from scheduling an unbounded fan-out.
	 *
	 * @var int
	 */
	public const MAX_STEPS = 20;

	/**
	 * The plan's steps, contiguous from index 0.
	 *
	 * @since 1.1.0
	 * @var array<int, Step>
	 */
	private $steps;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, Step> $steps Steps in any order; sorted and re-indexed here.
	 */
	public function __construct( array $steps = array() ) {
		$this->steps = $this->normalise( $steps );
	}

	/**
	 * Build the single-step plan the free plugin sends.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $delay_minutes Minutes after abandonment.
	 * @param string $template_id   Template id, or '' for the default.
	 * @return Plan
	 */
	public static function single( int $delay_minutes, string $template_id = '' ): Plan {
		return new self( array( new Step( 0, $delay_minutes, $template_id ) ) );
	}

	/**
	 * Build an empty plan (nothing is scheduled).
	 *
	 * @since 1.1.0
	 *
	 * @return Plan
	 */
	public static function none(): Plan {
		return new self( array() );
	}

	/**
	 * Get every step, in order.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, Step>
	 */
	public function steps(): array {
		return $this->steps;
	}

	/**
	 * Get one step by index.
	 *
	 * @since 1.1.0
	 *
	 * @param int $index Zero-based step index.
	 * @return Step|null
	 */
	public function step( int $index ): ?Step {
		return $this->steps[ $index ] ?? null;
	}

	/**
	 * Get the step that follows the given index.
	 *
	 * @since 1.1.0
	 *
	 * @param int $index Zero-based step index.
	 * @return Step|null
	 */
	public function next_after( int $index ): ?Step {
		return $this->step( $index + 1 );
	}

	/**
	 * Count the steps.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->steps );
	}

	/**
	 * Whether the plan schedules nothing.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->steps;
	}

	/**
	 * Represent the plan as plain arrays (logging, events, tests).
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function to_array(): array {
		$steps = array();

		foreach ( $this->steps as $step ) {
			$steps[] = $step->to_array();
		}

		return $steps;
	}

	/**
	 * Drop non-steps, order by delay, cap the length, and re-index from zero.
	 *
	 * Sorting by delay is what lets the runner treat the index as a cursor: a
	 * plan is always "earliest first", so advancing the cursor always advances
	 * time. Equal delays keep their submitted order (usort is stable in PHP 8;
	 * the explicit index tiebreak keeps 7.4 stable too).
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, mixed> $steps Raw steps.
	 * @return array<int, Step>
	 */
	private function normalise( array $steps ): array {
		$valid = array();

		foreach ( array_values( $steps ) as $position => $step ) {
			if ( $step instanceof Step ) {
				$valid[] = array( $position, $step );
			}
		}

		usort(
			$valid,
			static function ( array $left, array $right ): int {
				$delay = $left[1]->delay_minutes() <=> $right[1]->delay_minutes();

				return 0 !== $delay ? $delay : ( $left[0] <=> $right[0] );
			}
		);

		$ordered = array();

		foreach ( array_slice( $valid, 0, self::MAX_STEPS ) as $index => $entry ) {
			$ordered[] = $entry[1]->with_index( $index );
		}

		return $ordered;
	}
}
