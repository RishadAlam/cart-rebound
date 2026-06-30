<?php
/**
 * Minimal WordPress class/function stubs for unit + integration tests.
 *
 * Brain\Monkey mocks functions but not classes, so we provide just enough of
 * WP_Error, WP_REST_Request and WP_REST_Response for the framework under test.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );
defined( 'ARRAY_N' ) || define( 'ARRAY_N', 'ARRAY_N' );
defined( 'OBJECT' ) || define( 'OBJECT', 'OBJECT' );

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Tiny WP_Error stand-in.
	 */
	class WP_Error {

		/** @var array<string, array<int, string>> */
		protected $errors = array();

		/** @var array<string, mixed> */
		protected $error_data = array();

		/**
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( string $code = '', string $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][] = $message;

				if ( '' !== $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		/** @return bool */
		public function has_errors(): bool {
			return array() !== $this->errors;
		}

		/** @return string */
		public function get_error_code(): string {
			$codes = array_keys( $this->errors );

			return (string) ( $codes[0] ?? '' );
		}

		/**
		 * @param string $code Error code.
		 * @return mixed
		 */
		public function get_error_data( string $code = '' ) {
			$code = '' === $code ? $this->get_error_code() : $code;

			return $this->error_data[ $code ] ?? null;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value to test.
	 * @return bool
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Tiny WP_REST_Request stand-in.
	 */
	class WP_REST_Request {

		/** @var array<string, string> */
		protected $headers = array();

		/** @var array<string, mixed> */
		protected $params = array();

		/**
		 * @param string $key Header name.
		 * @return string|null
		 */
		public function get_header( string $key ) {
			return $this->headers[ strtolower( $key ) ] ?? null;
		}

		/**
		 * @param string $key   Header name.
		 * @param string $value Header value.
		 * @return void
		 */
		public function set_header( string $key, string $value ): void {
			$this->headers[ strtolower( $key ) ] = $value;
		}

		/**
		 * @param string $key Param name.
		 * @return mixed
		 */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		/**
		 * @param string $key   Param name.
		 * @param mixed  $value Param value.
		 * @return void
		 */
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Tiny WP_REST_Response stand-in.
	 */
	class WP_REST_Response {

		/** @var mixed */
		public $data;

		/** @var int */
		public $status;

		/**
		 * @param mixed $data   Payload.
		 * @param int   $status HTTP status.
		 */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/** @return mixed */
		public function get_data() {
			return $this->data;
		}

		/** @return int */
		public function get_status(): int {
			return $this->status;
		}
	}
}
