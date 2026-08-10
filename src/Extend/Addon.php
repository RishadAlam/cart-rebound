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
 * Note what is absent: this plugin has no concept of a licence. An add-on says
 * which features it is delivering right now and where its own screen lives.
 * Whether it is delivering nothing because a key expired, a trial ended, or a
 * setting is off is a question this plugin neither asks nor could answer.
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
	 * Where the add-on's own admin screen lives.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $settings_url;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param string             $slug     Unique slug.
	 * @param string             $name     Display name.
	 * @param string             $version  Version string.
	 * @param string             $url          Product URL.
	 * @param array<int, string> $features     Feature keys it is currently delivering.
	 * @param string             $settings_url The add-on's own admin screen.
	 */
	private function __construct( string $slug, string $name, string $version, string $url, array $features, string $settings_url ) {
		$this->slug         = $slug;
		$this->name         = $name;
		$this->version      = $version;
		$this->url          = $url;
		$this->features     = $features;
		$this->settings_url = $settings_url;
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
			esc_url_raw( (string) ( $data['settings_url'] ?? '' ) )
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
	 * Get the feature keys this add-on is currently delivering.
	 *
	 * An add-on that is installed but not currently delivering — however it
	 * decides that — reports an empty list, and the matching screens stay
	 * locked. Why it is not delivering is the add-on's business to explain on
	 * its own screen, which is what {@see settings_url()} points at.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function features(): array {
		return $this->features;
	}

	/**
	 * Where the add-on's own admin screen lives.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function settings_url(): string {
		return $this->settings_url;
	}

	/**
	 * Whether the add-on is delivering anything at all right now.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_delivering(): bool {
		return array() !== $this->features;
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
			'slug'         => $this->slug,
			'name'         => $this->name,
			'version'      => $this->version,
			'url'          => $this->url,
			'features'     => $this->features,
			'settings_url' => $this->settings_url,
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
