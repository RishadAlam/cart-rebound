<?php
/**
 * One step of a cart's follow-up plan.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Followup;

defined( 'ABSPATH' ) || exit;

/**
 * A single scheduled follow-up: when to send, which template, and any extra
 * data the extension that produced it wants carried through to render time.
 *
 * Delays are measured from the moment the cart was abandoned, not from the
 * previous send. A step that runs late (a stalled queue, a failed transport)
 * therefore cannot push every later step out with it, and the merchant's
 * "1h / 24h / 72h" reads as the wall-clock schedule it looks like.
 *
 * @since 1.1.0
 */
final class Step {

	/**
	 * Position in the plan, zero-based.
	 *
	 * @since 1.1.0
	 * @var int
	 */
	private $index;

	/**
	 * Minutes after abandonment at which this step is due.
	 *
	 * @since 1.1.0
	 * @var int
	 */
	private $delay_minutes;

	/**
	 * Template id to render, or '' for the default template.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $template_id;

	/**
	 * Arbitrary extension payload carried to render time.
	 *
	 * @since 1.1.0
	 * @var array<string, mixed>
	 */
	private $meta;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $index         Zero-based position in the plan.
	 * @param int                  $delay_minutes Minutes after abandonment.
	 * @param string               $template_id   Template id, or '' for the default.
	 * @param array<string, mixed> $meta          Extension payload.
	 */
	public function __construct( int $index, int $delay_minutes, string $template_id = '', array $meta = array() ) {
		$this->index         = max( 0, $index );
		$this->delay_minutes = max( 1, $delay_minutes );
		$this->template_id   = $template_id;
		$this->meta          = $meta;
	}

	/**
	 * Get the zero-based position in the plan.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	public function index(): int {
		return $this->index;
	}

	/**
	 * Get the delay, in minutes after abandonment.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	public function delay_minutes(): int {
		return $this->delay_minutes;
	}

	/**
	 * Get the template id ('' means the default template).
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function template_id(): string {
		return $this->template_id;
	}

	/**
	 * Get the whole extension payload.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		return $this->meta;
	}

	/**
	 * Read one key from the extension payload.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key      Payload key.
	 * @param mixed  $fallback Value when the key is absent.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : $fallback;
	}

	/**
	 * Copy this step with a different position.
	 *
	 * Used by {@see Plan} so a plan's steps are always numbered contiguously
	 * from zero, whatever indexes the extension that built them supplied.
	 *
	 * @since 1.1.0
	 *
	 * @param int $index New zero-based position.
	 * @return Step
	 */
	public function with_index( int $index ): Step {
		return new self( $index, $this->delay_minutes, $this->template_id, $this->meta );
	}

	/**
	 * Copy this step with additional payload merged in.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $meta Payload to merge over the current one.
	 * @return Step
	 */
	public function with_meta( array $meta ): Step {
		return new self( $this->index, $this->delay_minutes, $this->template_id, array_merge( $this->meta, $meta ) );
	}

	/**
	 * Represent the step as a plain array (logging, events, tests).
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'index'         => $this->index,
			'delay_minutes' => $this->delay_minutes,
			'template_id'   => $this->template_id,
			'meta'          => $this->meta,
		);
	}
}
