<?php
/**
 * `wp cart-rebound migrate` command.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Console\Commands;

defined( 'ABSPATH' ) || exit;

use CartRebound\Console\Command;
use CartRebound\Database\Migrator;
use WP_CLI;

/**
 * Runs pending database migrations.
 *
 * @since 0.1.0
 */
final class MigrateCommand extends Command {

	/**
	 * Run pending migrations.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cart-rebound migrate
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$this->app->make( Migrator::class )->run();

		WP_CLI::success( 'Migrations complete.' );
	}
}
