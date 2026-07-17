<?php
/**
 * Email template create/update request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Validates the required fields of an email template.
 *
 * Only `name` and `subject` are validated here; the HTML `body` and the other
 * optional fields are read raw and sanitised by the TemplateStore (the body
 * must keep its markup, which a string rule would strip).
 *
 * @since 0.1.0
 */
final class EmailTemplateRequest extends FormRequest {

	/**
	 * Validation rules.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string>
	 */
	public function rules(): array {
		return array(
			'name'    => 'required|string|max:120',
			'subject' => 'required|string|max:200',
		);
	}
}
