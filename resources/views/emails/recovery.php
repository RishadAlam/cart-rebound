<?php
/**
 * Recovery email template.
 *
 * Rendered by RecoveryMailer::build_body() with these variables in scope.
 *
 * @package CartRebound
 *
 * @var string $content         The token-replaced message body (pre-escaped pieces).
 * @var string $recovery_url    The tokenised recovery URL.
 * @var string $unsubscribe_url The tokenised one-click unsubscribe URL.
 */

defined( 'ABSPATH' ) || exit;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php /* Outlook renders at 96dpi and silently enlarges everything without this. */ ?>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="x-apple-disable-message-reformatting" content="" />
	<title><?php echo esc_html__( 'Your cart is waiting', 'cart-rebound' ); ?></title>
</head>
<body style="margin: 0; padding: 0; background: #f4f4f5;">
<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; color: #1a1a1a; line-height: 1.5;">
	<div style="padding: 24px;">
		<?php echo wp_kses_post( wpautop( $content ) ); ?>
		<p style="margin: 32px 0;">
			<a href="<?php echo esc_url( $recovery_url ); ?>" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600;">
				<?php echo esc_html__( 'Complete your order', 'cart-rebound' ); ?>
			</a>
		</p>
	</div>
	<div style="padding: 16px 24px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; line-height: 1.5;">
		<p style="margin: 0;">
			<?php echo esc_html__( 'Don’t want these reminders?', 'cart-rebound' ); ?>
			<a href="<?php echo esc_url( $unsubscribe_url ); ?>" style="color: #6b7280; text-decoration: underline;">
				<?php echo esc_html__( 'Unsubscribe', 'cart-rebound' ); ?>
			</a>
		</p>
	</div>
</div>
</body>
</html>
