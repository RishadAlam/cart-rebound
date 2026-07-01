/**
 * Ambient typings for the data WordPress injects into the page via
 * `wp_add_inline_script` as `window.CartRebound`.
 */
export {};

declare global {
	interface CartReboundBootData {
		apiUrl: string;
		nonce: string;
		// Route the WordPress submenu seeded into the hash router at load.
		initialRoute?: string;
		currentUser: {
			id: number;
			caps: string[];
		};
	}

	interface WpMediaAttachment {
		url?: string;
		alt?: string;
	}

	interface WpMediaFrame {
		on: (event: string, handler: () => void) => void;
		open: () => void;
		state: () => {
			get: (key: string) => {
				first: () => { toJSON: () => WpMediaAttachment };
			};
		};
	}

	interface Window {
		// Injected by wp_add_inline_script; absent if the boot script failed to run.
		CartRebound?: CartReboundBootData;
		// The WordPress media library (present when wp_enqueue_media() has run).
		wp?: {
			media?: (options?: Record<string, unknown>) => WpMediaFrame;
		};
	}
}
