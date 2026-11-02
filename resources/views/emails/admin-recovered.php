<?php
/**
 * Admin "cart recovered" notification email.
 *
 * Rendered by RecoveryMailer::notify_admin() with these variables in scope.
 * Tables and inline styles keep the layout intact in Outlook's Word engine.
 *
 * @package CartRebound
 *
 * @var string                                                         $store_name    Store name.
 * @var string                                                         $preheader     Inbox preview text.
 * @var array{before: string, amount: string, after: string}          $headline      Main heading, split around the highlighted amount ('' for a free order).
 * @var string                                                         $intro         Sentence under the heading.
 * @var array<int, array{label: string, value: string, note: string}> $details       Order facts, already plain text.
 * @var string                                                         $order_url     Admin edit-order URL.
 * @var string                                                         $dashboard_url Cart Rebound dashboard URL.
 * @var string                                                         $settings_url  Cart Rebound settings URL.
 * @var array{count: string, url: string}|null                         $review        Review request, or null when not due.
 */

defined( 'ABSPATH' ) || exit;

$cart_rebound_dir   = is_rtl() ? 'rtl' : 'ltr';
$cart_rebound_start = is_rtl() ? 'right' : 'left';
$cart_rebound_font  = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>" dir="<?php echo esc_attr( $cart_rebound_dir ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="light">
	<meta name="supported-color-schemes" content="light">
	<title><?php echo esc_html( implode( '', $headline ) ); ?></title>
	<style>
		@media only screen and (max-width: 480px) {
			.cr-pad { padding-left: 20px !important; padding-right: 20px !important; }
			.cr-label { width: 104px !important; }
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background: #f0f0f1;">
<div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent; font-size: 1px; line-height: 1px; mso-hide: all;"><?php echo esc_html( $preheader ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f0f0f1;">
	<tr>
		<td align="center" style="padding: 32px 12px;">
			<!--[if mso]><table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" dir="<?php echo esc_attr( $cart_rebound_dir ); ?>" style="width: 100%; max-width: 600px; font-family: <?php echo esc_attr( $cart_rebound_font ); ?>; color: #1d2327; line-height: 1.5;">
				<tr>
					<td style="padding: 0 4px 12px; font-size: 13px; color: #50575e;">
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td align="<?php echo esc_attr( $cart_rebound_start ); ?>" style="font-size: 15px; font-weight: 600; color: #1d2327;"><?php echo esc_html( $store_name ); ?></td>
								<td align="<?php echo esc_attr( is_rtl() ? 'left' : 'right' ); ?>" style="font-size: 13px; color: #646970;"><?php echo esc_html__( 'Cart Rebound', 'cart-rebound' ); ?></td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style="background: #ffffff; border: 1px solid #dcdcde; border-radius: 8px;">
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td class="cr-pad" style="padding: 32px 32px 8px;">
									<h1 style="margin: 0; font-size: 26px; line-height: 1.25; font-weight: 700; color: #1d2327;"><?php echo esc_html( $headline['before'] ); ?><span style="color: #0a6b3d;"><?php echo esc_html( $headline['amount'] ); ?></span><?php echo esc_html( $headline['after'] ); ?></h1>
									<p style="margin: 8px 0 0; font-size: 15px; color: #50575e;"><?php echo esc_html( $intro ); ?></p>
								</td>
							</tr>
							<tr>
								<td class="cr-pad" style="padding: 16px 32px 8px;">
									<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top: 1px solid #f0f0f1;">
										<?php foreach ( $details as $cart_rebound_detail ) : ?>
											<tr>
												<td class="cr-label" valign="top" width="140" style="width: 140px; padding: 12px 12px 12px 0; border-bottom: 1px solid #f0f0f1; font-size: 14px; color: #646970;"><?php echo esc_html( $cart_rebound_detail['label'] ); ?></td>
												<td valign="top" style="padding: 12px 0; border-bottom: 1px solid #f0f0f1; font-size: 14px; color: #1d2327; font-weight: 600;">
													<?php echo esc_html( $cart_rebound_detail['value'] ); ?>
													<?php if ( '' !== $cart_rebound_detail['note'] ) : ?>
														<br><span style="font-weight: 400; color: #646970;"><?php echo esc_html( $cart_rebound_detail['note'] ); ?></span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</table>
								</td>
							</tr>
							<tr>
								<td class="cr-pad" style="padding: 24px 32px 32px;">
									<table role="presentation" cellpadding="0" cellspacing="0" border="0">
										<tr>
											<td align="center" bgcolor="#2271b1" style="background: #2271b1; border-radius: 6px; mso-padding-alt: 12px 24px;">
												<a href="<?php echo esc_url( $order_url ); ?>" style="display: block; padding: 12px 24px; border-radius: 6px; color: #ffffff; font-size: 15px; font-weight: 600; line-height: 1.25; text-decoration: none;"><?php echo esc_html__( 'View order', 'cart-rebound' ); ?></a>
											</td>
										</tr>
									</table>
									<p style="margin: 16px 0 0; font-size: 14px;">
										<a href="<?php echo esc_url( $dashboard_url ); ?>" style="color: #2271b1; text-decoration: underline;"><?php echo esc_html__( 'Open the Cart Rebound dashboard', 'cart-rebound' ); ?></a>
									</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<?php if ( null !== $review ) : ?>
					<tr>
						<td style="padding-top: 16px;">
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #ffffff; border: 1px solid #dcdcde; border-radius: 8px;">
								<tr>
									<td class="cr-pad" style="padding: 24px 32px;">
										<h2 style="margin: 0; font-size: 17px; line-height: 1.4; font-weight: 600; color: #1d2327;"><?php echo esc_html__( 'How has Cart Rebound worked for you?', 'cart-rebound' ); ?></h2>
										<p style="margin: 6px 0 0; font-size: 14px; color: #50575e;">
											<?php echo esc_html( $review['count'] ); ?>
											<?php echo esc_html__( 'Your honest review helps other store owners decide.', 'cart-rebound' ); ?>
										</p>
										<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top: 16px;">
											<tr>
												<td align="center" style="border: 1px solid #2271b1; border-radius: 6px; mso-padding-alt: 10px 20px;">
													<a href="<?php echo esc_url( $review['url'] ); ?>" style="display: block; padding: 10px 20px; border-radius: 6px; color: #2271b1; font-size: 14px; font-weight: 600; line-height: 1.25; text-decoration: none;"><?php echo esc_html__( 'Write a review', 'cart-rebound' ); ?></a>
												</td>
											</tr>
										</table>
										<p style="margin: 8px 0 0; font-size: 12px; color: #646970;"><?php echo esc_html__( 'WordPress.org account required.', 'cart-rebound' ); ?></p>
										<p style="margin: 16px 0 0; font-size: 13px; color: #646970;">
											<?php
											echo wp_kses(
												sprintf(
													/* translators: %s: link to the Cart Rebound dashboard. */
													esc_html__( 'Not now? You can be reminded later, or not asked again, from your %s.', 'cart-rebound' ),
													'<a href="' . esc_url( $dashboard_url ) . '" style="color: #646970; text-decoration: underline;">' . esc_html__( 'Cart Rebound dashboard', 'cart-rebound' ) . '</a>'
												),
												array(
													'a' => array(
														'href'  => true,
														'style' => true,
													),
												)
											);
											?>
										</p>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<td style="padding: 20px 4px 0; font-size: 12px; line-height: 1.6; color: #646970;">
						<?php echo esc_html__( 'You get this email because “Notify admin on recovery” is on in Cart Rebound.', 'cart-rebound' ); ?>
						<a href="<?php echo esc_url( $settings_url ); ?>" style="color: #646970; text-decoration: underline;"><?php echo esc_html__( 'Change email settings', 'cart-rebound' ); ?></a>
					</td>
				</tr>
			</table>
			<!--[if mso]></td></tr></table><![endif]-->
		</td>
	</tr>
</table>
</body>
</html>
