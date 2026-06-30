<?php
/**
 * Recovery email template.
 *
 * Rendered by RecoveryMailer::build_body() with these variables in scope.
 *
 * @package CartRebound
 *
 * @var string $content      The token-replaced message body (pre-escaped pieces).
 * @var string $recovery_url The tokenised recovery URL.
 */

defined( 'ABSPATH' ) || exit;

?>
<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; color: #1a1a1a; line-height: 1.5;">
	<div style="padding: 24px;">
		<?php echo wp_kses_post( wpautop( $content ) ); ?>
		<p style="margin: 32px 0;">
			<a href="<?php echo esc_url( $recovery_url ); ?>" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600;">
				<?php echo esc_html__( 'Complete your order', 'cart-rebound' ); ?>
			</a>
		</p>
	</div>
</div>
