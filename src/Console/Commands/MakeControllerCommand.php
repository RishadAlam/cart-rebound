<?php
/**
 * `wp cart-rebound make:controller` command.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Console\Commands;

defined( 'ABSPATH' ) || exit;

use CartRebound\Console\Command;
use CartRebound\Support\Str;
use WP_CLI;

/**
 * Scaffolds a new REST controller class.
 *
 * @since 0.1.0
 */
final class MakeControllerCommand extends Command {

	/**
	 * Generate a controller.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The controller name (a "Controller" suffix is added if missing).
	 *
	 * ## EXAMPLES
	 *
	 *     wp cart-rebound make:controller Tasks
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$name = $args[0] ?? '';

		if ( '' === $name ) {
			WP_CLI::error( 'A controller name is required.' );
			return;
		}

		$class = Str::studly( $name );

		if ( ! $this->is_valid_class_name( $class ) ) {
			WP_CLI::error( 'Invalid controller name. Use letters and numbers only (e.g. "Tasks").' );
			return;
		}

		if ( 'Controller' !== substr( $class, -10 ) ) {
			$class .= 'Controller';
		}

		$path = $this->app->base_path( 'src/Http/Controllers/' . $class . '.php' );

		if ( file_exists( $path ) ) {
			WP_CLI::error( sprintf( '%s already exists.', $class ) );
			return;
		}

		if ( ! $this->write_file( $path, $this->stub( $class ) ) ) {
			WP_CLI::error( 'Could not write the controller file.' );
			return;
		}

		WP_CLI::success( sprintf( 'Created controller %s.', $class ) );
	}

	/**
	 * Render the controller stub.
	 *
	 * @since 0.1.0
	 *
	 * @param string $class_name The class name.
	 * @return string
	 */
	private function stub( string $class_name ): string {
		$template = <<<'PHP'
<?php
/**
 * {{class}} controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;

/**
 * {{class}}.
 *
 * @since 0.1.0
 */
final class {{class}} extends Controller {

	/**
	 * Handle the request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The current REST request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		return $this->respond( array() );
	}
}
PHP;

		return str_replace( '{{class}}', $class_name, $template ) . "\n";
	}
}
