<?php
/**
 * Settings controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use CartRebound\Http\Requests\UpdateSettingsRequest;
use CartRebound\Support\Settings;
use WP_REST_Response;

/**
 * Reads and updates plugin settings.
 *
 * @since 0.1.0
 */
final class SettingsController extends Controller {

	/**
	 * Settings store.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Application $app      Application instance.
	 * @param Settings    $settings Settings store.
	 */
	public function __construct( Application $app, Settings $settings ) {
		parent::__construct( $app );
		$this->settings = $settings;
	}

	/**
	 * Return the current settings.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response
	 */
	public function index(): WP_REST_Response {
		return $this->respond( $this->settings->all() );
	}

	/**
	 * Persist settings and re-sync the schedule.
	 *
	 * @since 0.1.0
	 *
	 * @param UpdateSettingsRequest $request The validated request.
	 * @return WP_REST_Response
	 */
	public function update( UpdateSettingsRequest $request ): WP_REST_Response {
		$all = $this->settings->update( $request->validated() );

		/**
		 * Fires after settings are saved so the scheduler can reconcile.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $all The full, sanitised settings.
		 */
		do_action( 'cart_rebound_settings_updated', $all );

		return $this->respond( $all );
	}
}
