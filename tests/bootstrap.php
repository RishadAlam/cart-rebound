<?php
/**
 * PHPUnit bootstrap.
 *
 * Defines the constants that production code guards on, then loads the
 * Composer autoloader so tests can exercise the framework in isolation
 * (WordPress functions are mocked with Brain\Monkey).
 *
 * @package CartRebound
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
defined( 'CART_REBOUND_VERSION' ) || define( 'CART_REBOUND_VERSION', '0.1.0' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/Stubs/wp-classes.php';
