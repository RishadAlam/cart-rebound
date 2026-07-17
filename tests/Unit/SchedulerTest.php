<?php
/**
 * Scheduler unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Cron\Scheduler;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Cron\Scheduler
 */
final class SchedulerTest extends TestCase {

	public function test_clear_removes_every_wp_cron_job_for_the_hook(): void {
		Functions\expect( 'wp_unschedule_hook' )
			->once()
			->with( RecoveryMailer::HOOK )
			->andReturn( 2 );

		( new Scheduler() )->clear( RecoveryMailer::HOOK );

		$this->addToAssertionCount( 1 );
	}

	public function test_clear_with_args_clears_only_the_matching_wp_cron_job(): void {
		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( RecoveryMailer::HOOK, array( 17 ) )
			->andReturn( 1 );

		( new Scheduler() )->clear_with_args( RecoveryMailer::HOOK, array( 17 ) );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_action_scheduler_clear_removes_all_args_but_targeted_clear_keeps_them(): void {
		eval( // phpcs:ignore Squiz.PHP.Eval.Discouraged -- isolated test stubs make function_exists() follow the Action Scheduler branch.
			'namespace {
				function as_schedule_recurring_action() {}
				function as_next_scheduled_action() { return false; }
				function as_schedule_single_action() {}
				function as_unschedule_all_actions( ...$args ) { $GLOBALS["cart_rebound_unscheduled"][] = $args; }
			}'
		);

		Functions\when( 'wp_unschedule_hook' )->justReturn( 1 );
		Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 1 );

		$scheduler = new Scheduler();
		$scheduler->clear( RecoveryMailer::HOOK );
		$scheduler->clear_with_args( RecoveryMailer::HOOK, array( 17 ) );

		$this->assertSame(
			array(
				array( RecoveryMailer::HOOK ),
				array( RecoveryMailer::HOOK, array( 17 ), Scheduler::GROUP ),
			),
			$GLOBALS['cart_rebound_unscheduled']
		);
	}
}
