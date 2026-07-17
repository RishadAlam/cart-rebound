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
	plugins: [
		react({
			babel: {
				shouldPrintComment: (comment: string): boolean =>
					comment.includes('translators:'),
			},
		}),
	],
	esbuild: {
		// Keep WordPress translator guidance beside placeholder strings after
		// minification so POT generation can audit the production artifact.
		legalComments: 'inline',
	},
	resolve: {
		alias: [
			{
				find: /^react\/jsx(?:-dev)?-runtime$/,
				replacement: resolvePath(
					'./resources/js/admin/compat/react-jsx-runtime.ts'
				),
			},
			{ find: '@', replacement: resolvePath('./resources/js/admin') },
		],
	},
	build: {
		outDir: resolvePath('./public/build'),
		emptyOutDir: true,
		manifest: true,
		minify: 'terser',
		terserOptions: {
			format: {
				comments: /translators:|@license|@preserve|^!/i,
			},
		},
		// Emit a real stylesheet that WordPress can enqueue instead of injecting
		// CSS into the JavaScript bundle at runtime.
		cssCodeSplit: false,
		rollupOptions: {
			external: [
				'@wordpress/i18n',
				'react',
				'react-dom',
				'react-dom/client',
			],
			input: {
				admin: resolvePath('./resources/js/admin/main.tsx'),
			},
			output: {
				format: 'iife',
				name: 'CartReboundAdmin',
				inlineDynamicImports: true,
				globals: {
					'@wordpress/i18n': 'wp.i18n',
					react: 'React',
					'react-dom': 'ReactDOM',
					'react-dom/client': 'ReactDOM',
				},
			},
		},
	},
	server: {
		cors: true,
	},
});
