<?php

declare( strict_types=1 );

$finder = PhpCsFixer\Finder::create()
	->in( array( __DIR__ . '/src' ) )
	->name( '*.php' );

return ( new PhpCsFixer\Config() )
	->setIndent( "\t" )
	->setLineEnding( "\n" )
	->setRiskyAllowed( false )
	// Not @PSR12: its space indentation and spacing conflict with WordPress-Extra PHPCS.
	->setRules(
		array(
			'array_syntax'              => array( 'syntax' => 'long' ),
			'single_quote'             => true,
			'no_unused_imports'        => true,
			'ordered_imports'          => array( 'sort_algorithm' => 'alpha' ),
			'no_trailing_whitespace'   => true,
			'no_whitespace_in_blank_line' => true,
		)
	)
	->setFinder( $finder );
