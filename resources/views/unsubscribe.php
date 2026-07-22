<?php
/**
 * Standalone unsubscribe page (shopper-facing, theme-independent).
 *
 * Rendered by UnsubscribeHandler outside the theme so it looks intentional
 * rather than inheriting whatever the active theme does with wp_die().
 *
 * @package CartRebound
 *
 * @var string $state       One of 'confirm', 'done', 'invalid'.
 * @var string $form_action Absolute URL the confirmation form posts to.
 * @var string $token       Recovery token (confirm state only).
 * @var string $field_flag  Hidden-field name carrying the unsubscribe flag.
 * @var string $field_token Hidden-field name carrying the token.
 * @var string $home_url    Store home URL.
 */

defined( 'ABSPATH' ) || exit;

$cart_rebound_state = isset( $state ) && is_string( $state ) ? $state : 'invalid';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html__( 'Unsubscribe', 'cart-rebound' ); ?></title>
	<style>
		:root {
			--cr-bg: oklch(0.985 0.003 256);
			--cr-surface: oklch(1 0 0);
			--cr-border: oklch(0.916 0.006 256);
			--cr-ink: oklch(0.27 0.02 264);
			--cr-muted: oklch(0.52 0.018 264);
			--cr-accent: oklch(0.55 0.16 264);
			--cr-accent-hover: oklch(0.49 0.16 264);
			--cr-accent-ink: oklch(0.99 0 0);
			--cr-accent-soft: oklch(0.955 0.03 264);
			--cr-success: oklch(0.5 0.12 155);
			--cr-success-soft: oklch(0.95 0.04 155);
			--cr-warning: oklch(0.5 0.1 70);
			--cr-warning-soft: oklch(0.945 0.05 80);
			--cr-ease: cubic-bezier(0.23, 1, 0.32, 1);
		}

		* { box-sizing: border-box; }

		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px;
			background: var(--cr-bg);
			color: var(--cr-ink);
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
				Oxygen, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			line-height: 1.55;
			-webkit-font-smoothing: antialiased;
		}

		.cr-card {
			width: 100%;
			max-width: 448px;
			padding: 40px 36px 32px;
			background: var(--cr-surface);
			border: 1px solid var(--cr-border);
			border-radius: 16px;
			box-shadow: 0 24px 48px -12px oklch(0.27 0.02 264 / 0.16),
				0 8px 20px -8px oklch(0.27 0.02 264 / 0.1);
			text-align: center;
			animation: cr-in 320ms var(--cr-ease) both;
		}

		@keyframes cr-in {
			from { opacity: 0; transform: translateY(10px) scale(0.985); }
		}

		.cr-brand {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 28px;
			color: var(--cr-accent);
			font-size: 14px;
			font-weight: 600;
			letter-spacing: -0.01em;
		}

		.cr-brand__mark {
			display: grid;
			place-items: center;
			width: 26px;
			height: 26px;
			border-radius: 8px;
			background: var(--cr-accent);
			color: var(--cr-accent-ink);
		}

		.cr-icon {
			display: grid;
			place-items: center;
			width: 56px;
			height: 56px;
			margin: 0 auto 20px;
			border-radius: 999px;
		}

		.cr-icon.is-confirm { background: var(--cr-accent-soft); color: var(--cr-accent); }
		.cr-icon.is-done { background: var(--cr-success-soft); color: var(--cr-success); }
		.cr-icon.is-invalid { background: var(--cr-warning-soft); color: var(--cr-warning); }

		.cr-title {
			margin: 0 0 8px;
			font-size: 21px;
			font-weight: 600;
			letter-spacing: -0.02em;
			text-wrap: balance;
		}

		.cr-text {
			margin: 0 auto;
			max-width: 42ch;
			color: var(--cr-muted);
			font-size: 15px;
		}

		.cr-actions {
			margin-top: 28px;
			display: flex;
			flex-direction: column;
			gap: 12px;
		}

		.cr-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 100%;
			padding: 12px 20px;
			border: 1px solid transparent;
			border-radius: 10px;
			font-size: 15px;
			font-weight: 600;
			text-decoration: none;
			cursor: pointer;
			transition: background-color 140ms var(--cr-ease),
				border-color 140ms var(--cr-ease), transform 140ms var(--cr-ease);
		}

		.cr-btn:active { transform: scale(0.98); }

		.cr-btn--primary { background: var(--cr-accent); color: var(--cr-accent-ink); }
		.cr-btn--primary:hover { background: var(--cr-accent-hover); }

		.cr-btn--ghost {
			background: transparent;
			color: var(--cr-muted);
			font-weight: 500;
			font-size: 14px;
			padding: 8px;
		}
		.cr-btn--ghost:hover { color: var(--cr-ink); }

		.cr-form { margin: 0; }

		@media (prefers-reduced-motion: reduce) {
			.cr-card { animation: none; }
		}
	</style>
</head>
<body>
	<main class="cr-card">
		<div class="cr-brand">
			<span class="cr-brand__mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15" aria-hidden="true">
					<path d="M2.25 2.25a.75.75 0 0 0 0 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 0 0-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 0 0 0-1.5H5.378A2.25 2.25 0 0 1 7.5 15h11.218a.75.75 0 0 0 .674-.421 60.358 60.358 0 0 0 2.96-7.228.75.75 0 0 0-.525-.965A60.864 60.864 0 0 0 5.68 4.509l-.232-.867A1.875 1.875 0 0 0 3.636 2.25H2.25ZM3.75 20.25a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM16.5 20.25a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Z" />
				</svg>
			</span>
			<?php echo esc_html__( 'Cart Rebound', 'cart-rebound' ); ?>
		</div>

		<?php if ( 'confirm' === $cart_rebound_state ) : ?>
			<div class="cr-icon is-confirm" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" width="26" height="26">
					<rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7" />
					<path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</div>
			<h1 class="cr-title">
				<?php echo esc_html__( 'Unsubscribe from recovery emails?', 'cart-rebound' ); ?>
			</h1>
			<p class="cr-text">
				<?php echo esc_html__( 'You’ll stop receiving reminder emails about items left in your cart. This won’t affect order or account emails.', 'cart-rebound' ); ?>
			</p>
			<div class="cr-actions">
				<form class="cr-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
					<input type="hidden" name="<?php echo esc_attr( $field_flag ); ?>" value="1" />
					<input type="hidden" name="<?php echo esc_attr( $field_token ); ?>" value="<?php echo esc_attr( $token ); ?>" />
					<button type="submit" class="cr-btn cr-btn--primary">
						<?php echo esc_html__( 'Unsubscribe', 'cart-rebound' ); ?>
					</button>
				</form>
				<a class="cr-btn cr-btn--ghost" href="<?php echo esc_url( $home_url ); ?>">
					<?php echo esc_html__( 'Keep me subscribed', 'cart-rebound' ); ?>
				</a>
			</div>

		<?php elseif ( 'done' === $cart_rebound_state ) : ?>
			<div class="cr-icon is-done" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" width="28" height="28">
					<path d="m5 12.5 4.2 4.2L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</div>
			<h1 class="cr-title">
				<?php echo esc_html__( 'You’re unsubscribed', 'cart-rebound' ); ?>
			</h1>
			<p class="cr-text">
				<?php echo esc_html__( 'You won’t receive any more cart recovery emails from this store.', 'cart-rebound' ); ?>
			</p>
			<div class="cr-actions">
				<a class="cr-btn cr-btn--primary" href="<?php echo esc_url( $home_url ); ?>">
					<?php echo esc_html__( 'Return to store', 'cart-rebound' ); ?>
				</a>
			</div>

		<?php else : ?>
			<div class="cr-icon is-invalid" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" width="26" height="26">
					<circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7" />
					<path d="M12 8v5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
					<circle cx="12" cy="16" r="1" fill="currentColor" />
				</svg>
			</div>
			<h1 class="cr-title">
				<?php echo esc_html__( 'This link is no longer valid', 'cart-rebound' ); ?>
			</h1>
			<p class="cr-text">
				<?php echo esc_html__( 'This unsubscribe link has expired or has already been used.', 'cart-rebound' ); ?>
			</p>
			<div class="cr-actions">
				<a class="cr-btn cr-btn--primary" href="<?php echo esc_url( $home_url ); ?>">
					<?php echo esc_html__( 'Return to store', 'cart-rebound' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</main>
</body>
</html>
<?php
// Rendering is complete; nothing further should run on this request.
exit;
