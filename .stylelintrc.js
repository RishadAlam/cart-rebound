/**
 * Stylelint configuration — extends the WordPress preset and whitelists the
 * Tailwind at-rules so utility CSS does not raise "unknown at-rule" errors.
 */
export default {
	extends: [ '@wordpress/stylelint-config' ],
	rules: {
		// Allow BEM-style class names (block__element--modifier) for the
		// app's own scoped component CSS, alongside plain kebab-case.
		'selector-class-pattern': [
			'^[a-z][a-z0-9]*(-[a-z0-9]+)*(__[a-z0-9]+(-[a-z0-9]+)*)?(--[a-z0-9]+(-[a-z0-9]+)*)?$',
			{ message: 'Use kebab-case or BEM (block__element--modifier) class names.' },
		],
		// Align with Prettier's formatting (no blank line after an opening brace).
		'rule-empty-line-before': [
			'always',
			{ except: [ 'first-nested' ], ignore: [ 'after-comment' ] },
		],
		// Noisy stylistic rule that fights logical grouping of related selectors.
		'no-descending-specificity': null,
		'at-rule-no-unknown': [
			true,
			{
				ignoreAtRules: [
					'tailwind',
					'apply',
					'layer',
					'screen',
					'variants',
					'responsive',
					'config',
				],
			},
		],
	},
};
