<?php
/**
 * Rector configuration.
 *
 * Targets PHP 7.4 (the project floor) so no PHP 8-only refactors are
 * suggested, and applies the dead-code and code-quality prepared sets. Run
 * `composer rector` for a dry-run; never auto-fix in CI.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
	->withPaths( array( __DIR__ . '/src' ) )
	->withPhpVersion( PhpVersion::PHP_74 )
	->withPreparedSets(
		deadCode: true,
		codeQuality: true
	)
	->withSkip(
		array(
			// WordPress-Docs (PHPCS) REQUIRES an explicit @return tag on every
			// function, so this dead-code rule is intentionally disabled to
			// avoid the two tools fighting over @return void / @return bool.
			RemoveUselessReturnTagRector::class,
		)
	);
