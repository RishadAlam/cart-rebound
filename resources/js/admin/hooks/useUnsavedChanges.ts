import { useEffect } from 'react';
import { useBlocker } from 'react-router-dom';

export const useUnsavedChanges = (dirty: boolean, message: string) => {
	const blocker = useBlocker(
		({ currentLocation, nextLocation }) =>
			dirty && currentLocation.pathname !== nextLocation.pathname
	);

	useEffect(() => {
		if (blocker.state !== 'blocked') {
			return;
		}

		// eslint-disable-next-line no-alert
		if (window.confirm(message)) {
			blocker.proceed();
		} else {
			blocker.reset();
		}
	}, [blocker, message]);

	useEffect(() => {
		if (!dirty) {
			return;
		}

		const onBeforeUnload = (event: BeforeUnloadEvent) => {
			event.preventDefault();
			// Chrome < 119 only prompts when returnValue is set.
			event.returnValue = '';
		};

		window.addEventListener('beforeunload', onBeforeUnload);

		return () => {
			window.removeEventListener('beforeunload', onBeforeUnload);
		};
	}, [dirty]);
};
