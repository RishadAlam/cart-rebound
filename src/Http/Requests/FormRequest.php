<?php
/**
 * Abstract validating form request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Requests;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;

/**
 * Base class for sanitising + validating REST input.
 *
 * Subclasses declare {@see rules()} and optionally override {@see authorize()}.
 * Controllers call {@see validate()} and receive either a clean associative
 * array of sanitised values or a WP_Error carrying field-level messages.
 *
 * @since 0.1.0
 */
abstract class FormRequest {

	/**
	 * The current REST request.
	 *
	 * @since 0.1.0
	 * @var WP_REST_Request
	 */
	protected $request;

	/**
	 * Cached sanitised values after a successful validation pass.
	 *
	 * @since 0.1.0
	 * @var array<string, mixed>|null
	 */
	protected $validated;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The current REST request.
	 */
	public function __construct( WP_REST_Request $request ) {
		$this->request = $request;
	}

	/**
	 * Validation rules keyed by field, e.g. `[ 'email' => 'required|email' ]`.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string>
	 */
	abstract public function rules(): array;

	/**
	 * Authorise the request. Override to add capability checks.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function authorize(): bool {
		return true;
	}

	/**
	 * Validate and sanitise the request input.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>|WP_Error Sanitised values, or a WP_Error on failure.
	 */
	public function validate() {
		// Authorization is re-checked on every call (never cached), so a reused
		// request instance cannot return previously-validated data after the
		// caller's permission has changed.
		if ( ! $this->authorize() ) {
			return new WP_Error(
				'cart_rebound_forbidden',
				__( 'You do not have permission to perform this action.', 'cart-rebound' ),
				array( 'status' => 403 )
			);
		}

		if ( null !== $this->validated ) {
			return $this->validated;
		}

		$validated = array();
		$errors    = array();

		foreach ( $this->rules() as $field => $definition ) {
			$rules      = explode( '|', $definition );
			$value      = $this->request->get_param( $field );
			$is_present = null !== $value && '' !== $value;

			if ( ! $is_present ) {
				if ( in_array( 'required', $rules, true ) ) {
					/* translators: %s: form field name. */
					$errors[ $field ][] = sprintf( __( 'The %s field is required.', 'cart-rebound' ), $field );
				}
				continue;
			}

			foreach ( $rules as $rule ) {
				$message = $this->check_rule( $rule, $field, $value );

				if ( null !== $message ) {
					$errors[ $field ][] = $message;
				}
			}

			$validated[ $field ] = $this->sanitize( $rules, $value );
		}

		if ( array() !== $errors ) {
			return new WP_Error(
				'cart_rebound_validation_failed',
				__( 'The submitted data is invalid.', 'cart-rebound' ),
				array(
					'status' => 422,
					'errors' => $errors,
				)
			);
		}

		$this->validated = $validated;

		return $validated;
	}

	/**
	 * Get the sanitised values from a successful validation pass.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The validated values, or an empty array if validation has not passed.
	 */
	public function validated(): array {
		return $this->validated ?? array();
	}

	/**
	 * Check a single rule against a value.
	 *
	 * @since 0.1.0
	 *
	 * @param string $rule  Rule name, optionally with a `:argument`.
	 * @param string $field Field name (for messages).
	 * @param mixed  $value The value being validated.
	 * @return string|null Error message, or null when the value passes.
	 */
	private function check_rule( string $rule, string $field, $value ): ?string {
		if ( 'string' === $rule && ! is_string( $value ) ) {
			/* translators: %s: form field name. */
			return sprintf( __( 'The %s field must be a string.', 'cart-rebound' ), $field );
		}

		if ( 'integer' === $rule && false === filter_var( $value, FILTER_VALIDATE_INT ) ) {
			/* translators: %s: form field name. */
			return sprintf( __( 'The %s field must be an integer.', 'cart-rebound' ), $field );
		}

		if ( 'email' === $rule && ! is_email( (string) $value ) ) {
			/* translators: %s: form field name. */
			return sprintf( __( 'The %s field must be a valid email address.', 'cart-rebound' ), $field );
		}

		if ( 'url' === $rule && false === wp_http_validate_url( (string) $value ) ) {
			/* translators: %s: form field name. */
			return sprintf( __( 'The %s field must be a valid URL.', 'cart-rebound' ), $field );
		}

		$length = $this->length_of( $value );

		if ( null !== $length && 0 === strpos( $rule, 'max:' ) ) {
			$max = (int) substr( $rule, 4 );

			if ( $length > $max ) {
				/* translators: 1: form field name, 2: maximum length. */
				return sprintf( __( 'The %1$s field may not be longer than %2$d characters.', 'cart-rebound' ), $field, $max );
			}
		}

		if ( null !== $length && 0 === strpos( $rule, 'min:' ) ) {
			$min = (int) substr( $rule, 4 );

			if ( $length < $min ) {
				/* translators: 1: form field name, 2: minimum length. */
				return sprintf( __( 'The %1$s field must be at least %2$d characters.', 'cart-rebound' ), $field, $min );
			}
		}

		return null;
	}

	/**
	 * Measure a value's length for min/max rules.
	 *
	 * Strings are measured by character length and arrays by element count.
	 * Other types (int, float, bool) are not length-constrained — use the
	 * `integer`/`numeric` rules for those — so they return null and skip the
	 * check rather than being measured by a surprising string-cast length.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value The value being measured.
	 * @return int|null The measurable length, or null when the type has no length.
	 */
	private function length_of( $value ): ?int {
		if ( is_string( $value ) ) {
			return strlen( $value );
		}

		if ( is_array( $value ) ) {
			return count( $value );
		}

		return null;
	}

	/**
	 * Sanitise a value according to its rules.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string> $rules The rules applied to this field.
	 * @param mixed              $value The raw value.
	 * @return mixed The sanitised value.
	 */
	private function sanitize( array $rules, $value ) {
		if ( in_array( 'email', $rules, true ) ) {
			return sanitize_email( (string) $value );
		}

		if ( in_array( 'url', $rules, true ) ) {
			return esc_url_raw( (string) $value );
		}

		if ( in_array( 'integer', $rules, true ) ) {
			return (int) $value;
		}

		if ( in_array( 'numeric', $rules, true ) ) {
			return (float) $value;
		}

		if ( in_array( 'boolean', $rules, true ) ) {
			return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		return $value;
	}
}
