<?php
/**
 * Follow-up plan unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use CartRebound\Followup\Plan;
use CartRebound\Followup\Step;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Followup\Plan
 * @covers \CartRebound\Followup\Step
 */
final class FollowupPlanTest extends TestCase {

	public function test_a_single_step_plan_is_what_the_free_plugin_sends(): void {
		$plan = Plan::single( 60 );

		$this->assertSame( 1, $plan->count() );
		$this->assertFalse( $plan->is_empty() );
		$this->assertSame( 60, $plan->step( 0 )->delay_minutes() );
		$this->assertSame( '', $plan->step( 0 )->template_id() );
		$this->assertNull( $plan->next_after( 0 ) );
	}

	public function test_an_empty_plan_schedules_nothing(): void {
		$plan = Plan::none();

		$this->assertTrue( $plan->is_empty() );
		$this->assertSame( 0, $plan->count() );
		$this->assertNull( $plan->step( 0 ) );
	}

	public function test_steps_are_ordered_by_delay_and_reindexed_from_zero(): void {
		$plan = new Plan(
			array(
				new Step( 9, 4320, 'coupon' ),
				new Step( 3, 60, 'reminder' ),
				new Step( 7, 1440, 'urgency' ),
			)
		);

		$this->assertSame( array( 'reminder', 'urgency', 'coupon' ), array_map(
			static function ( Step $step ): string {
				return $step->template_id();
			},
			$plan->steps()
		) );

		// The index is a cursor the runner compares against, so it must be the
		// step's position in the ordered plan, not whatever the add-on passed.
		$this->assertSame( array( 0, 1, 2 ), array_map(
			static function ( Step $step ): int {
				return $step->index();
			},
			$plan->steps()
		) );
	}

	public function test_equal_delays_keep_the_order_they_were_supplied_in(): void {
		$plan = new Plan(
			array(
				new Step( 0, 60, 'first' ),
				new Step( 0, 60, 'second' ),
			)
		);

		$this->assertSame( 'first', $plan->step( 0 )->template_id() );
		$this->assertSame( 'second', $plan->step( 1 )->template_id() );
	}

	public function test_a_plan_is_capped_so_an_add_on_cannot_fan_out_forever(): void {
		$steps = array();

		for ( $index = 0; $index < Plan::MAX_STEPS + 15; $index++ ) {
			$steps[] = new Step( $index, ( $index + 1 ) * 10 );
		}

		$this->assertSame( Plan::MAX_STEPS, ( new Plan( $steps ) )->count() );
	}

	public function test_non_steps_are_discarded_rather_than_trusted(): void {
		$plan = new Plan( array( 'not-a-step', 42, null, new Step( 0, 30 ) ) );

		$this->assertSame( 1, $plan->count() );
	}

	public function test_a_step_delay_is_never_below_one_minute(): void {
		$this->assertSame( 1, ( new Step( 0, 0 ) )->delay_minutes() );
		$this->assertSame( 1, ( new Step( 0, -500 ) )->delay_minutes() );
	}

	public function test_step_meta_carries_add_on_payload_through_to_render_time(): void {
		$step = ( new Step( 0, 60, 'tpl', array( 'coupon_policy' => 'percent' ) ) )
			->with_meta( array( 'variant' => 'b' ) );

		$this->assertSame( 'percent', $step->get( 'coupon_policy' ) );
		$this->assertSame( 'b', $step->get( 'variant' ) );
		$this->assertSame( 'fallback', $step->get( 'missing', 'fallback' ) );
	}
}
