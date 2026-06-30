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
	{ to: '/settings', label: 'Settings', end: false },
];

const linkClass = ({ isActive }: { isActive: boolean }): string =>
	isActive
		? 'border-b-2 border-blue-600 pb-2 font-semibold text-blue-600'
		: 'pb-2 text-gray-600 hover:text-gray-900';

export const Layout = () => (
	<div className="cart-rebound-app p-6">
		<h1 className="text-xl font-semibold">Cart Rebound</h1>

		<nav className="mt-4 flex gap-6 border-b border-gray-200">
			{tabs.map((tab) => (
				<NavLink
					key={tab.to}
					to={tab.to}
					end={tab.end}
					className={linkClass}
				>
					{tab.label}
				</NavLink>
			))}
		</nav>

		<div className="mt-6">
			<Outlet />
		</div>
	</div>
);
