import { isAxiosError } from 'axios';
import { __ } from '@wordpress/i18n';

// WP_Error responses carry the readable reason in the body; axios's own
// message is only "Request failed with status code 4xx".
export const messageOf = (error: unknown): string => {
	if (isAxiosError<{ message?: unknown }>(error)) {
		const message = error.response?.data?.message;

		if (typeof message === 'string' && message !== '') {
			return message;
		}
	}

	return error instanceof Error
		? error.message
		: __('Something went wrong.', 'cart-rebound');
};
