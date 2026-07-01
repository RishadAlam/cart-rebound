<?php
/**
 * Email templates controller.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use CartRebound\Core\Application;
use CartRebound\Http\Requests\EmailTemplateRequest;
use CartRebound\Mail\TemplateStore;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Admin CRUD for the recovery-email templates.
 *
 * @since 0.2.0
 */
final class TemplatesController extends Controller {

	/**
	 * Template store.
	 *
	 * @since 0.2.0
	 * @var TemplateStore
	 */
	private $templates;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param Application   $app       Application instance.
	 * @param TemplateStore $templates Template store.
	 */
	public function __construct( Application $app, TemplateStore $templates ) {
		parent::__construct( $app );
		$this->templates = $templates;
	}

	/**
	 * List all templates.
	 *
	 * @since 0.2.0
	 *
	 * @return WP_REST_Response
	 */
	public function index(): WP_REST_Response {
		return $this->respond( array( 'items' => $this->templates->all() ) );
	}

	/**
	 * Create a template.
	 *
	 * @since 0.2.0
	 *
	 * @param EmailTemplateRequest $request The validated request.
	 * @return WP_REST_Response
	 */
	public function store( EmailTemplateRequest $request ): WP_REST_Response {
		return $this->respond( $this->templates->create( $this->payload( $request ) ), 201 );
	}

	/**
	 * Update a template.
	 *
	 * @since 0.2.0
	 *
	 * @param EmailTemplateRequest $request The validated request.
	 * @return WP_REST_Response
	 */
	public function update( EmailTemplateRequest $request ): WP_REST_Response {
		$id      = sanitize_text_field( (string) $request->param( 'id' ) );
		$updated = $this->templates->update( $id, $this->payload( $request ) );

		if ( null === $updated ) {
			return $this->respond( array( 'message' => __( 'Template not found.', 'cart-rebound' ) ), 404 );
		}

		return $this->respond( $updated );
	}

	/**
	 * Delete a template.
	 *
	 * @since 0.2.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$deleted = $this->templates->delete( sanitize_text_field( (string) $request->get_param( 'id' ) ) );

		return $this->respond( array( 'deleted' => $deleted ) );
	}

	/**
	 * Mark a template as the default used for automatic sends.
	 *
	 * @since 0.2.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function set_default( WP_REST_Request $request ): WP_REST_Response {
		$updated = $this->templates->set_default( sanitize_text_field( (string) $request->get_param( 'id' ) ) );

		return $this->respond( array( 'updated' => $updated ) );
	}

	/**
	 * Build a template payload from the request (body/HTML fields read raw).
	 *
	 * @since 0.2.0
	 *
	 * @param EmailTemplateRequest $request The validated request.
	 * @return array<string, mixed>
	 */
	private function payload( EmailTemplateRequest $request ): array {
		$validated = $request->validated();

		return array(
			'name'       => (string) ( $validated['name'] ?? '' ),
			'subject'    => (string) ( $validated['subject'] ?? '' ),
			'body'       => (string) $request->param( 'body' ),
			'from_name'  => (string) $request->param( 'from_name' ),
			'from_email' => (string) $request->param( 'from_email' ),
			'coupon'     => (string) $request->param( 'coupon' ),
			'is_default' => (bool) $request->param( 'is_default' ),
		);
	}
}
