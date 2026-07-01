/**
 * App shell: heading + tab navigation + routed content.
 */
import { NavLink, Outlet } from 'react-router-dom';

interface Tab {
	to: string;
	label: string;
	end: boolean;
}

const tabs: Tab[] = [
	{ to: '/', label: 'Dashboard', end: true },
	{ to: '/carts', label: 'Carts', end: false },
	{ to: '/templates', label: 'Templates', end: false },
	{ to: '/settings', label: 'Settings', end: false },
];

const tabClass = ({ isActive }: { isActive: boolean }): string =>
	isActive ? 'cr-tab is-active' : 'cr-tab';

export const Layout = () => (
	<div className="cr-app">
		<header className="cr-header">
			<h1 className="cr-header__title">Cart Rebound</h1>
			<p className="cr-header__subtitle">
				Recover abandoned WooCommerce carts with tokenized links and
				automated emails — and track the revenue you win back.
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
