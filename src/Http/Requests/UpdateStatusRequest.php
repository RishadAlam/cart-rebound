<?php
/**
 * Manual status-change request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Validates the cart id (URL) + target status (body) for a manual status change.
 *
 * The status vocabulary itself is enforced in the controller against
 * {@see \CartRebound\Models\CartSession::STATUSES}.
 *
 * @since 0.1.0
 */
final class UpdateStatusRequest extends FormRequest {

	/**
	 * Validation rules.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string>
	 */
	public function rules(): array {
		return array(
			'id'     => 'required|integer',
			'status' => 'required|string',
		);
	}
}
