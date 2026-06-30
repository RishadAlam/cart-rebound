/** @type {import('tailwindcss').Config} */
export default {
	content: [ './resources/js/**/*.{ts,tsx}', './resources/views/**/*.php' ],
	corePlugins: {
		// Disabled so Tailwind's reset does not fight wp-admin's global styles.
		preflight: false,
	},
	theme: {
		extend: {},
	},
	plugins: [],
};
