<?php
/**
 * Store branding helpers.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the store's own logo and accent colour so shopper-facing pages and emails match the site.
 *
 * @since 1.1.3
 */
final class Branding {

	/**
	 * Accent used when the theme exposes no usable colour.
	 *
	 * @var string
	 */
	public const FALLBACK_ACCENT = '#2563eb';

	/**
	 * The store name, entity-decoded for plain-text and re-escaped contexts.
	 *
	 * @since 1.1.3
	 *
	 * @return string
	 */
	public static function store_name(): string {
		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * The site logo, else the site icon.
	 *
	 * @since 1.1.3
	 *
	 * @return array{url: string, is_icon: bool} Empty url when the site has neither.
	 */
	public static function logo(): array {
		$id = (int) get_theme_mod( 'custom_logo' );

		if ( $id <= 0 ) {
			$id = (int) get_option( 'site_logo' );
		}

		$url = $id > 0 ? wp_get_attachment_image_url( $id, 'medium' ) : false;

		if ( is_string( $url ) && '' !== $url ) {
			return array(
				'url'     => $url,
				'is_icon' => false,
			);
		}

		return array(
			'url'     => (string) get_site_icon_url( 96 ),
			'is_icon' => true,
		);
	}

	/**
	 * The theme's button colours, else its link colour, else the WooCommerce email colour.
	 *
	 * @since 1.1.3
	 *
	 * @return array{accent: string, ink: string} Hex background and readable text colour.
	 */
	public static function colors(): array {
		$accent = '';
		$ink    = '';

		if ( function_exists( 'wp_get_global_styles' ) ) {
			$accent = self::resolve( wp_get_global_styles( array( 'elements', 'button', 'color', 'background' ) ) );
			$ink    = '' !== $accent ? self::resolve( wp_get_global_styles( array( 'elements', 'button', 'color', 'text' ) ) ) : '';

			if ( '' === $accent ) {
				$accent = self::resolve( wp_get_global_styles( array( 'elements', 'link', 'color', 'text' ) ) );
			}
		}

		if ( '' === $accent ) {
			$accent = self::resolve( get_option( 'woocommerce_email_base_color', '' ) );
		}

		if ( '' === $accent ) {
			$accent = self::FALLBACK_ACCENT;
		}

		if ( '' === $ink || self::contrast( $accent, $ink ) < 4.5 ) {
			$ink = self::readable_on( $accent );
		}

		return array(
			'accent' => $accent,
			'ink'    => $ink,
		);
	}

	/**
	 * White or near-black, whichever reads better on a background.
	 *
	 * @since 1.1.3
	 *
	 * @param string $background Hex colour.
	 * @return string
	 */
	public static function readable_on( string $background ): string {
		return self::contrast( $background, '#ffffff' ) >= self::contrast( $background, '#111111' ) ? '#ffffff' : '#111111';
	}

	/**
	 * Turn a theme.json colour value (hex or preset reference) into a hex colour.
	 *
	 * @since 1.1.3
	 *
	 * @param mixed $value Raw value such as "#111", "var(--wp--preset--color--contrast)" or "var:preset|color|contrast".
	 * @return string Hex colour, or '' when it cannot be resolved.
	 */
	private static function resolve( $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		$hex = sanitize_hex_color( $value );

		if ( is_string( $hex ) && '' !== $hex ) {
			return $hex;
		}

		if ( ! preg_match( '/(?:--wp--preset--color--|var:preset\|color\|)([a-z0-9-]+)/i', $value, $match ) || ! function_exists( 'wp_get_global_settings' ) ) {
			return '';
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );

		foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
			foreach ( (array) ( $palette[ $origin ] ?? array() ) as $entry ) {
				if ( is_array( $entry ) && ( $entry['slug'] ?? '' ) === $match[1] ) {
					return self::resolve( $entry['color'] ?? '' );
				}
			}
		}

		return '';
	}

	/**
	 * WCAG contrast ratio between two hex colours.
	 *
	 * @since 1.1.3
	 *
	 * @param string $first  Hex colour.
	 * @param string $second Hex colour.
	 * @return float
	 */
	private static function contrast( string $first, string $second ): float {
		$a = self::luminance( $first );
		$b = self::luminance( $second );

		return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	}

	/**
	 * Relative luminance of a hex colour.
	 *
	 * @since 1.1.3
	 *
	 * @param string $hex Hex colour (#rgb or #rrggbb).
	 * @return float
	 */
	private static function luminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$channels = array_map(
			static function ( string $pair ): float {
				$value = hexdec( $pair ) / 255;

				return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
			},
			str_split( str_pad( substr( $hex, 0, 6 ), 6, '0' ), 2 )
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}
