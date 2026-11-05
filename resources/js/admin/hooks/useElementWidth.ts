import { useLayoutEffect, useState } from 'react';

export const useElementWidth = () => {
	const [el, setEl] = useState<HTMLDivElement | null>(null);
	const [width, setWidth] = useState(0);

	useLayoutEffect(() => {
		if (!el) {
			return;
		}

		setWidth(el.getBoundingClientRect().width);

		const observer = new ResizeObserver((entries) => {
			for (const entry of entries) {
				setWidth(entry.contentRect.width);
			}
		});

		observer.observe(el);

		return () => {
			observer.disconnect();
		};
	}, [el]);

	return { ref: setEl, width };
};
