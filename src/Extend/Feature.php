<?php
/**
 * The feature vocabulary shared by the plugin and its add-ons.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Extend;

defined( 'ABSPATH' ) || exit;

/**
 * The names an add-on uses to say what it delivers.
 *
 * Both sides of the boundary agree here and nowhere else: an add-on claims
 * these keys when it registers, and the admin app unlocks the matching screen
 * when it sees one. Keeping the list in the free plugin means a screen can
 * never be unlocked by a key the free plugin has never heard of.
 *
 * @since 1.1.0
 */
final class Feature {

	/**
	 * A multi-step follow-up sequence instead of one email.
	 *
	 * @var string
	 */
	public const SEQUENCE = 'sequence';

	/**
	 * Unique, single-use, expiring coupons minted per cart.
	 *
	 * @var string
	 */
	public const COUPONS = 'coupons';

	/**
	 * Open and click measurement on the emails that go out.
	 *
	 * @var string
	 */
	public const TRACKING = 'tracking';

	/**
	 * Reporting over the tracked funnel and the email performance.
	 *
	 * @var string
	 */
	public const ANALYTICS = 'analytics';

	/**
	 * Rules deciding which carts enter recovery at all.
	 *
	 * @var string
	 */
	public const RULES = 'rules';

	/**
	 * Every recognised feature key.
	 *
	 * @var array<int, string>
	 */
	public const ALL = array(
		self::SEQUENCE,
		self::COUPONS,
		self::TRACKING,
		self::ANALYTICS,
		self::RULES,
	);

	/**
	 * Whether a string is a feature key this version recognises.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key Candidate key.
	 * @return bool
	 */
	public static function exists( string $key ): bool {
		return in_array( $key, self::ALL, true );
	}
}
