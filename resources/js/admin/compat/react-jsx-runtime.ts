/**
 * Compatibility bridge for packages compiled with React's automatic JSX
 * runtime. WordPress 6.2 exposes React itself, but does not guarantee the
 * separate `react-jsx-runtime` script handle used by newer core releases.
 */
import * as React from 'react';

type Props = Record<string, unknown> | null;

const createElement = (
	type: React.ElementType,
	props: Props,
	key?: React.Key
): React.ReactElement =>
	React.createElement(
		type,
		key === undefined ? props : { ...(props ?? {}), key }
	);

export const Fragment = React.Fragment;
export const jsx = createElement;
export const jsxs = createElement;
export const jsxDEV = createElement;
