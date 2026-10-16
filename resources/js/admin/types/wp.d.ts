export {};

declare global {
	interface CartReboundBootData {
		apiUrl: string;
		nonce: string;
		initialRoute?: string;
		pages?: Record<string, { url: string; title: string }>;
		currency: {
			code: string;
			symbol: string;
			position: 'left' | 'right' | 'left_space' | 'right_space';
			decimalSeparator: string;
			thousandSeparator: string;
			decimals: number;
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
		CartRebound?: CartReboundBootData;
		jQuery?: (target: Document) => { trigger: (event: string) => void };
		wp?: {
			media?: (options?: Record<string, unknown>) => WpMediaFrame;
		};
	}
}
