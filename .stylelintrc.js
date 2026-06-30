/**
 * Stylelint configuration — extends the WordPress preset and whitelists the
 * Tailwind at-rules so utility CSS does not raise "unknown at-rule" errors.
 */
export default {
	extends: [ '@wordpress/stylelint-config' ],
	rules: {
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
