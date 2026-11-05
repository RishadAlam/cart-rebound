import { __, _x } from '@wordpress/i18n';

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

export const statusMeaning = (status: string): string => {
	switch (status) {
		case 'active':
			return __(
				'Shopper is still building their cart — no order placed yet.',
				'cart-rebound'
			);
		case 'abandoned':
			return __(
				'Idle past the threshold; a recovery email may be scheduled.',
				'cart-rebound'
			);
		case 'pending-payment':
			return __(
				'Order placed but not paid yet (e.g. cheque or bank transfer). Items are kept and no recovery email is sent.',
				'cart-rebound'
			);
		case 'recovered':
			return __(
				'An abandoned cart that came back and paid — a recovery win.',
				'cart-rebound'
			);
		case 'completed':
			return __(
				'Converted to a paid order without ever being abandoned.',
				'cart-rebound'
			);
		case 'lost':
			return __(
				'Abandoned and cleaned up, or a paid order later refunded or cancelled.',
				'cart-rebound'
			);
		default:
			return '';
	}
};
