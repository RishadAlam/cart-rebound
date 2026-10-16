<?php

declare( strict_types=1 );

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
	->withPaths( array( __DIR__ . '/src' ) )
	->withPhpVersion( PhpVersion::PHP_74 )
	// Positional (deadCode, codeQuality): CI parses this file on PHP 7.4, which lacks named arguments.
	->withPreparedSets( true, true )
	->withSkip(
		array(
			// WordPress-Docs (PHPCS) requires an explicit @return on every function.
			RemoveUselessReturnTagRector::class,
		)
	);
