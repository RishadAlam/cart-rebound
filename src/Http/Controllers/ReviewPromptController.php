<?php
/**
 * Review prompt controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Admin\ReviewPrompt;
use CartRebound\Core\Application;
use CartRebound\Http\Requests\ReviewPromptRequest;
use WP_REST_Response;

/**
 * Records the store manager's answer to the review request.
 *
 * @since 1.1.3
 */
final class ReviewPromptController extends Controller {

	/**
	 * Review prompt state.
	 *
	 * @since 1.1.3
	 * @var ReviewPrompt
	 */
	private $prompt;

	/**
	 * Constructor.
	 *
	 * @since 1.1.3
	 *
	 * @param Application  $app    Application instance.
	 * @param ReviewPrompt $prompt Review prompt state.
	 */
	public function __construct( Application $app, ReviewPrompt $prompt ) {
		parent::__construct( $app );
		$this->prompt = $prompt;
	}

	/**
	 * Snooze or permanently stop the review request.
	 *
	 * @since 1.1.3
	 *
	 * @param ReviewPromptRequest $request The validated request.
	 * @return WP_REST_Response
	 */
	public function update( ReviewPromptRequest $request ): WP_REST_Response {
		$choice = (string) $request->validated()['choice'];

		if ( ! $this->prompt->respond( $choice ) ) {
			return $this->respond( array( 'message' => __( 'Your choice could not be saved. Please try again.', 'cart-rebound' ) ), 500 );
		}

		return $this->respond( array( 'updated' => true ) );
	}
}
