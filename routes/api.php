<?php
/**
 * REST API routes.
 *
 * Loaded on `rest_api_init`. Every route is registered under the
 * `cart-rebound/v1` namespace and gated by its middleware stack.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use CartRebound\Http\Controllers\CaptureController;
use CartRebound\Http\Controllers\PingController;
use CartRebound\Support\Facades\Route;

Route::get( 'ping', array( PingController::class, 'index' ) )->middleware( 'nonce' );

/*
 * Front-end guest beacon: captures the email/name/phone a guest enters at
 * checkout. State-changing but intentionally nonce-only — guests hold no
 * capability, and the endpoint can only update the visitor's current cart.
 */
Route::post( 'capture', array( CaptureController::class, 'store' ) )->middleware( 'nonce' );
