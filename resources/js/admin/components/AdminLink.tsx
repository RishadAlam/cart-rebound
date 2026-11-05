import type { AnchorHTMLAttributes, MouseEvent, ReactNode } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { isPlainLeftClick, pageUrl } from '../lib/adminPages';

type AdminLinkProps = Omit<
	AnchorHTMLAttributes<HTMLAnchorElement>,
	'href' | 'className' | 'onClick'
> & {
	to: string;
	end?: boolean;
	state?: unknown;
	className?: string | ((state: { isActive: boolean }) => string);
	children: ReactNode;
};

export const AdminLink = ({
	to,
	end = false,
	state,
	className,
	children,
	...rest
}: AdminLinkProps) => {
	const navigate = useNavigate();
	const { pathname } = useLocation();
	const isActive = end
		? pathname === to
		: pathname === to || pathname.startsWith(`${to}/`);

	const onClick = (event: MouseEvent<HTMLAnchorElement>) => {
		if (!isPlainLeftClick(event)) {
			return;
		}

		event.preventDefault();
		void navigate(to, state === undefined ? undefined : { state });
	};

	return (
		<a
			{...rest}
			href={pageUrl(to)}
			className={
				typeof className === 'function'
					? className({ isActive })
					: className
			}
			aria-current={isActive ? 'page' : undefined}
			onClick={onClick}
		>
			{children}
		</a>
	);
};
