export default {
	extends: [ '@wordpress/stylelint-config' ],
	rules: {
		'selector-class-pattern': [
			'^[a-z][a-z0-9]*(-[a-z0-9]+)*(__[a-z0-9]+(-[a-z0-9]+)*)?(--[a-z0-9]+(-[a-z0-9]+)*)?$',
			{ message: 'Use kebab-case or BEM (block__element--modifier) class names.' },
		],
		// Matches Prettier: no blank line after an opening brace.
		'rule-empty-line-before': [
			'always',
			{ except: [ 'first-nested' ], ignore: [ 'after-comment' ] },
		],
		// Fights logical grouping of related selectors.
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
