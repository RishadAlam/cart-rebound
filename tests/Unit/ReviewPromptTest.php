<?php

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use CartRebound\Admin\ReviewPrompt;
use CartRebound\Tests\TestCase;
use WC_Order;

/**
 * @covers \CartRebound\Admin\ReviewPrompt
 */
final class ReviewPromptTest extends TestCase {

	/** @var array<string, mixed> */
	public $options = array();

	protected function set_up(): void {
		parent::set_up();

		$this->options   = array();
		$GLOBALS['wpdb'] = new ReviewPromptWpdb( $this );

		Functions\when( 'get_option' )->alias(
			function ( $key, $fallback = false ) {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) {
				$changed               = ( $this->options[ $key ] ?? null ) !== $value;
				$this->options[ $key ] = $value;

				return $changed;
			}
		);
		Functions\when( 'add_option' )->alias(
			function ( $key, $value ) {
				if ( array_key_exists( $key, $this->options ) ) {
					return false;
				}

				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'maybe_serialize' )->alias( 'serialize' );
		Functions\when( 'maybe_unserialize' )->alias( 'unserialize' );
		Functions\when( 'wp_cache_delete' )->justReturn( true );
		Functions\when( 'number_format_i18n' )->alias(
			static function ( $number, $decimals = 0 ) {
				return number_format( (float) $number, (int) $decimals );
			}
		);
	}

	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	public function test_stays_silent_until_a_cart_is_recovered(): void {
		$prompt = new ReviewPrompt();

		$this->assertFalse( $prompt->is_due() );
		$this->assertFalse( $prompt->claim_email() );
		$this->assertNull( $prompt->for_admin() );
	}

	public function test_a_recovery_arms_the_prompt_with_that_order(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 49.5, '1042' ) );

		$this->assertSame(
			array(
				'recoveries'     => 3,
				'recoveriesText' => '3',
				'amount'         => '49.50',
				'orderNumber'    => '1042',
				'reviewUrl'      => ReviewPrompt::REVIEW_URL,
				'snoozeDays'     => 14,
			),
			$prompt->for_admin()
		);
	}

	public function test_a_free_order_has_no_amount(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 0, '7' ) );

		$this->assertSame( '', $prompt->for_admin()['amount'] );
	}

	public function test_remind_later_pauses_banner_and_email_until_the_snooze_ends(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );

		$this->assertTrue( $prompt->respond( ReviewPrompt::CHOICE_LATER ) );
		$this->assertFalse( $prompt->is_due() );
		$this->assertFalse( $prompt->claim_email() );

		$this->options[ ReviewPrompt::OPTION ]['snoozed_until'] = time() - 1;

		$this->assertTrue( $prompt->is_due() );
		$this->assertTrue( $prompt->claim_email() );
	}

	public function test_a_permanent_answer_survives_recoveries_and_stale_reminders(): void {
		foreach ( array( ReviewPrompt::CHOICE_REVIEW, ReviewPrompt::CHOICE_DISMISS ) as $choice ) {
			$this->options = array();
			$prompt        = new ReviewPrompt();
			$prompt->record_recovery( $this->order( 10, '1' ) );

			$this->assertTrue( $prompt->respond( $choice ) );

			$prompt->record_recovery( $this->order( 20, '2' ) );
			$this->assertTrue( $prompt->respond( ReviewPrompt::CHOICE_LATER ) );

			$this->assertFalse( $prompt->is_due(), $choice );
			$this->assertFalse( $prompt->claim_email(), $choice );
		}
	}

	public function test_opening_the_review_page_is_not_recorded_as_a_submitted_review(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );
		$prompt->respond( ReviewPrompt::CHOICE_REVIEW );

		$this->assertSame( 'review_clicked', $this->options[ ReviewPrompt::OPTION ]['status'] );
	}

	public function test_remind_later_before_any_recovery_changes_nothing(): void {
		$prompt = new ReviewPrompt();

		$this->assertTrue( $prompt->respond( ReviewPrompt::CHOICE_LATER ) );
		$this->assertArrayNotHasKey( ReviewPrompt::OPTION, $this->options );
	}

	public function test_a_recovery_does_not_undo_a_dismissal_saved_meanwhile(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );

		$GLOBALS['wpdb']->before_update = function () {
			$GLOBALS['wpdb']->before_update            = null;
			$this->options[ ReviewPrompt::OPTION ]['status'] = 'dismissed';
		};

		$prompt->record_recovery( $this->order( 20, '2' ) );

		$this->assertSame( 'dismissed', $this->options[ ReviewPrompt::OPTION ]['status'] );
		$this->assertFalse( $prompt->is_due() );
	}

	public function test_reversing_the_only_recovery_stands_the_prompt_down(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );

		$GLOBALS['wpdb']->recovered = 0;
		$prompt->recovery_reversed();

		$this->assertFalse( $prompt->is_due() );
		$this->assertNull( $prompt->for_admin() );
	}

	public function test_reversal_keeps_the_prompt_while_other_recoveries_remain(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );

		$prompt->recovery_reversed();

		$this->assertTrue( $prompt->is_due() );
	}

	public function test_rejects_an_unknown_choice_without_changing_state(): void {
		$prompt = new ReviewPrompt();

		$this->assertFalse( $prompt->respond( 'reviewed' ) );
		$this->assertArrayNotHasKey( ReviewPrompt::OPTION, $this->options );
	}

	public function test_email_carries_the_request_at_most_once_a_rolling_month(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );

		$this->assertTrue( $prompt->claim_email() );
		$this->assertFalse( $prompt->claim_email() );
		$this->assertTrue( $prompt->is_due() );

		$this->options[ ReviewPrompt::OPTION ]['emailed_at'] = time() - 31 * DAY_IN_SECONDS;

		$this->assertTrue( $prompt->claim_email() );
	}

	public function test_only_one_of_two_simultaneous_emails_wins_the_claim(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );

		$GLOBALS['wpdb']->before_update = function () {
			$this->options[ ReviewPrompt::OPTION ]['emailed_at'] = time();
		};

		$this->assertFalse( $prompt->claim_email() );
	}

	public function test_a_failed_send_hands_the_claim_back(): void {
		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );

		$this->assertTrue( $prompt->claim_email() );

		$prompt->release_email();

		$this->assertTrue( $prompt->claim_email() );
	}

	public function test_filter_can_veto_the_request(): void {
		Filters\expectApplied( 'cart_rebound_show_review_prompt' )->andReturn( false );

		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );

		$this->assertFalse( $prompt->is_due() );
		$this->assertFalse( $prompt->claim_email() );
	}

	public function test_filter_cannot_override_a_dismissal(): void {
		Filters\expectApplied( 'cart_rebound_show_review_prompt' )->andReturn( true );

		$prompt = new ReviewPrompt();
		$prompt->record_recovery( $this->order( 10, '1' ) );
		$prompt->respond( ReviewPrompt::CHOICE_DISMISS );

		$this->assertFalse( $prompt->is_due() );
		$this->assertNull( $prompt->for_admin() );
	}

	private function order( float $total, string $number ): WC_Order {
		$order           = new WC_Order();
		$order->total    = $total;
		$order->currency = 'USD';
		$order->number   = $number;

		return $order;
	}
}

// phpcs:disable -- lightweight test fixture.

final class ReviewPromptWpdb {

	/** @var string */
	public $options = 'wp_options';

	/** @var string */
	public $prefix = 'wp_';

	/** @var int Rows the fake reports for any COUNT(*) query. */
	public $recovered = 3;

	/** @var callable|null */
	public $before_update;

	/** @var ReviewPromptTest */
	private $test;

	public function __construct( ReviewPromptTest $test ) {
		$this->test = $test;
	}

	public function prepare( string $query, ...$args ): string {
		$args = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : $args;

		return vsprintf( str_replace( array( '%s', '%i' ), array( "'%s'", '`%s`' ), $query ), $args );
	}

	public function get_var( string $query ) {
		if ( false !== strpos( $query, 'COUNT(*)' ) ) {
			return (string) $this->recovered;
		}

		preg_match( "/option_name = '([^']+)'/", $query, $match );

		return array_key_exists( $match[1], $this->test->options ) ? serialize( $this->test->options[ $match[1] ] ) : null;
	}

	public function update( string $table, array $data, array $where ) {
		if ( $this->before_update ) {
			( $this->before_update )();
		}

		$name = $where['option_name'];

		if ( ! array_key_exists( $name, $this->test->options ) || serialize( $this->test->options[ $name ] ) !== $where['option_value'] ) {
			return 0;
		}

		$this->test->options[ $name ] = unserialize( $data['option_value'] );

		return 1;
	}
}
