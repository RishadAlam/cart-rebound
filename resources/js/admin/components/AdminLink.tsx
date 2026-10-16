import type { MouseEvent, ReactNode } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { isPlainLeftClick, pageUrl } from '../lib/adminPages';

interface AdminLinkProps {
	to: string;
	end?: boolean;
	className?: string | ((state: { isActive: boolean }) => string);
	children: ReactNode;
}

export const AdminLink = ({
	to,
	end = false,
	className,
	children,
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
		void navigate(to);
	};

	return (
		<a
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
