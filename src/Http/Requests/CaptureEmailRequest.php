<?php
/**
 * Guest identity capture request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Validates the identity fields a guest types at checkout.
 *
 * @since 0.1.0
 */
final class CaptureEmailRequest extends FormRequest {

	/**
	 * Validation rules.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string>
	 */
	public function rules(): array {
		return array(
			'email'      => 'email|max:100',
			'first_name' => 'string|max:100',
			'last_name'  => 'string|max:100',
			'phone'      => 'string|max:40',
		);
	}
}
