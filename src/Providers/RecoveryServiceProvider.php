<?php
/**
 * Recovery + order-linking provider.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Providers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\ServiceProvider;
use CartRebound\Recovery\OrderLinker;
use CartRebound\Recovery\RecoveryHandler;
use CartRebound\Recovery\UnsubscribeHandler;
use WC_Order;

/**
 * Wires the tokenised recovery handler and the order-to-cart linking hooks for
 * classic, block, and async/IPN gateways.
 *
 * @since 0.1.0
 */
final class RecoveryServiceProvider extends ServiceProvider {

	/**
	 * Wire recovery + order hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'template_redirect', array( $this->app->make( RecoveryHandler::class ), 'handle' ) );
		add_action( 'template_redirect', array( $this->app->make( UnsubscribeHandler::class ), 'handle' ) );

		$linker = $this->app->make( OrderLinker::class );

		// Front-end checkout only (classic + block). woocommerce_new_order is
		// intentionally NOT hooked: it fires for admin/programmatic orders too and
		// would mis-attribute them to an unrelated tracked cart.
		add_action( 'woocommerce_checkout_order_processed', array( $linker, 'on_order_created' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_store_api_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( $linker, 'on_status_changed' ), 20, 3 );
	}

	/**
	 * Link an order created through the block / Store API checkout.
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Order $order The created order.
	 * @return void
	 */
	public function on_store_api_order( WC_Order $order ): void {
		$this->app->make( OrderLinker::class )->on_order_created( $order->get_id() );
	}
}
