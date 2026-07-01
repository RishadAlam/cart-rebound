<?php
/**
 * Admin-only REST routes.
 *
 * Register routes here that should only be available to authenticated admin
 * users. Use the `can:<capability>` middleware to gate them, e.g.:
 *
 *     Route::post( 'settings', array( SettingsController::class, 'update' ) )
 *         ->middleware( array( 'nonce', 'can:manage_options' ) );
 *
 * @package CartRebound
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use CartRebound\Http\Controllers\CartsController;
use CartRebound\Http\Controllers\CouponsController;
use CartRebound\Http\Controllers\OrdersController;
use CartRebound\Http\Controllers\SettingsController;
use CartRebound\Http\Controllers\StatsController;
use CartRebound\Support\Facades\Route;

$cart_rebound_admin = array( 'nonce', 'can:manage_woocommerce' );

Route::get( 'carts', array( CartsController::class, 'index' ) )->middleware( $cart_rebound_admin );
Route::post( 'carts/bulk', array( CartsController::class, 'bulk' ) )->middleware( $cart_rebound_admin );
Route::get( 'carts/(?P<id>\d+)', array( CartsController::class, 'show' ) )->middleware( $cart_rebound_admin );
Route::delete( 'carts/(?P<id>\d+)', array( CartsController::class, 'destroy' ) )->middleware( $cart_rebound_admin );
Route::post( 'carts/(?P<id>\d+)/mark-recovered', array( CartsController::class, 'mark_recovered' ) )->middleware( $cart_rebound_admin );
Route::post( 'carts/(?P<id>\d+)/status', array( CartsController::class, 'update_status' ) )->middleware( $cart_rebound_admin );
Route::post( 'carts/(?P<id>\d+)/send-email', array( CartsController::class, 'send_email' ) )->middleware( $cart_rebound_admin );
Route::get( 'orders', array( OrdersController::class, 'index' ) )->middleware( $cart_rebound_admin );
Route::get( 'coupons', array( CouponsController::class, 'index' ) )->middleware( $cart_rebound_admin );
Route::get( 'stats', array( StatsController::class, 'index' ) )->middleware( $cart_rebound_admin );
Route::get( 'settings', array( SettingsController::class, 'index' ) )->middleware( $cart_rebound_admin );
Route::post( 'settings', array( SettingsController::class, 'update' ) )->middleware( $cart_rebound_admin );
