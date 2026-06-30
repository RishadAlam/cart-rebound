/**
 * ESLint configuration (eslintrc format).
 *
 * Layers the WordPress ruleset, type-aware TypeScript rules and the React
 * Hooks rules. We use `recommended` (not `recommended-with-formatting`) because
 * formatting is owned by the separate Prettier step; this preset aligns ESLint
 * with `@wordpress/prettier-config` instead of enforcing competing formatting
 * rules that would fight Prettier.
 */
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
		'plugin:@wordpress/eslint-plugin/recommended',
		'plugin:@typescript-eslint/recommended-type-checked',
		'plugin:react-hooks/recommended',
	],
	ignorePatterns: [ 'public/build', 'vendor', 'node_modules', '*.cjs', 'vite.config.ts' ],
	rules: {
		// React 17+ automatic runtime: the React import is not required.
		'react/react-in-jsx-scope': 'off',
	},
};
