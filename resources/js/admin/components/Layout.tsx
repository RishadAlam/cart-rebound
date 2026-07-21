/**
 * App shell: heading + tab navigation + routed content.
 */
import { NavLink, Outlet } from 'react-router-dom';
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

export const Layout = () => (
	<div className="cr-app">
		<OnboardingWizard />

		<header className="cr-header">
			<h1 className="cr-header__title">
				{__('Cart Rebound', 'cart-rebound')}
			</h1>
			<p className="cr-header__subtitle">
				{__(
					'Recover abandoned WooCommerce carts with tokenized links and automated emails — and track the revenue you win back.',
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
