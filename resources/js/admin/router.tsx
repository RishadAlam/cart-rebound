import { createMemoryRouter } from 'react-router-dom';
import { Layout } from './components/Layout';
import { Carts } from './pages/Carts';
import { Dashboard } from './pages/Dashboard';
import { Log } from './pages/Log';
import { Settings } from './pages/Settings';
import { Templates } from './pages/Templates';
import { initialRoute, syncWithAdmin } from './lib/adminPages';

// Routes live in the page query arg, not the path, so a memory router drives the
// views and syncWithAdmin mirrors each route onto its WordPress submenu URL.
export const router = createMemoryRouter(
	[
		{
			path: '/',
			element: <Layout />,
			children: [
				{ index: true, element: <Dashboard /> },
				{ path: 'carts', element: <Carts /> },
				{ path: 'templates', element: <Templates /> },
				{ path: 'logs', element: <Log /> },
				{ path: 'settings', element: <Settings /> },
			],
		},
	],
	{ initialEntries: [initialRoute()] }
);

syncWithAdmin(router);
