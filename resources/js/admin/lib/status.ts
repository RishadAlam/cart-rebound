/**
 * Shared cart lifecycle status helpers.
 *
 * Kept in one place so the Carts table and the Dashboard reports translate the
 * same status with the same string and context.
 */
import { _x } from '@wordpress/i18n';

export const statusLabel = (status: string): string => {
	switch (status) {
		case 'active':
			return _x('Active', 'cart status', 'cart-rebound');
		case 'abandoned':
			return _x('Abandoned', 'cart status', 'cart-rebound');
		case 'pending-payment':
			return _x('Pending payment', 'cart status', 'cart-rebound');
		case 'recovered':
			return _x('Recovered', 'cart status', 'cart-rebound');
		case 'completed':
			return _x('Completed', 'cart status', 'cart-rebound');
		case 'lost':
			return _x('Lost', 'cart status', 'cart-rebound');
		default:
			return status;
	}
};
