<?php
/**
 * Bulk cart action request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Validates the action name for a bulk operation on the cart list.
 *
 * The `ids` list and the (action-dependent) `status` are validated in the
 * controller: `ids` because {@see FormRequest} has no array rule, and `status`
 * because it is only required for the `status` action.
 *
 * @since 0.1.0
 */
final class BulkActionRequest extends FormRequest {

	/**
	 * Validation rules.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string>
	 */
	public function rules(): array {
		return array(
			'action' => 'required|string',
		);
	}
}
