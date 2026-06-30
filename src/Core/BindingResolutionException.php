<?php
/**
 * Container binding resolution exception.
 *
 * @package CartRebound
 */

declare( strict_types=1 );

namespace CartRebound\Core;

defined( 'ABSPATH' ) || exit;

use Exception;

/**
 * Thrown when the container cannot resolve a requested binding.
 *
 * @since 0.1.0
 */
final class BindingResolutionException extends Exception {

}
