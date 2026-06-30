/**
 * Admin sub-routing (hash-based, so it works inside wp-admin).
 */
import { createHashRouter } from 'react-router-dom';
import { Layout } from './components/Layout';
import { Carts } from './pages/Carts';
import { Dashboard } from './pages/Dashboard';
import { Settings } from './pages/Settings';

export const router = createHashRouter([
	{
		path: '/',
		element: <Layout />,
		children: [
			{ index: true, element: <Dashboard /> },
			{ path: 'carts', element: <Carts /> },
			{ path: 'settings', element: <Settings /> },
		],
	},
]);
