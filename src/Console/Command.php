<?php
/**
 * Base WP-CLI command.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Console;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use WP_Filesystem_Base;

/**
 * Base class for WP-CLI commands, with container access and safe file writing.
 *
 * @since 0.1.0
 */
abstract class Command {

	/**
	 * The application instance.
	 *
	 * @since 0.1.0
	 * @var Application
	 */
	protected $app;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app The application instance.
	 */
	public function __construct( Application $app ) {
		$this->app = $app;
	}

	/**
	 * Validate that a string is a safe PHP class name.
	 *
	 * Rejects anything that is not a bare identifier (letters/digits, leading
	 * letter), which blocks path-traversal sequences (`/`, `\`, `.`, `..`) from
	 * reaching the generated file path.
	 *
	 * @since 0.1.0
	 *
	 * @param string $class_name The candidate class name.
	 * @return bool
	 */
	protected function is_valid_class_name( string $class_name ): bool {
		return 1 === preg_match( '/^[A-Za-z][A-Za-z0-9]*$/', $class_name );
	}

	/**
	 * Write a file through WP_Filesystem, creating its directory if needed.
	 *
	 * @since 0.1.0
	 *
	 * @param string $path     Absolute file path.
	 * @param string $contents File contents.
	 * @return bool
	 */
	protected function write_file( string $path, string $contents ): bool {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return false;
		}

		$directory = dirname( $path );

		if ( ! $wp_filesystem->is_dir( $directory ) ) {
			$wp_filesystem->mkdir( $directory );
		}

		return (bool) $wp_filesystem->put_contents( $path, $contents, false );
	}
}
