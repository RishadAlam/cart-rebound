/**
 * Turning a failed request into something a shop owner can act on.
 *
 * Axios rejects with "Request failed with status code 403", which tells a
 * merchant nothing and a developer barely more. WordPress, meanwhile, puts a
 * written explanation in the response body — the plugin's own middleware returns
 * "Your session has expired. Please reload the page and try again." for exactly
 * the case that produces most 403s, a wp-admin tab left open past its nonce.
 * That message is the one worth showing, so it is preferred over the transport's.
 */
import { isAxiosError } from 'axios';
import { __ } from '@wordpress/i18n';

interface RestErrorBody {
	message?: unknown;
	code?: unknown;
}

/**
 * The clearest available description of why a request failed.
 * @param error    Whatever the mutation or query rejected with.
 * @param fallback What to say when nothing better is available.
 */
export const errorMessage = (error: unknown, fallback: string): string => {
	if (isAxiosError(error)) {
		const body = error.response?.data as RestErrorBody | undefined;

		if (typeof body?.message === 'string' && body.message !== '') {
			return body.message;
		}

		// No body to read: say what the class of failure was, since "403" and
		// "the server is down" call for different actions.
		if (error.response === undefined) {
			return __(
				'Could not reach the site. Check your connection and try again.',
				'cart-rebound'
			);
		}

		if (error.response.status === 403 || error.response.status === 401) {
			return __(
				'Your session has expired. Please reload the page and try again.',
				'cart-rebound'
			);
		}

		return fallback;
	}

	if (error instanceof Error && error.message !== '') {
		// A message the app raised itself is already written for a person.
		return error.message;
	}

	return fallback;
};
