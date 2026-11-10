import { useEffect, useRef } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { AdminLink } from './AdminLink';
import { OnboardingWizard } from './OnboardingWizard';
import { ReviewPrompt } from './ReviewPrompt';

interface Tab {
	to: string;
	label: string;
	end: boolean;
}

const tabs: Tab[] = [
	{ to: '/', label: __('Dashboard', 'cart-rebound'), end: true },
	{ to: '/carts', label: __('Carts', 'cart-rebound'), end: false },
	{
		to: '/templates',
		label: __('Templates', 'cart-rebound'),
		end: false,
	},
	{ to: '/logs', label: __('Log', 'cart-rebound'), end: false },
	{ to: '/settings', label: __('Settings', 'cart-rebound'), end: false },
];

const tabClass = ({ isActive }: { isActive: boolean }): string =>
	isActive ? 'cr-tab is-active' : 'cr-tab';

const WIDE_ROUTES = ['/', '/carts', '/logs'];

// Ask for a review only where recovery results are on screen, not over setup work.
const REVIEW_ROUTES = ['/', '/carts'];

const docs = window.CartRebound?.docs ?? {};

const ExternalIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<path
			d="M9.5 3.5h3v3m0-3L7 9m-1-5H4.5a1 1 0 0 0-1 1v6.5a1 1 0 0 0 1 1H11a1 1 0 0 0 1-1V10"
			stroke="currentColor"
			strokeWidth="1.4"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

// WordPress sizes its sticky admin menu from a page height it measures only on load
// and resize, so re-pin it whenever the app's own height changes.
const useRepinAdminMenu = () => {
	const ref = useRef<HTMLDivElement>(null);

	useEffect(() => {
		const node = ref.current;

		if (!node || !window.jQuery || !('ResizeObserver' in window)) {
			return;
		}

		let frame = 0;
		const observer = new ResizeObserver(() => {
			window.cancelAnimationFrame(frame);
			frame = window.requestAnimationFrame(() => {
				window.jQuery?.(document).trigger('wp-pin-menu');
			});
		});

		observer.observe(node);

		return () => {
			observer.disconnect();
			window.cancelAnimationFrame(frame);
		};
	}, []);

	return ref;
};

export const Layout = () => {
	const { pathname } = useLocation();
	const wide = WIDE_ROUTES.includes(pathname);
	const appRef = useRepinAdminMenu();
	const docsUrl = docs[pathname] ?? docs['/'];

	return (
		<div ref={appRef} className="cr-app">
			<OnboardingWizard />

			<header className="cr-header">
				<div className="cr-header__text">
					<h1 className="cr-header__title">
						{__('Cart Rebound', 'cart-rebound')}
					</h1>
					<p className="cr-header__subtitle">
						{__(
							'Recover more WooCommerce sales with automated emails, secure recovery links, and clear revenue tracking.',
							'cart-rebound'
						)}
					</p>
				</div>
				{docsUrl && (
					<a
						className="cr-linkbtn cr-header__help"
						href={docsUrl}
						target="_blank"
						rel="noopener noreferrer"
					>
						{__('Help with this page', 'cart-rebound')}
						<ExternalIcon />
						<span className="cr-sr-only">
							{__('(opens in a new tab)', 'cart-rebound')}
						</span>
					</a>
				)}
			</header>

			<nav className="cr-tabs">
				{tabs.map((tab) => (
					<AdminLink
						key={tab.to}
						to={tab.to}
						end={tab.end}
						className={tabClass}
					>
						{tab.label}
					</AdminLink>
				))}
			</nav>

			<div className={wide ? 'cr-page' : 'cr-page is-narrow'}>
				<ReviewPrompt visible={REVIEW_ROUTES.includes(pathname)} />
				<Outlet />
			</div>
		</div>
	);
};
