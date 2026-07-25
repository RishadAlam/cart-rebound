/**
 * App shell: heading + tab navigation + routed content.
 */
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
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

// Reports and tables get the full admin width; the form pages (templates,
// settings) stay capped so their label/input rows remain scannable.
const WIDE_ROUTES = ['/', '/carts', '/logs'];

export const Layout = () => {
	const { pathname } = useLocation();
	const wide = WIDE_ROUTES.includes(pathname);

	return (
		<div className={wide ? 'cr-app is-wide' : 'cr-app'}>
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
					<NavLink
						key={tab.to}
						to={tab.to}
						end={tab.end}
						className={tabClass}
					>
						{tab.label}
					</NavLink>
				))}
			</nav>

			<Outlet />
		</div>
	);
};
