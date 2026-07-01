<?php
/**
 * Form request unit tests for the plugin's requests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Http\Requests\BulkActionRequest;
use CartRebound\Http\Requests\CaptureEmailRequest;
use CartRebound\Http\Requests\MarkRecoveredRequest;
use CartRebound\Http\Requests\UpdateStatusRequest;
use CartRebound\Tests\TestCase;
use WP_Error;
use WP_REST_Request;

/**
 * @covers \CartRebound\Http\Requests\BulkActionRequest
 * @covers \CartRebound\Http\Requests\CaptureEmailRequest
 * @covers \CartRebound\Http\Requests\MarkRecoveredRequest
 * @covers \CartRebound\Http\Requests\UpdateStatusRequest
 */
final class CartRequestsTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'is_email' )->alias(
			static function ( $value ) {
				return false !== strpos( (string) $value, '@' ) ? $value : false;
			}
		);
	}

	private function request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request();

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	public function test_mark_recovered_requires_both_ids(): void {
		$result = ( new MarkRecoveredRequest( $this->request( array( 'id' => 5 ) ) ) )->validate();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	public function test_mark_recovered_accepts_valid_ids(): void {
		$result = ( new MarkRecoveredRequest(
			$this->request(
				array(
					'id'       => '5',
					'order_id' => '12',
				)
			)
		) )->validate();

		$this->assertSame(
			array(
				'id'       => 5,
				'order_id' => 12,
			),
			$result
		);
	}

	public function test_update_status_requires_id_and_status(): void {
		$result = ( new UpdateStatusRequest( $this->request( array( 'id' => 5 ) ) ) )->validate();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	public function test_update_status_accepts_valid_payload(): void {
		$result = ( new UpdateStatusRequest(
			$this->request(
				array(
					'id'     => '7',
					'status' => 'lost',
				)
			)
		) )->validate();

		$this->assertSame(
			array(
				'id'     => 7,
				'status' => 'lost',
			),
			$result
		);
	}

	public function test_bulk_action_requires_action(): void {
		$result = ( new BulkActionRequest( $this->request( array() ) ) )->validate();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	public function test_bulk_action_accepts_action(): void {
		$result = ( new BulkActionRequest( $this->request( array( 'action' => 'delete' ) ) ) )->validate();

		$this->assertSame( array( 'action' => 'delete' ), $result );
	}

	public function test_capture_email_rejects_invalid_email(): void {
		$result = ( new CaptureEmailRequest( $this->request( array( 'email' => 'not-an-email' ) ) ) )->validate();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_capture_email_accepts_valid_payload(): void {
		$result = ( new CaptureEmailRequest(
			$this->request(
				array(
					'email'      => 'a@b.com',
					'first_name' => 'Ann',
				)
			)
		) )->validate();

		$this->assertSame( 'a@b.com', $result['email'] );
		$this->assertSame( 'Ann', $result['first_name'] );
	}
}
