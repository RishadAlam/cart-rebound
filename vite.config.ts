/**
 * Vite build configuration.
 *
 * Builds the admin React bundle into public/build with a manifest so PHP can
 * enqueue the hashed asset files. The dev server enables CORS for HMR against a
 * local WordPress instance.
 */
import { rmSync, writeFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig, type Plugin } from 'vite';

const resolvePath = (path: string): string =>
	fileURLToPath(new URL(path, import.meta.url));

const hotFile = resolvePath('./public/hot');

/**
 * Tell WordPress when Vite is actively serving the source entry. The marker is
 * removed on a normal server shutdown and before every production build.
 */
const wordpressHotFile = (): Plugin => ({
	name: 'cart-rebound-wordpress-hot-file',
	configResolved: ({ command }) => {
		if (command === 'build') {
			rmSync(hotFile, { force: true });
		}
	},
	configureServer: (server) => {
		const removeHotFile = (): void => rmSync(hotFile, { force: true });
		const detachCleanup = (): void => {
			process.off('SIGHUP', removeHotFile);
			process.off('SIGINT', removeHotFile);
			process.off('SIGQUIT', removeHotFile);
			process.off('SIGTERM', removeHotFile);
			process.off('exit', removeHotFile);
		};

		process.once('SIGHUP', removeHotFile);
		process.once('SIGINT', removeHotFile);
		process.once('SIGQUIT', removeHotFile);
		process.once('SIGTERM', removeHotFile);
		process.once('exit', removeHotFile);

		server.httpServer?.once('listening', () => {
			const address = server.httpServer?.address();
			const port =
				typeof address === 'object' && address !== null
					? address.port
					: 5173;
			const protocol = server.config.server.https ? 'https' : 'http';

			writeFileSync(
				hotFile,
				`${JSON.stringify({ url: `${protocol}://localhost:${port}` })}\n`,
				'utf8'
			);
		});
		server.httpServer?.once('close', () => {
			removeHotFile();
			detachCleanup();
		});
	},
});

export default defineConfig({
	root: resolvePath('./resources/js'),
	base: './',
	plugins: [
		wordpressHotFile(),
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
		host: '127.0.0.1',
		port: 5173,
		strictPort: true,
	},
});
