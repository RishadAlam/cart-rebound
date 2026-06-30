/**
 * Prettier configuration — extends the WordPress preset (tabs, single quotes,
 * spaces inside parentheses) so JS/TS formatting matches WordPress core style.
 */
import wpConfig from '@wordpress/prettier-config';

/** @type {import('prettier').Config} */
export default {
	...wpConfig,
};
