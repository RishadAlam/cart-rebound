/**
 * Vite build configuration.
 *
 * Builds the admin React bundle into public/build with a manifest so PHP can
 * enqueue the hashed asset files. The dev server enables CORS for HMR against a
 * local WordPress instance.
 */
import { fileURLToPath, URL } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const resolvePath = (path: string): string =>
	fileURLToPath(new URL(path, import.meta.url));

export default defineConfig({
	root: resolvePath('./resources/js'),
	base: './',
	plugins: [react()],
	resolve: {
		alias: {
			'@': resolvePath('./resources/js/admin'),
		},
	},
	build: {
		outDir: resolvePath('./public/build'),
		emptyOutDir: true,
		manifest: true,
		rollupOptions: {
			input: {
				admin: resolvePath('./resources/js/admin/main.tsx'),
			},
		},
	},
	server: {
		cors: true,
	},
});
