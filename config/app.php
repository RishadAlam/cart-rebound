<?php
/**
 * Application configuration.
 *
 * Returns the ordered list of service providers the application registers and
 * boots on every request.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use CartRebound\Providers\AdminServiceProvider;
use CartRebound\Providers\AppServiceProvider;
use CartRebound\Providers\AssetServiceProvider;
use CartRebound\Providers\CaptureServiceProvider;
use CartRebound\Providers\CartReboundServiceProvider;
use CartRebound\Providers\LogServiceProvider;
use CartRebound\Providers\RecoveryServiceProvider;
use CartRebound\Providers\RouteServiceProvider;
use CartRebound\Providers\SchedulerServiceProvider;

return array(
	'name'      => 'Cart Rebound',
	'providers' => array(
		AppServiceProvider::class,
		RouteServiceProvider::class,
		AdminServiceProvider::class,
		AssetServiceProvider::class,
		CartReboundServiceProvider::class,
		CaptureServiceProvider::class,
		RecoveryServiceProvider::class,
		SchedulerServiceProvider::class,
		LogServiceProvider::class,
	),
);
