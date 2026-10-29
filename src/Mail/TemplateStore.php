<?php
/**
 * Email template store.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Mail;

defined( 'ABSPATH' ) || exit;

use CartRebound\Support\Settings;

/**
 * CRUD store for the recovery-email templates kept in a single option.
 *
 * @since 0.1.0
 */
final class TemplateStore {

	/**
	 * Option key holding the list of templates.
	 *
	 * @var string
	 */
	public const OPTION = 'cart_rebound_email_templates';

	/**
	 * Columns the {products_table} tag can render, in canonical order.
	 *
	 * @var array<int, string>
	 */
	public const TABLE_COLUMNS = array( 'image', 'name', 'sku', 'quantity', 'price', 'subtotal' );

	/**
	 * Table skins the {products_table} tag can render with.
	 *
	 * @var array<int, string>
	 */
	public const TABLE_STYLES = array( 'lined', 'boxed', 'plain' );

	/**
	 * Product thumbnail sizes, in pixels.
	 *
	 * @var array<int, int>
	 */
	public const TABLE_IMAGE_SIZES = array( 32, 48, 64 );

	/**
	 * Settings store (source of the seeded default template).
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get every template. Seeds and persists a default on first access.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, null );

		if ( ! is_array( $stored ) || array() === $stored ) {
			return $this->seed_and_persist();
		}

		$templates = array();
		$upgraded  = false;

		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$template = $this->normalise( $row );

			if ( $this->is_untouched_legacy_seed( $template ) ) {
				$template = array_merge( $template, $this->starter() );
				$upgraded = true;
			}

			$templates[] = $template;
		}

		if ( array() === $templates ) {
			return $this->seed_and_persist();
		}

		$templates = $this->ensure_one_default( $templates );

		if ( $upgraded ) {
			$this->save( $templates );
		}

		return $templates;
	}

	/**
	 * Get a single template by id.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Template id.
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ) {
		return $this->by_id( $this->all(), $id );
	}

	/**
	 * Get the default template (drives automatic abandonment emails).
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function default() {
		$templates = $this->all();

		foreach ( $templates as $template ) {
			if ( ! empty( $template['is_default'] ) ) {
				return $template;
			}
		}

		return $templates[0] ?? $this->seed();
	}

	/**
	 * Create a template.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $data Raw template data.
	 * @return array<string, mixed> The stored template.
	 */
	public function create( array $data ): array {
		$templates = $this->all();

		$template       = $this->sanitise( $data );
		$template['id'] = $this->new_id();

		$default_id = empty( $data['is_default'] ) ? $this->default_id( $templates ) : $template['id'];

		$templates[] = $template;
		$templates   = $this->apply_default( $templates, $default_id );
		$this->save( $templates );

		return (array) $this->by_id( $templates, $template['id'] );
	}

	/**
	 * Update a template by id.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $id   Template id.
	 * @param array<string, mixed> $data Raw template data.
	 * @return array<string, mixed>|null The stored template, or null if unknown.
	 */
	public function update( string $id, array $data ) {
		$templates = $this->all();
		$found     = false;

		foreach ( $templates as $index => $template ) {
			if ( (string) $template['id'] === $id ) {
				$merged              = $this->sanitise( array_merge( $template, $data ) );
				$merged['id']        = $id;
				$templates[ $index ] = $merged;
				$found               = true;
			}
		}

		if ( ! $found ) {
			return null;
		}

		$default_id = empty( $data['is_default'] ) ? $this->default_id( $templates ) : $id;
		$templates  = $this->apply_default( $templates, $default_id );
		$this->save( $templates );

		return $this->by_id( $templates, $id );
	}

	/**
	 * Delete a template by id.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Template id.
	 * @return bool True when a template was deleted.
	 */
	public function delete( string $id ): bool {
		$templates = $this->all();
		$target    = $this->by_id( $templates, $id );

		if ( null === $target || ! empty( $target['is_default'] ) ) {
			return false;
		}

		$remaining = array();

		foreach ( $templates as $template ) {
			if ( (string) $template['id'] !== $id ) {
				$remaining[] = $template;
			}
		}

		$this->save( $this->apply_default( $remaining, $this->default_id( $remaining ) ) );

		return true;
	}

	/**
	 * Mark a template as the default, clearing the flag on the others.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Template id.
	 * @return bool
	 */
	public function set_default( string $id ): bool {
		$templates = $this->all();

		if ( null === $this->by_id( $templates, $id ) ) {
			return false;
		}

		$this->save( $this->apply_default( $templates, $id ) );

		return true;
	}

	/**
	 * Build the seeded "Default" template from the legacy flat settings.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	private function seed(): array {
		$defaults = $this->settings->defaults();
		$subject  = (string) $this->settings->get( 'email_subject' );
		$body     = (string) $this->settings->get( 'email_body' );
		$content  = $subject === $defaults['email_subject'] && $body === $defaults['email_body']
			? $this->starter()
			: array(
				'subject' => $subject,
				'body'    => wpautop( $body ),
			);

		return $this->sanitise(
			array_merge(
				array(
					'id'         => $this->new_id(),
					'name'       => __( 'Default', 'cart-rebound' ),
					'from_name'  => (string) $this->settings->get( 'email_from_name' ),
					'from_email' => (string) $this->settings->get( 'email_from_email' ),
					'coupon'     => (string) $this->settings->get( 'email_coupon' ),
					'is_default' => true,
				),
				$content
			)
		);
	}

	/**
	 * The starter subject, body and product table for a fresh default template.
	 *
	 * @since 1.1.3
	 *
	 * @return array<string, mixed>
	 */
	private function starter(): array {
		$paragraphs = array(
			'<h2>' . __( 'Your cart is still here', 'cart-rebound' ) . '</h2>',
			'<p>' . __( 'Hi {first_name},', 'cart-rebound' ) . '</p>',
			'<p>' . __( 'You left a few things in your cart at {store_name}. We saved them for you, so you can pick up where you stopped.', 'cart-rebound' ) . '</p>',
			'{products_table}',
			'<p>' . sprintf(
				/* translators: %s: the {coupon_code} merge tag wrapped in <strong>. */
				__( 'Use the code %s at checkout to save on this order.', 'cart-rebound' ),
				'<strong>{coupon_code}</strong>'
			) . '</p>',
			'<p>' . __( 'Questions about your order or delivery? Reply to this email and {manager_name} will help.', 'cart-rebound' ) . '</p>',
		);

		return array(
			'subject' => __( '{first_name}, your cart at {store_name} is saved', 'cart-rebound' ),
			'body'    => implode( "\n", $paragraphs ),
			'table'   => self::table_config(
				array_merge(
					self::table_defaults(),
					array(
						'enabled'        => true,
						'columns'        => array( 'image', 'name', 'quantity', 'subtotal' ),
						'show_total_row' => true,
					)
				)
			),
		);
	}

	/**
	 * Whether a stored template is still the pre-1.1.3 seed nobody edited.
	 *
	 * @since 1.1.3
	 *
	 * @param array<string, mixed> $template Normalised template.
	 * @return bool
	 */
	private function is_untouched_legacy_seed( array $template ): bool {
		$defaults = $this->settings->defaults();

		return $template['subject'] === $defaults['email_subject']
			&& trim( (string) $template['body'] ) === trim( wpautop( (string) $defaults['email_body'] ) )
			&& self::table_defaults() === $template['table'];
	}

	/**
	 * Seed a single default template and persist it.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function seed_and_persist(): array {
		$seeded = array( $this->seed() );
		update_option( self::OPTION, $seeded, false );

		return $seeded;
	}

	/**
	 * Sanitise raw template data into the stored shape.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private function sanitise( array $data ): array {
		return array(
			'id'         => isset( $data['id'] ) ? sanitize_text_field( (string) $data['id'] ) : '',
			'name'       => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'subject'    => sanitize_text_field( (string) ( $data['subject'] ?? '' ) ),
			'body'       => wp_kses_post( (string) ( $data['body'] ?? '' ) ),
			'from_name'  => sanitize_text_field( (string) ( $data['from_name'] ?? '' ) ),
			'from_email' => sanitize_email( (string) ( $data['from_email'] ?? '' ) ),
			'coupon'     => sanitize_text_field( (string) ( $data['coupon'] ?? '' ) ),
			'is_default' => ! empty( $data['is_default'] ),
			'table'      => self::table_config( $data['table'] ?? array() ),
		);
	}

	/**
	 * Default {products_table} rendering options.
	 *
	 * Templates stored before table options existed render with these, so changing them changes
	 * existing emails.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function table_defaults(): array {
		return array(
			'enabled'         => false,
			'style'           => 'lined',
			'columns'         => array( 'name', 'quantity', 'subtotal' ),
			'image_size'      => 48,
			'show_header'     => true,
			'with_tax'        => false,
			'link_items'      => true,
			'show_variations' => true,
			'show_total_row'  => false,
			'max_items'       => 0,
		);
	}

	/**
	 * Sanitise raw {products_table} options against the defaults.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $raw Raw option data.
	 * @return array<string, mixed>
	 */
	public static function table_config( $raw ): array {
		$defaults = self::table_defaults();
		$data     = is_array( $raw ) ? $raw : array();

		$columns = array();

		if ( isset( $data['columns'] ) && is_array( $data['columns'] ) ) {
			foreach ( $data['columns'] as $column ) {
				$column = sanitize_key( (string) $column );

				if ( in_array( $column, self::TABLE_COLUMNS, true ) && ! in_array( $column, $columns, true ) ) {
					$columns[] = $column;
				}
			}
		}

		$style      = sanitize_key( (string) ( $data['style'] ?? $defaults['style'] ) );
		$image_size = (int) ( $data['image_size'] ?? $defaults['image_size'] );
		$max_items  = isset( $data['max_items'] ) ? max( 0, (int) $data['max_items'] ) : 0;

		return array(
			'enabled'         => ! empty( $data['enabled'] ),
			'style'           => in_array( $style, self::TABLE_STYLES, true ) ? $style : $defaults['style'],
			'columns'         => array() !== $columns ? $columns : $defaults['columns'],
			'image_size'      => in_array( $image_size, self::TABLE_IMAGE_SIZES, true ) ? $image_size : $defaults['image_size'],
			'show_header'     => isset( $data['show_header'] ) ? ! empty( $data['show_header'] ) : $defaults['show_header'],
			'with_tax'        => ! empty( $data['with_tax'] ),
			'link_items'      => isset( $data['link_items'] ) ? ! empty( $data['link_items'] ) : $defaults['link_items'],
			'show_variations' => isset( $data['show_variations'] ) ? ! empty( $data['show_variations'] ) : $defaults['show_variations'],
			'show_total_row'  => ! empty( $data['show_total_row'] ),
			'max_items'       => $max_items,
		);
	}

	/**
	 * Normalise a stored row (fills any missing keys).
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @return array<string, mixed>
	 */
	private function normalise( array $row ): array {
		$template       = $this->sanitise( $row );
		$template['id'] = '' !== $template['id'] ? $template['id'] : $this->new_id();

		return $template;
	}

	/**
	 * Guarantee exactly one default in a list read from storage.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, array<string, mixed>> $templates Templates.
	 * @return array<int, array<string, mixed>>
	 */
	private function ensure_one_default( array $templates ): array {
		if ( array() === $templates ) {
			return $templates;
		}

		return $this->apply_default( $templates, $this->default_id( $templates ) );
	}

	/**
	 * Resolve which id should be the default (current default, else the first).
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, array<string, mixed>> $templates Templates.
	 * @return string
	 */
	private function default_id( array $templates ): string {
		foreach ( $templates as $template ) {
			if ( ! empty( $template['is_default'] ) ) {
				return (string) $template['id'];
			}
		}

		return isset( $templates[0]['id'] ) ? (string) $templates[0]['id'] : '';
	}

	/**
	 * Set is_default true on exactly the matching id.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, array<string, mixed>> $templates  Templates.
	 * @param string                           $default_id Id to flag default.
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_default( array $templates, string $default_id ): array {
		foreach ( $templates as $index => $template ) {
			$templates[ $index ]['is_default'] = ( (string) $template['id'] === $default_id );
		}

		return $templates;
	}

	/**
	 * Find a template by id within a list.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, array<string, mixed>> $templates Templates.
	 * @param string                           $id        Template id.
	 * @return array<string, mixed>|null
	 */
	private function by_id( array $templates, string $id ) {
		foreach ( $templates as $template ) {
			if ( (string) $template['id'] === $id ) {
				return $template;
			}
		}

		return null;
	}

	/**
	 * Persist a normalised list of templates.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, array<string, mixed>> $templates Templates.
	 * @return void
	 */
	private function save( array $templates ): void {
		update_option( self::OPTION, array_values( $templates ), false );
	}

	/**
	 * Generate a URL-safe unique id.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	private function new_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return md5( uniqid( (string) wp_rand( 0, PHP_INT_MAX ), true ) );
	}
}
