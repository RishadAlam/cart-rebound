<?php
/**
 * Console command unit tests.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Tests\Unit;

use CartRebound\Console\Command;
use CartRebound\Core\Application;
use CartRebound\Tests\TestCase;

/**
 * @covers \CartRebound\Console\Command
 */
final class CommandTest extends TestCase {

	private function command(): ExposedCommand {
		return new ExposedCommand( Application::get_instance( __DIR__ ) );
	}

	public function test_accepts_plain_identifiers(): void {
		$command = $this->command();

		$this->assertTrue( $command->check( 'Task' ) );
		$this->assertTrue( $command->check( 'Task2' ) );
		$this->assertTrue( $command->check( 'OrderItem' ) );
	}

	public function test_rejects_path_traversal_and_invalid_names(): void {
		$command = $this->command();

		$this->assertFalse( $command->check( '../../evil' ) );
		$this->assertFalse( $command->check( 'Foo/Bar' ) );
		$this->assertFalse( $command->check( 'Foo\\Bar' ) );
		$this->assertFalse( $command->check( 'Foo.php' ) );
		$this->assertFalse( $command->check( '1Bad' ) );
		$this->assertFalse( $command->check( '' ) );
	}
}

// phpcs:disable -- lightweight test fixture.

final class ExposedCommand extends Command {

	public function check( string $class ): bool {
		return $this->is_valid_class_name( $class );
	}
}
