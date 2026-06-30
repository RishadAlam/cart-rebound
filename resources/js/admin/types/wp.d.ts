/**
 * Ambient typings for the data WordPress injects into the page via
 * `wp_add_inline_script` as `window.CartRebound`.
 */
export {};

declare global {
	interface CartReboundBootData {
		apiUrl: string;
		nonce: string;
		currentUser: {
			id: number;
			caps: string[];
		};
	}

	interface Window {
		// Injected by wp_add_inline_script; absent if the boot script failed to run.
		CartRebound?: CartReboundBootData;
	}
}
