<?php
/**
 * A registered add-on.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Extend;

defined( 'ABSPATH' ) || exit;

/**
 * What one add-on tells the free plugin about itself.
 *
 * Constructed only through {@see from_array()}, which distrusts every field: an
 * add-on is third-party code, and the values here reach the admin screen and
 * decide what it unlocks. Unknown feature keys are dropped rather than passed
 * through, so a typo unlocks nothing instead of unlocking everything.
 *
 * @since 1.1.0
 */
final class Addon {

	/**
	 * Unique add-on slug.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $slug;

	/**
	 * Display name.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $name;

	/**
	 * Add-on version.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $version;

	/**
	 * Where to send someone who wants it (or wants to renew).
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $url;

	/**
	 * Recognised feature keys this add-on delivers.
	 *
	 * @since 1.1.0
	 * @var array<int, string>
	 */
	private $features;

	/**
	 * Whether the add-on's license is currently in good standing.
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	private $licensed;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param string             $slug     Unique slug.
	 * @param string             $name     Display name.
	 * @param string             $version  Version string.
	 * @param string             $url      Product/renewal URL.
	 * @param array<int, string> $features Recognised feature keys.
	 * @param bool               $licensed Whether the license is in good standing.
	 */
	private function __construct( string $slug, string $name, string $version, string $url, array $features, bool $licensed ) {
		$this->slug     = $slug;
		$this->name     = $name;
		$this->version  = $version;
		$this->url      = $url;
		$this->features = $features;
		$this->licensed = $licensed;
	}

	/**
	 * Build an add-on from an untrusted array, or null when it is unusable.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Raw add-on description.
	 * @return Addon|null
	 */
	public static function from_array( array $data ): ?Addon {
		$slug = sanitize_key( (string) ( $data['slug'] ?? '' ) );

		if ( '' === $slug ) {
			return null;
		}

		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );

		return new self(
			$slug,
			'' !== $name ? $name : $slug,
			sanitize_text_field( (string) ( $data['version'] ?? '' ) ),
			esc_url_raw( (string) ( $data['url'] ?? '' ) ),
			self::clean_features( $data['features'] ?? array() ),
			! empty( $data['licensed'] )
		);
	}

	/**
	 * Get the slug.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function slug(): string {
		return $this->slug;
	}

	/**
	 * Get the display name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Get the version.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function version(): string {
		return $this->version;
	}

	/**
	 * Get the product URL.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Get the feature keys this add-on claims.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function features(): array {
		return $this->features;
	}

	/**
	 * Whether the license is in good standing.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_licensed(): bool {
		return $this->licensed;
	}

	/**
	 * The feature keys this add-on is actually delivering right now.
	 *
	 * An unlicensed add-on delivers nothing, whatever it claims — which is what
	 * keeps the screens honest when a license lapses.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function active_features(): array {
		return $this->licensed ? $this->features : array();
	}

	/**
	 * Represent the add-on for the admin app.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'slug'     => $this->slug,
			'name'     => $this->name,
			'version'  => $this->version,
			'url'      => $this->url,
			'features' => $this->features,
			'licensed' => $this->licensed,
		);
	}

	/**
	 * Keep only recognised, de-duplicated feature keys.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $features Raw feature list.
	 * @return array<int, string>
	 */
	private static function clean_features( $features ): array {
		if ( ! is_array( $features ) ) {
			return array();
		}

		$clean = array();

		foreach ( $features as $feature ) {
			if ( ! is_scalar( $feature ) ) {
				continue;
			}

			$key = sanitize_key( (string) $feature );

			if ( Feature::exists( $key ) && ! in_array( $key, $clean, true ) ) {
				$clean[] = $key;
			}
		}

		return $clean;
	}
}
