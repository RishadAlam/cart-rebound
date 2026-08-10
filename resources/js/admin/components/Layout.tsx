/**
 * App shell: heading + tab navigation + routed content.
 */
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { OnboardingWizard } from './OnboardingWizard';
import { useAddons } from '../hooks/useAddons';
import type { ProFeature } from '../types/api';

interface Tab {
	to: string;
	label: string;
	end: boolean;
	/** Present on tabs an add-on unlocks; absent on the free ones. */
	feature?: ProFeature;
}

const TABS: Tab[] = [
	{ to: '/', label: __('Dashboard', 'cart-rebound'), end: true },
	{ to: '/carts', label: __('Carts', 'cart-rebound'), end: false },
	{ to: '/templates', label: __('Templates', 'cart-rebound'), end: false },
	{
		to: '/sequence',
		label: __('Sequence', 'cart-rebound'),
		end: false,
		feature: 'sequence',
	},
	{
		to: '/analytics',
		label: __('Analytics', 'cart-rebound'),
		end: false,
		feature: 'analytics',
	},
	{
		to: '/rules',
		label: __('Rules', 'cart-rebound'),
		end: false,
		feature: 'rules',
	},
	{ to: '/logs', label: __('Log', 'cart-rebound'), end: false },
	{ to: '/settings', label: __('Settings', 'cart-rebound'), end: false },
];

const tabClass = ({ isActive }: { isActive: boolean }): string =>
	isActive ? 'cr-tab is-active' : 'cr-tab';

/*
 * Two page shapes, and every screen is one of them.
 *
 * Reports and tables take the full admin width, because a chart or a
 * twelve-column table has something to do with the space. Forms hold a fixed
 * measure, because a label/input row stretched across a wide monitor is harder
 * to read, not easier — the eye loses the line on the way back.
 *
 * Deciding it here, once, is what stops three settings screens ending up three
 * different widths.
 */
const WIDE_ROUTES = ['/', '/carts', '/logs', '/analytics'];

export const Layout = () => {
	const { pathname } = useLocation();
	const { features, addons } = useAddons();
	const wide = WIDE_ROUTES.includes(pathname);
	const shell = wide ? 'cr-app is-wide' : 'cr-app is-form';

	// An add-on that is delivering renames the product, because from that point
	// on it is what the merchant bought. Nothing else about the shell changes.
	const addon = addons.find((candidate) => candidate.features.length > 0);
	const title = addon ? addon.name : __('Cart Rebound', 'cart-rebound');

	return (
		<div className={shell}>
			<OnboardingWizard />

			<header className="cr-header">
				<h1 className="cr-header__title">{title}</h1>
				<p className="cr-header__subtitle">
					{__(
						'Recover more WooCommerce sales with automated emails, secure recovery links, and clear revenue tracking.',
						'cart-rebound'
					)}
				</p>
			</header>

			<nav className="cr-tabs">
				{TABS.map((tab) => (
					<NavLink
						key={tab.to}
						to={tab.to}
						end={tab.end}
						className={tabClass}
					>
						{tab.label}
						{tab.feature !== undefined &&
							!features.includes(tab.feature) && (
								<span className="cr-tab__lock">
									{__('Pro', 'cart-rebound')}
								</span>
							)}
					</NavLink>
				))}
			</nav>

			<Outlet />
		</div>
	);
};
