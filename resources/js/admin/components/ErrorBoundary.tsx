/**
 * Error boundary so a render-time throw shows a styled fallback instead of a
 * blank admin screen.
 */
import { Component, type ErrorInfo, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';

interface Props {
	children: ReactNode;
}

interface State {
	hasError: boolean;
}

export class ErrorBoundary extends Component<Props, State> {
	public override state: State = { hasError: false };

	public static getDerivedStateFromError(): State {
		return { hasError: true };
	}

	public override componentDidCatch(error: Error, info: ErrorInfo): void {
		// eslint-disable-next-line no-console
		console.error('CartRebound render error:', error, info);
	}

	public override render(): ReactNode {
		if (this.state.hasError) {
			return (
				<div
					className="cart-rebound-error p-6 text-red-600"
					role="alert"
				>
					{__(
						'Something went wrong. Please reload the page.',
						'cart-rebound'
					)}
				</div>
			);
		}

		return this.props.children;
	}
}
