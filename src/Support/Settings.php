<?php
/**
 * Plugin settings store.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Typed getter/updater over a single `cart_rebound_settings` option.
 *
 * The framework ships no settings primitive (its Config layer reads PHP config
 * files, not user options), so this is a thin, defaulted wrapper around
 * get_option()/update_option() with per-field sanitisation.
 *
 * @since 0.1.0
 */
final class Settings {

	/**
	 * Option key holding the settings array.
	 *
	 * @var string
	 */
	public const OPTION = 'cart_rebound_settings';

	/**
	 * Default values for every setting.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'guest_tracking'         => false,
			'abandonment_threshold'  => 30,
			'scan_interval'          => 5,
			'cleanup_days'           => 30,
			'converted_cleanup_days' => 365,
			'recovery_email_enabled' => false,
			'admin_recovery_email'   => false,
			'paid_order_statuses'    => array( 'processing', 'completed' ),
			'email_delay_minutes'    => 60,
			'email_subject'          => __( 'You left something in your cart', 'cart-rebound' ),
			'email_body'             => __( 'Hi {first_name}, your cart is still waiting: {products} {recovery_url}', 'cart-rebound' ),
			'email_from_name'        => '',
			'email_from_email'       => '',
			'email_coupon'           => '',
			'onboarding_complete'    => false,
		);
	}

	/**
	 * Get all settings merged over the defaults.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( $this->defaults(), $stored );
	}

	/**
	 * Get a single setting.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value when the key is unknown.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		$all = $this->all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Merge, sanitise, and persist settings.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $values Partial settings to write.
	 * @return array<string, mixed> The full, sanitised settings.
	 */
	public function update( array $values ): array {
		$merged    = array_merge( $this->all(), $values );
		$sanitised = $this->sanitise( $merged );

		update_option( self::OPTION, $sanitised, false );

		return $sanitised;
	}

	/**
	 * Sanitise a full settings array by field type.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $values Raw settings.
	 * @return array<string, mixed>
	 */
	private function sanitise( array $values ): array {
		return array(
			'guest_tracking'         => ! empty( $values['guest_tracking'] ),
			'abandonment_threshold'  => max( 1, (int) ( $values['abandonment_threshold'] ?? 30 ) ),
			'scan_interval'          => max( 1, (int) ( $values['scan_interval'] ?? 5 ) ),
			'cleanup_days'           => max( 1, (int) ( $values['cleanup_days'] ?? 30 ) ),
			'converted_cleanup_days' => max( 1, (int) ( $values['converted_cleanup_days'] ?? 365 ) ),
			'recovery_email_enabled' => ! empty( $values['recovery_email_enabled'] ),
			'admin_recovery_email'   => ! empty( $values['admin_recovery_email'] ),
			'paid_order_statuses'    => $this->sanitise_statuses( $values['paid_order_statuses'] ?? array() ),
			'email_delay_minutes'    => max( 1, (int) ( $values['email_delay_minutes'] ?? 60 ) ),
			'email_subject'          => sanitize_text_field( (string) ( $values['email_subject'] ?? '' ) ),
			'email_body'             => sanitize_textarea_field( (string) ( $values['email_body'] ?? '' ) ),
			'email_from_name'        => sanitize_text_field( (string) ( $values['email_from_name'] ?? '' ) ),
			'email_from_email'       => sanitize_email( (string) ( $values['email_from_email'] ?? '' ) ),
			'email_coupon'           => sanitize_text_field( (string) ( $values['email_coupon'] ?? '' ) ),
			'onboarding_complete'    => ! empty( $values['onboarding_complete'] ),
		);
	}

	/**
	 * Sanitise the "counts as paid" WooCommerce order-status list.
	 *
	 * Falls back to WooCommerce's paid defaults when nothing valid is supplied so
	 * a converting cart can always be attributed.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value Raw status list.
	 * @return array<int, string>
	 */
	private function sanitise_statuses( $value ): array {
		$statuses = is_array( $value ) ? $value : array();
		$clean    = array();

		foreach ( $statuses as $status ) {
			$key = sanitize_key( (string) $status );

			if ( '' !== $key && ! in_array( $key, $clean, true ) ) {
				$clean[] = $key;
			}
		}

		return array() === $clean ? array( 'processing', 'completed' ) : $clean;
	}
}
