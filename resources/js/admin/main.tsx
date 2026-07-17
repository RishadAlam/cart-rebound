/**
 * Admin React entry point.
 *
 * Mounts the application into the root node rendered by the admin page.
 */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { __ } from '@wordpress/i18n';
import { App } from './App';
import { ErrorBoundary } from './components/ErrorBoundary';

import './styles/main.css';

const container = document.getElementById('cart-rebound-root');

if (container) {
	const root = createRoot(container);

	if (window.CartRebound) {
		root.render(
			<StrictMode>
				<ErrorBoundary>
					<App />
				</ErrorBoundary>
			</StrictMode>
		);
	} else {
		root.render(
			<div className="cr-app">
				<div className="cr-notice is-error" role="alert">
					{__(
						'Cart Rebound could not initialise: configuration data is missing. Please reload the page.',
						'cart-rebound'
					)}
				</div>
			</div>
		);
	}
}
