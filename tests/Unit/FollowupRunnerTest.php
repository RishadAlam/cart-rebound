<?php
/**
 * Follow-up runner unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Cron\Scheduler;
use CartRebound\Followup\Plan;
use CartRebound\Followup\Runner;
use CartRebound\Followup\Step;
use CartRebound\Mail\RecoveryMailer;
use CartRebound\Mail\TemplateStore;
use CartRebound\Models\CartSession;
use CartRebound\Recovery\RecoveryLink;
use CartRebound\Support\Settings;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Followup\Runner
 */
final class FollowupRunnerTest extends TestCase {

	/** @var RunnerWpdb */
	private $wpdb;

	/** @var array<int, array{timestamp: int, hook: string, args: array<int, mixed>}> */
	private $scheduled = array();

	/** @var array<int, array{hook: string, args: array<int, mixed>}> */
	private $cleared = array();

	/** @var array<int, array<string, mixed>> */
	private $mailed = array();

	/** @var array<string, mixed> */
	private $settings = array();

	protected function set_up(): void {
		parent::set_up();

		$this->wpdb      = new RunnerWpdb();
		$GLOBALS['wpdb'] = $this->wpdb;
		$this->scheduled = array();
		$this->cleared   = array();
		$this->mailed    = array();
		$this->settings  = array(
			'recovery_email_enabled' => true,
			'email_delay_minutes'    => 60,
		);

		Functions\when( 'is_email' )->alias(
			static function ( $email ) {
				return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
			}
		);
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'wpautop' )->returnArg();
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'template-id' );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wc_get_cart_url' )->justReturn( 'https://shop.test/cart' );
		Functions\when( 'add_query_arg' )->justReturn( 'https://shop.test/cart?recover' );
		Functions\when( 'home_url' )->justReturn( 'https://shop.test/' );

		Functions\when( 'get_option' )->alias(
			function ( $key ) {
				return Settings::OPTION === $key ? $this->settings : null;
			}
		);

		Functions\when( 'wp_schedule_single_event' )->alias(
			function ( $timestamp, $hook, $args = array() ): bool {
				$this->scheduled[] = array(
					'timestamp' => (int) $timestamp,
					'hook'      => (string) $hook,
					'args'      => (array) $args,
				);

				return true;
			}
		);

		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			function ( $hook, $args = array() ): void {
				$this->cleared[] = array(
					'hook' => (string) $hook,
					'args' => (array) $args,
				);
			}
		);

		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $message ): bool {
				$this->mailed[] = array(
					'to'      => $to,
					'subject' => $subject,
					'message' => $message,
				);

				return true;
			}
		);
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_the_free_plan_is_the_single_email_it_has_always_sent(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$plan = $this->runner()->plan_for( $this->row() );

		$this->assertSame( 1, $plan->count() );
		$this->assertSame( 60, $plan->step( 0 )->delay_minutes() );
	}

	public function test_the_recovery_email_setting_is_a_master_switch_an_add_on_cannot_reopen(): void {
		$this->settings['recovery_email_enabled'] = false;

		$reached = false;

		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) use ( &$reached ) {
				if ( 'cart_rebound_followup_plan' === $hook ) {
					$reached = true;

					return new Plan( array( new Step( 0, 30 ) ) );
				}

				return $value;
			}
		);

		$plan = $this->runner()->plan_for( $this->row() );

		$this->assertTrue( $plan->is_empty() );
		$this->assertFalse( $reached, 'The plan filter must not run while follow-ups are switched off.' );
	}

	public function test_an_add_on_plan_replaces_the_single_email(): void {
		$this->filter_plan( new Plan( array( new Step( 0, 60 ), new Step( 1, 1440 ), new Step( 2, 4320 ) ) ) );

		$this->assertSame( 3, $this->runner()->plan_for( $this->row() )->count() );
	}

	public function test_a_filter_returning_junk_falls_back_to_the_free_plan(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) {
				return 'cart_rebound_followup_plan' === $hook ? 'not-a-plan' : $value;
			}
		);

		$this->assertSame( 1, $this->runner()->plan_for( $this->row() )->count() );
	}

	public function test_starting_a_plan_queues_the_first_step_from_the_abandonment_time(): void {
		$this->filter_plan( new Plan( array( new Step( 0, 90 ), new Step( 1, 1440 ) ) ) );

		$abandoned = gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS );

		$this->runner()->start( $this->row( array( 'abandoned_at' => $abandoned ) ) );

		$this->assertCount( 1, $this->scheduled );
		$this->assertSame( Runner::HOOK, $this->scheduled[0]['hook'] );
		$this->assertSame( array( 7, 0 ), $this->scheduled[0]['args'] );

		// 90 minutes after abandonment, which was 30 minutes ago: an hour out.
		$this->assertEqualsWithDelta(
			strtotime( $abandoned . ' UTC' ) + 90 * MINUTE_IN_SECONDS,
			$this->scheduled[0]['timestamp'],
			5
		);
	}

	public function test_a_step_whose_moment_already_passed_runs_next_rather_than_in_the_past(): void {
		$this->filter_plan( new Plan( array( new Step( 0, 60 ) ) ) );

		$this->runner()->start(
			$this->row( array( 'abandoned_at' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) )
		);

		$this->assertGreaterThan( time(), $this->scheduled[0]['timestamp'] );
	}

	public function test_a_cart_that_left_the_abandoned_state_is_never_mailed(): void {
		$this->filter_plan( new Plan( array( new Step( 0, 60 ) ) ) );
		$this->wpdb->results = array( $this->row( array( 'status' => CartSession::STATUS_RECOVERED ) ) );

		$this->runner()->run( 7, 0 );

		$this->assertSame( array(), $this->mailed );
		$this->assertSame( 0, $this->wpdb->updates );
	}

	public function test_an_unsubscribed_shopper_is_never_mailed_again(): void {
		$this->filter_plan( new Plan( array( new Step( 0, 60 ) ) ) );
		$this->wpdb->results      = array( $this->row() );
		$this->wpdb->unsubscribed = true;

		$this->runner()->run( 7, 0 );

		$this->assertSame( array(), $this->mailed );
	}

	public function test_an_emptied_cart_is_never_mailed(): void {
		$this->filter_plan( new Plan( array( new Step( 0, 60 ) ) ) );
		$this->wpdb->results = array( $this->row( array( 'items_count' => 0 ) ) );

		$this->runner()->run( 7, 0 );

		$this->assertSame( array(), $this->mailed );
	}

	public function test_a_replayed_job_cannot_send_the_same_step_twice(): void {
		$this->filter_plan( new Plan( array( new Step( 0, 60 ), new Step( 1, 1440 ) ) ) );
		$this->wpdb->results = array( $this->row() );

		// The compare-and-set finds the cursor already past this step.
		$this->wpdb->rows_updated = 0;

		$this->runner()->run( 7, 0 );

		$this->assertSame( array(), $this->mailed );
		$this->assertSame( array(), $this->scheduled, 'A step it never claimed must not queue the next one either.' );
	}

	public function test_a_delivered_step_queues_the_one_behind_it(): void {
		$this->filter_plan( new Plan( array( new Step( 0, 60 ), new Step( 1, 1440 ) ) ) );
		$this->wpdb->results = array( $this->row() );

		$this->runner()->run( 7, 0 );

		$this->assertCount( 1, $this->mailed );
		$this->assertCount( 1, $this->scheduled );
		$this->assertSame( array( 7, 1 ), $this->scheduled[0]['args'] );
	}

	public function test_the_last_step_queues_nothing_after_it(): void {
		$this->filter_plan( new Plan( array( new Step( 0, 60 ) ) ) );
		$this->wpdb->results = array( $this->row() );

		$this->runner()->run( 7, 0 );

		$this->assertCount( 1, $this->mailed );
		$this->assertSame( array(), $this->scheduled );
	}

	public function test_a_failed_send_still_advances_the_sequence(): void {
		Functions\when( 'wp_mail' )->justReturn( false );

		$this->filter_plan( new Plan( array( new Step( 0, 60 ), new Step( 1, 1440 ) ) ) );
		$this->wpdb->results = array( $this->row() );

		$this->runner()->run( 7, 0 );

		$this->assertCount( 1, $this->scheduled, 'One refused hand-off must not strand every later step.' );
		$this->assertSame( array( 7, 1 ), $this->scheduled[0]['args'] );
	}

	public function test_cancelling_clears_every_step_slot_and_the_legacy_job(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->runner()->cancel( 7, 'converted' );

		$hooks = array_column( $this->cleared, 'hook' );

		$this->assertSame( Plan::MAX_STEPS, count( array_filter(
			$hooks,
			static function ( string $hook ): bool {
				return Runner::HOOK === $hook;
			}
		) ) );

		$this->assertContains( RecoveryMailer::HOOK, $hooks );
	}

	/**
	 * Make the plan filter return a fixed plan.
	 *
	 * @param Plan $plan The plan to return.
	 * @return void
	 */
	private function filter_plan( Plan $plan ): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) use ( $plan ) {
				return 'cart_rebound_followup_plan' === $hook ? $plan : $value;
			}
		);
	}

	/**
	 * A mailable abandoned cart row.
	 *
	 * @param array<string, mixed> $overrides Fields to override.
	 * @return array<string, mixed>
	 */
	private function row( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'             => 7,
				'email'          => 'shopper@example.com',
				'first_name'     => 'Jordan',
				'status'         => CartSession::STATUS_ABANDONED,
				'items_count'    => 2,
				'cart_contents'  => '[]',
				'recovery_token' => 'token',
				'followup_step'  => 0,
				'abandoned_at'   => gmdate( 'Y-m-d H:i:s' ),
			),
			$overrides
		);
	}

	private function runner(): Runner {
		$settings = new Settings();

		return new Runner(
			$settings,
			new Scheduler(),
			new RecoveryMailer( $settings, new RecoveryLink(), new TemplateStore( $settings ) )
		);
	}
}

// phpcs:disable -- Lightweight database fixture for model lookups and writes.
class RunnerWpdb {

	/** @var string */
	public $prefix = 'wp_';

	/** @var array<int, array<string, mixed>> */
	public $results = array();

	/** @var bool */
	public $unsubscribed = false;

	/** @var int */
	public $rows_updated = 1;

	/** @var int */
	public $updates = 0;

	public function prepare( $query, $args = array() ) {
		$flat = is_array( $args ) ? $args : array( $args );

		return (string) preg_replace_callback(
			'/%[isdf]/',
			static function () use ( &$flat ) {
				return (string) array_shift( $flat );
			},
			(string) $query
		);
	}

	public function get_results( $query, $output = null ) {
		unset( $output );

		if ( is_string( $query ) && false !== strpos( $query, 'cart_rebound_unsubscribes' ) ) {
			return $this->unsubscribed
				? array( array( 'id' => 1, 'email' => 'shopper@example.com' ) )
				: array();
		}

		return $this->results;
	}

	public function query( $query ) {
		if ( is_string( $query ) && 0 === strpos( ltrim( $query ), 'UPDATE' ) ) {
			++$this->updates;

			return $this->rows_updated;
		}

		return 0;
	}

	public function update( $table, $data, $where ) {
		unset( $table, $data, $where );

		++$this->updates;

		return 1;
	}
}
