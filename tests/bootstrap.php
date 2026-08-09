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

// WordPress time constants, used by the scheduling code under test.
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
defined( 'WEEK_IN_SECONDS' ) || define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/Stubs/wp-classes.php';
