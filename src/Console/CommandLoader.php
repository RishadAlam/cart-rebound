<?php
/**
 * WP-CLI command loader.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Console;

defined( 'ABSPATH' ) || exit;

use CartRebound\Console\Commands\MigrateCommand;
use CartRebound\Core\Application;
use WP_CLI;

/**
 * Registers the plugin's WP-CLI commands.
 *
 * Resolved and called only when the WP_CLI constant is defined.
 *
 * @since 0.1.0
 */
final class CommandLoader {

	/**
	 * The application instance.
	 *
	 * @since 0.1.0
	 * @var Application
	 */
	private $app;

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
	 * Register the commands with WP-CLI.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		WP_CLI::add_command( 'cart-rebound migrate', $this->app->make( MigrateCommand::class ) );
	}
}
