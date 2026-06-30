<?php
/**
 * PHP-CS-Fixer configuration.
 *
 * Deliberately a minimal, tab-indented rule set that AGREES with the
 * WordPress-Extra PHPCS standard rather than fighting it. We do not apply the
 * full PSR-12 preset because PSR-12 mandates space indentation and brace/paren
 * spacing that conflict with WordPress core style. Keeping this small means
 * `composer cs-check` and `composer phpcs` never disagree.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

$finder = PhpCsFixer\Finder::create()
	->in( array( __DIR__ . '/src' ) )
	->name( '*.php' );

return ( new PhpCsFixer\Config() )
	->setIndent( "\t" )
	->setLineEnding( "\n" )
	->setRiskyAllowed( false )
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
