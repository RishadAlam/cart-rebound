<?php
/**
 * Abstract response transformer.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Resources;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for transforming models into API-shaped arrays.
 *
 * Named JsonResource (rather than Resource) because `resource` is a soft
 * reserved word in PHP.
 *
 * @since 0.1.0
 */
abstract class JsonResource {

	/**
	 * The underlying item (model, array, object, ...).
	 *
	 * @since 0.1.0
	 * @var mixed
	 */
	protected $item;

	/**
	 * Constructor.
	 *
	 * Marked final so {@see make()} and {@see collection()} can safely use
	 * `new static()`.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $item The item to transform.
	 */
	final public function __construct( $item ) {
		$this->item = $item;
	}

	/**
	 * Transform the item into an associative array.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	abstract public function to_array(): array;

	/**
	 * Create a single resource instance.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $item The item to transform.
	 * @return static
	 */
	public static function make( $item ) {
		return new static( $item );
	}

	/**
	 * Transform a list of items.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, mixed> $items The items to transform.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collection( array $items ): array {
		$resources = array();

		foreach ( $items as $item ) {
			$resources[] = ( new static( $item ) )->to_array();
		}

		return $resources;
	}
}
