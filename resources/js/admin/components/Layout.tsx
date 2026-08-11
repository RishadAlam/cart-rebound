/**
 * App shell: heading + tab navigation + routed content.
 */
import { useEffect, useRef } from 'react';
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
	const tabsRef = useRef<HTMLElement>(null);

	/*
	 * Bring the current tab into view.
	 *
	 * Eight tabs do not fit a phone, so the strip scrolls — but it opened at the
	 * far left whatever screen you were on. Arriving on Settings from the
	 * WordPress menu, a merchant saw a strip that ended at Analytics and no
	 * indication of where they were: the one tab that answers "where am I" was
	 * the one off-screen. Scrolling is skipped when the strip fits, so nothing
	 * moves on a desktop.
	 */
	useEffect(() => {
		const strip = tabsRef.current;

		if (!strip || strip.scrollWidth <= strip.clientWidth) {
			return;
		}

		/*
		 * Measured after paint and centred by hand rather than with
		 * scrollIntoView: called during layout the browser settled fifteen pixels
		 * short and clipped the end of the label, which is the whole failure this
		 * is here to prevent. The browser clamps the assignment to the scrollable
		 * range, so the first and last tabs simply sit against their edge.
		 */
		const centre = () => {
			const active = strip.querySelector('.is-active');

			if (!active) {
				return;
			}

			const stripBox = strip.getBoundingClientRect();
			const tabBox = active.getBoundingClientRect();

			strip.scrollLeft +=
				tabBox.left -
				stripBox.left -
				(stripBox.width - tabBox.width) / 2;
		};

		// Twice: the first pass runs before the admin stylesheet has finished
		// settling tab widths and lands short, which clips the end of the very
		// label it is trying to reveal. The second lands on the real geometry.
		let second = 0;
		const first = window.requestAnimationFrame(() => {
			centre();
			second = window.requestAnimationFrame(centre);
		});

		return () => {
			window.cancelAnimationFrame(first);
			window.cancelAnimationFrame(second);
		};
	}, [pathname]);

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

			<nav className="cr-tabs" ref={tabsRef}>
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
