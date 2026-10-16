import { NavigationType } from 'react-router-dom';
import type { createMemoryRouter } from 'react-router-dom';

type AppRouter = ReturnType<typeof createMemoryRouter>;

const pages = window.CartRebound?.pages ?? {};

const pageSlug = (url: string): string | null => {
	try {
		return new URL(url, window.location.href).searchParams.get('page');
	} catch {
		return null;
	}
};

const routeBySlug = new Map(
	Object.entries(pages).map(([route, page]) => [pageSlug(page.url), route])
);

export const pageUrl = (route: string): string =>
	pages[route]?.url ?? pages['/']?.url ?? '';

export const routeForUrl = (url: string): string | undefined =>
	routeBySlug.get(pageSlug(url));

// Bookmarks from the hash-router era still open on the tab they named.
export const initialRoute = (): string => {
	const legacy = window.location.hash.replace(/^#/, '');

	if (legacy.startsWith('/') && pages[legacy]) {
		return legacy;
	}

	return window.CartRebound?.initialRoute ?? '/';
};

const highlightSidebar = (route: string) => {
	const links = document.querySelectorAll<HTMLAnchorElement>(
		'#toplevel_page_cart-rebound .wp-submenu a'
	);

	links.forEach((link) => {
		const current = routeForUrl(link.href) === route;

		link.classList.toggle('current', current);
		link.parentElement?.classList.toggle('current', current);

		if (current) {
			link.setAttribute('aria-current', 'page');
		} else {
			link.removeAttribute('aria-current');
		}
	});
};

const isPlainLeftClick = (event: MouseEvent): boolean =>
	event.button === 0 &&
	!event.metaKey &&
	!event.ctrlKey &&
	!event.shiftKey &&
	!event.altKey;

export const syncWithAdmin = (router: AppRouter) => {
	const startRoute = router.state.location.pathname;
	const serverTitle =
		pages[window.CartRebound?.initialRoute ?? '/']?.title ?? '';
	const titleSuffix =
		serverTitle && document.title.startsWith(serverTitle)
			? document.title.slice(serverTitle.length)
			: null;

	const show = (route: string, action: 'push' | 'replace') => {
		const url = pages[route]?.url;

		if (url && url !== window.location.href) {
			window.history[action === 'push' ? 'pushState' : 'replaceState'](
				null,
				'',
				url
			);
		}

		if (titleSuffix !== null && pages[route]) {
			document.title = pages[route].title + titleSuffix;
		}

		highlightSidebar(route);
	};

	show(startRoute, 'replace');

	let shown = startRoute;

	router.subscribe((state) => {
		const route = state.location.pathname;

		if (route === shown) {
			return;
		}

		shown = route;
		show(
			route,
			state.historyAction === NavigationType.Push ? 'push' : 'replace'
		);
	});

	window.addEventListener('popstate', () => {
		const route = routeForUrl(window.location.href);

		if (route !== undefined) {
			void router.navigate(route, { replace: true });
		}
	});

	document
		.querySelector('#toplevel_page_cart-rebound')
		?.addEventListener('click', (event) => {
			const link = (event.target as Element).closest('a');
			const route = link ? routeForUrl(link.href) : undefined;

			if (route === undefined || !isPlainLeftClick(event as MouseEvent)) {
				return;
			}

			event.preventDefault();
			void router.navigate(route);
		});
};
