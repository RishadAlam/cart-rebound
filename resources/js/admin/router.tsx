/**
 * Admin sub-routing (hash-based, so it works inside wp-admin).
 */
import { createHashRouter } from 'react-router-dom';
import { Layout } from './components/Layout';
import { Carts } from './pages/Carts';
import { Dashboard } from './pages/Dashboard';
import { Log } from './pages/Log';
import { Settings } from './pages/Settings';
import { Templates } from './pages/Templates';

export const router = createHashRouter([
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
]);
