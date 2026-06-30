<?php
/**
 * `wp cart-rebound make:model` command.
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
 * Scaffolds a new model class.
 *
 * @since 0.1.0
 */
final class MakeModelCommand extends Command {

	/**
	 * Generate a model.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The model name (StudlyCase).
	 *
	 * ## EXAMPLES
	 *
	 *     wp cart-rebound make:model Task
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
			WP_CLI::error( 'A model name is required.' );
			return;
		}

		$class = Str::studly( $name );

		if ( ! $this->is_valid_class_name( $class ) ) {
			WP_CLI::error( 'Invalid model name. Use letters and numbers only (e.g. "Task").' );
			return;
		}

		$path = $this->app->base_path( 'src/Models/' . $class . '.php' );

		if ( file_exists( $path ) ) {
			WP_CLI::error( sprintf( '%s already exists.', $class ) );
			return;
		}

		if ( ! $this->write_file( $path, $this->stub( $class ) ) ) {
			WP_CLI::error( 'Could not write the model file.' );
			return;
		}

		WP_CLI::success( sprintf( 'Created model %s.', $class ) );
	}

	/**
	 * Render the model stub.
	 *
	 * @since 0.1.0
	 *
	 * @param string $class_name The class name.
	 * @return string
	 */
	private function stub( string $class_name ): string {
		$table = Str::snake( $class_name );

		$template = <<<'PHP'
<?php
/**
 * {{class}} model.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Models;

defined( 'ABSPATH' ) || exit;

/**
 * {{class}}.
 *
 * @since 0.1.0
 */
final class {{class}} extends Model {

	/**
	 * Unprefixed table suffix.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $table = '{{table}}';

	/**
	 * Mass-assignable columns.
	 *
	 * @since 0.1.0
	 * @var array<int, string>
	 */
	protected $fillable = array();
}
PHP;

		return str_replace( array( '{{class}}', '{{table}}' ), array( $class_name, $table ), $template ) . "\n";
	}
}
