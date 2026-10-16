module.exports = {
	root: true,
	parser: '@typescript-eslint/parser',
	parserOptions: {
		project: [ './tsconfig.json', './tsconfig.node.json' ],
		tsconfigRootDir: __dirname,
		ecmaVersion: 2022,
		sourceType: 'module',
		ecmaFeatures: { jsx: true },
	},
	settings: {
		react: { version: 'detect' },
	},
	env: {
		browser: true,
		es2022: true,
	},
	plugins: [ '@typescript-eslint', 'react-hooks' ],
	extends: [
		// Not `recommended-with-formatting`: Prettier owns formatting.
		'plugin:@wordpress/eslint-plugin/recommended',
		'plugin:@typescript-eslint/recommended-type-checked',
		'plugin:react-hooks/recommended',
	],
	ignorePatterns: [ 'public/build', 'vendor', 'node_modules', '*.cjs', 'vite.config.ts' ],
	rules: {
		'react/react-in-jsx-scope': 'off',
	},
};
