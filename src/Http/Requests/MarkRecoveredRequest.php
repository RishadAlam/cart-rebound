<?php
/**
 * Manual mark-recovered request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Validates the cart id (URL) + order id (body) for a manual reconciliation.
 *
 * @since 0.1.0
 */
final class MarkRecoveredRequest extends FormRequest {

	/**
	 * Validation rules.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string>
	 */
	public function rules(): array {
		return array(
			'id'       => 'required|integer',
			'order_id' => 'required|integer',
		);
	}
}
