<?php
/**
 * Add-on registry.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Extend;

defined( 'ABSPATH' ) || exit;

/**
 * Knows which add-ons are present and what they are currently delivering.
 *
 * The free plugin has no dependency on any particular add-on: it opens a
 * registration action, collects whatever answers, and gates its own Pro screens
 * on the feature keys it got back. Nothing here names a product.
 *
 * Collection is lazy and memoised. Firing on first read rather than on a fixed
 * hook means an add-on can register from wherever it boots — `plugins_loaded`,
 * its own service provider, a late `init` — without a load-order race deciding
 * whether its screens appear.
 *
 * @since 1.1.0
 */
final class Registry {

	/**
	 * Registered add-ons, keyed by slug. Null until collection has run.
	 *
	 * @since 1.1.0
	 * @var array<string, Addon>|null
	 */
	private $addons = null;

	/**
	 * Whether the registration action is currently running.
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	private $collecting = false;

	/**
	 * Register an add-on.
	 *
	 * Called by an add-on from the `cart_rebound_register_addons` action:
	 *
	 *     add_action( 'cart_rebound_register_addons', function ( $registry ) {
	 *         $registry->register( array(
	 *             'slug'     => 'cart-rebound-pro',
	 *             'name'     => 'Cart Rebound Pro',
	 *             'version'  => CART_REBOUND_PRO_VERSION,
	 *             'url'      => 'https://example.com/pro',
	 *             'features' => array( 'sequence', 'coupons' ),
	 *             'licensed' => $license->is_valid(),
	 *         ) );
	 *     } );
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $addon Add-on description.
	 * @return bool True when the add-on was accepted.
	 */
	public function register( array $addon ): bool {
		$parsed = Addon::from_array( $addon );

		if ( null === $parsed ) {
			return false;
		}

		if ( null === $this->addons ) {
			$this->addons = array();
		}

		$this->addons[ $parsed->slug() ] = $parsed;

		return true;
	}

	/**
	 * Get every registered add-on, keyed by slug.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, Addon>
	 */
	public function all(): array {
		$this->collect();

		return null === $this->addons ? array() : $this->addons;
	}

	/**
	 * Whether any add-on is installed and running.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function has_addons(): bool {
		return array() !== $this->all();
	}

	/**
	 * Whether any installed add-on holds a valid license.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_licensed(): bool {
		foreach ( $this->all() as $addon ) {
			if ( $addon->is_licensed() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every feature key currently being delivered.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function features(): array {
		$features = array();

		foreach ( $this->all() as $addon ) {
			foreach ( $addon->active_features() as $feature ) {
				if ( ! in_array( $feature, $features, true ) ) {
					$features[] = $feature;
				}
			}
		}

		return $features;
	}

	/**
	 * Whether a named feature is currently being delivered.
	 *
	 * The one call the rest of the plugin makes. It answers false for a feature
	 * nobody registered, and false for one registered by an add-on whose license
	 * has lapsed — the two cases the caller does not need to tell apart.
	 *
	 * @since 1.1.0
	 *
	 * @param string $feature Feature key from {@see Feature}.
	 * @return bool
	 */
	public function has( string $feature ): bool {
		return in_array( $feature, $this->features(), true );
	}

	/**
	 * The whole add-on picture, as the admin app consumes it.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function state(): array {
		$addons = array();

		foreach ( $this->all() as $addon ) {
			$addons[] = $addon->to_array();
		}

		return array(
			'installed'   => array() !== $addons,
			'licensed'    => $this->is_licensed(),
			'features'    => $this->features(),
			'addons'      => $addons,
			'upgrade_url' => self::upgrade_url(),
		);
	}

	/**
	 * Where the locked screens send someone who wants the add-on.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public static function upgrade_url(): string {
		/**
		 * Filter the URL the locked Pro screens link to.
		 *
		 * @since 1.1.0
		 *
		 * @param string $url Absolute URL.
		 */
		$url = apply_filters( 'cart_rebound_upgrade_url', 'https://github.com/RishadAlam/cart-rebound-pro' );

		return esc_url_raw( is_string( $url ) ? $url : '' );
	}

	/**
	 * Run the registration action once.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function collect(): void {
		if ( null !== $this->addons || $this->collecting ) {
			return;
		}

		// Guard against an add-on that reads the registry from inside its own
		// registration callback: without this, collect() would recurse.
		$this->collecting = true;
		$this->addons     = array();

		/**
		 * Fires so add-ons can register themselves.
		 *
		 * Runs once, the first time anything asks the registry a question, and
		 * the answer is memoised for the rest of the request. Register from this
		 * action rather than writing to any option: the registry describes what
		 * is running right now, so a deactivated add-on disappears from it with
		 * no cleanup step.
		 *
		 * @since 1.1.0
		 *
		 * @param Registry $registry The registry to register with.
		 */
		do_action( 'cart_rebound_register_addons', $this );

		$this->collecting = false;
	}
}
