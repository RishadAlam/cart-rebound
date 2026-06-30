<?php
/**
 * Settings update request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Validates inbound settings. Final sanitisation happens in the Settings store.
 *
 * @since 0.1.0
 */
final class UpdateSettingsRequest extends FormRequest {

	/**
	 * Validation rules.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string>
	 */
	public function rules(): array {
		return array(
			'enabled'                => 'boolean',
			'guest_tracking'         => 'boolean',
			'abandonment_threshold'  => 'integer',
			'scan_interval'          => 'integer',
			'cleanup_days'           => 'integer',
			'recovery_email_enabled' => 'boolean',
			'email_delay_minutes'    => 'integer',
			'email_subject'          => 'string|max:200',
			'email_body'             => 'string|max:2000',
			'email_from_name'        => 'string|max:100',
			'email_from_email'       => 'email|max:100',
		);
	}
}
