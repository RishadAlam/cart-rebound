<?php
/**
 * Review prompt answer request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Requests;

defined( 'ABSPATH' ) || exit;

use CartRebound\Admin\ReviewPrompt;

/**
 * Validates the store manager's answer to the review request.
 *
 * @since 1.1.3
 */
final class ReviewPromptRequest extends FormRequest {

	/**
	 * Validation rules.
	 *
	 * @since 1.1.3
	 *
	 * @return array<string, string>
	 */
	public function rules(): array {
		return array(
			'choice' => 'required|string|in:' . implode( ',', ReviewPrompt::CHOICES ),
		);
	}
}
