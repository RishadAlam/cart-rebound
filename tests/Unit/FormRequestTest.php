<?php
/**
 * FormRequest unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use Brain\Monkey\Functions;
use CartRebound\Http\Requests\FormRequest;
use CartRebound\Tests\TestCase;
use WP_Error;
use WP_REST_Request;

/**
 * @covers \CartRebound\Http\Requests\FormRequest
 */
final class FormRequestTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	private function request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request();

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	public function test_missing_required_field_fails_with_422(): void {
		$result = ( new TitleRequest( $this->request( array() ) ) )->validate();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	public function test_valid_input_returns_and_caches_sanitised_values(): void {
		$form = new TitleRequest( $this->request( array( 'title' => 'Hello' ) ) );

		$this->assertSame( array( 'title' => 'Hello' ), $form->validate() );
		$this->assertSame( array( 'title' => 'Hello' ), $form->validated() );
	}

	public function test_min_rule_does_not_reject_non_string_values(): void {
		// An integer under 'min:3' must not be measured as a 1-char string.
		$result = ( new AgeRequest( $this->request( array( 'age' => 5 ) ) ) )->validate();

		$this->assertSame( array( 'age' => 5 ), $result );
	}

	public function test_min_rule_enforced_for_strings(): void {
		$result = ( new TitleMinRequest( $this->request( array( 'title' => 'ab' ) ) ) )->validate();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_max_rule_enforced_for_arrays_by_count(): void {
		$result = ( new TagsRequest( $this->request( array( 'tags' => array( 'a', 'b', 'c' ) ) ) ) )->validate();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_failed_authorize_returns_403_even_after_a_prior_pass(): void {
		$form = new ToggleableRequest( $this->request( array( 'title' => 'Hi' ) ) );

		ToggleableRequest::$allowed = true;
		$this->assertSame( array( 'title' => 'Hi' ), $form->validate() );

		// Authorization revoked: a repeat call must re-check and deny, not serve cache.
		ToggleableRequest::$allowed = false;
		$denied = $form->validate();

		$this->assertInstanceOf( WP_Error::class, $denied );
		$this->assertSame( 403, $denied->get_error_data()['status'] );
	}
}

// phpcs:disable -- lightweight test fixtures.

class TitleRequest extends FormRequest {
	public function rules(): array {
		return array( 'title' => 'required|string' );
	}
}

class TitleMinRequest extends FormRequest {
	public function rules(): array {
		return array( 'title' => 'required|string|min:3' );
	}
}

class AgeRequest extends FormRequest {
	public function rules(): array {
		return array( 'age' => 'required|integer|min:3' );
	}
}

class TagsRequest extends FormRequest {
	public function rules(): array {
		return array( 'tags' => 'required|max:2' );
	}
}

class ToggleableRequest extends FormRequest {
	public static $allowed = true;

	public function authorize(): bool {
		return self::$allowed;
	}

	public function rules(): array {
		return array( 'title' => 'required|string' );
	}
}
