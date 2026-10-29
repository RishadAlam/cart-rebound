<?php
/**
 * Recovery email template.
 *
 * Rendered by RecoveryMailer::build_body() with these variables in scope.
 * The table layout keeps the 600px width intact in Outlook's Word engine.
 *
 * @package CartRebound
 *
 * @var string $content         The token-replaced message body (already paragraph-wrapped and escaped piece by piece).
 * @var string $recovery_url    The tokenised recovery URL.
 * @var string $unsubscribe_url The tokenised one-click unsubscribe URL.
 */

defined( 'ABSPATH' ) || exit;

$cart_rebound_dir = is_rtl() ? 'rtl' : 'ltr';

?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>" dir="<?php echo esc_attr( $cart_rebound_dir ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="light">
	<meta name="supported-color-schemes" content="light">
</head>
<body style="margin: 0; padding: 0; background: #ffffff;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #ffffff;">
	<tr>
		<td align="center" style="padding: 0;">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" dir="<?php echo esc_attr( $cart_rebound_dir ); ?>" style="width: 100%; max-width: 600px; font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; line-height: 1.5;">
				<tr>
					<td style="padding: 24px;">
						<?php echo wp_kses_post( $content ); ?>
						<p style="margin: 32px 0;">
							<a href="<?php echo esc_url( $recovery_url ); ?>" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600;">
								<?php echo esc_html__( 'Complete your order', 'cart-rebound' ); ?>
							</a>
						</p>
					</td>
				</tr>
				<tr>
					<td style="padding: 16px 24px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; line-height: 1.5;">
						<?php echo esc_html__( 'Don’t want these reminders?', 'cart-rebound' ); ?>
						<a href="<?php echo esc_url( $unsubscribe_url ); ?>" style="color: #6b7280; text-decoration: underline;">
							<?php echo esc_html__( 'Unsubscribe', 'cart-rebound' ); ?>
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
