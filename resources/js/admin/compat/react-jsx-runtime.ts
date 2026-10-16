// WordPress 6.2 exposes React but not the separate `react-jsx-runtime` handle that
// newer core ships, so packages built for the automatic JSX runtime need this bridge.
import * as React from 'react';
export type { JSX } from 'react';

type Props = Record<string, unknown> | null;

const createElement = (
	type: React.ElementType,
	props: Props,
	key?: React.Key
): React.ReactElement => {
	const normalizedProps =
		key === undefined ? props : { ...(props ?? {}), key };

	if (!normalizedProps || !('children' in normalizedProps)) {
		return React.createElement(type, normalizedProps);
	}

	const { children: rawChildren, ...rest } = normalizedProps;
	const children = rawChildren as React.ReactNode;

	if (Array.isArray(children)) {
		return React.createElement(
			type,
			rest,
			...(children as React.ReactNode[])
		);
	}

	return React.createElement(type, rest, children);
};

export const Fragment = React.Fragment;
export const jsx = createElement;
export const jsxs = createElement;
export const jsxDEV = createElement;
