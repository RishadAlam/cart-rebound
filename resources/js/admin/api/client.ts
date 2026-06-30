/**
 * Axios client configured for the WordPress REST API.
 *
 * The request interceptor attaches the REST nonce to every call so
 * cookie-authenticated requests pass WordPress's nonce check. `withCredentials`
 * keeps the auth cookie attached even when the REST root is on another origin.
 */
import axios from 'axios';

const boot = window.CartRebound;

export const apiClient = axios.create({
	baseURL: boot?.apiUrl ?? '',
	withCredentials: true,
	headers: {
		'Content-Type': 'application/json',
	},
});

apiClient.interceptors.request.use((config) => {
	if (boot?.nonce) {
		config.headers.set('X-WP-Nonce', boot.nonce);
	}

	return config;
});
