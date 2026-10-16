import { useEffect, useRef } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { AdminLink } from './AdminLink';
import { OnboardingWizard } from './OnboardingWizard';

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

	return (
		<div ref={appRef} className={wide ? 'cr-app is-wide' : 'cr-app'}>
			<OnboardingWizard />

			<header className="cr-header">
				<h1 className="cr-header__title">
					{__('Cart Rebound', 'cart-rebound')}
				</h1>
				<p className="cr-header__subtitle">
					{__(
						'Recover more WooCommerce sales with automated emails, secure recovery links, and clear revenue tracking.',
						'cart-rebound'
					)}
				</p>
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

			<Outlet />
		</div>
	);
};
