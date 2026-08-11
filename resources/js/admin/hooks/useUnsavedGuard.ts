/**
 * Don't let a form throw away work without asking.
 *
 * Every settings screen here keeps its edits in component state, and every one of
 * them threw those edits away silently: the tab strip is a router link, so one
 * click unmounted the form mid-edit with no prompt and nothing saved. Closing the
 * browser tab did the same. On a screen where a change costs a shopper a discount
 * or an email, "I thought I'd saved that" is an expensive sentence.
 *
 * Two exits need covering and they are covered differently:
 *
 * 1. Leaving the page — `beforeunload`, which the browser turns into its own
 *    generic prompt. The listener is only attached while there is something to
 *    lose, because a page that always blocks unload is a page people learn to
 *    dismiss without reading.
 *
 * 2. Moving between tabs inside the app — a router navigation, which no browser
 *    event covers. `useBlocker` needs the data router this app already uses
 *    (createHashRouter), and it hands us the decision.
 */
import { useEffect } from 'react';
import { useBlocker } from 'react-router-dom';
import { __ } from '@wordpress/i18n';

/**
 * Guard unsaved edits on the screen that calls this.
 * @param dirty Whether the form currently holds changes that are not saved.
 */
export const useUnsavedGuard = (dirty: boolean): void => {
	useEffect(() => {
		if (!dirty) {
			return;
		}

		const onBeforeUnload = (event: BeforeUnloadEvent) => {
			// Setting returnValue is what actually triggers the prompt; the
			// browser supplies its own wording and ignores ours.
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener('beforeunload', onBeforeUnload);

		return () => {
			window.removeEventListener('beforeunload', onBeforeUnload);
		};
	}, [dirty]);

	const blocker = useBlocker(dirty);

	useEffect(() => {
		if (blocker.state !== 'blocked') {
			return;
		}

		// eslint-disable-next-line no-alert
		const leave = window.confirm(
			__(
				'You have unsaved changes on this screen. Leave without saving?',
				'cart-rebound'
			)
		);

		if (leave) {
			blocker.proceed();

			return;
		}

		blocker.reset();
	}, [blocker]);
};
