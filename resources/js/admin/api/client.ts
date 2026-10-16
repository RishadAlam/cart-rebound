import axios from 'axios';

const boot = window.CartRebound;

export const apiClient = axios.create({
	baseURL: boot?.apiUrl ?? '',
	// Keeps the auth cookie attached when the REST root is on another origin.
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
