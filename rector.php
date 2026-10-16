<?php

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
			// WordPress-Docs (PHPCS) requires an explicit @return on every function.
			RemoveUselessReturnTagRector::class,
		)
	);
