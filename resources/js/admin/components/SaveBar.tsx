/**
 * The bar at the foot of every settings form.
 *
 * It existed three times — Settings, Sequence, Rules — and all three rendered
 * exactly two of the four states a save can be in. There was no failure state at
 * all: a rejected POST (an expired nonce on a tab left open overnight, a fatal
 * in another plugin's hook, a dropped connection) put the button back to "Save"
 * and changed nothing else on screen, so the merchant walked away believing
 * settings had been stored that had not.
 *
 * And "Saved." never went away once shown, because nothing reset the mutation —
 * so a stale confirmation sat under a form the merchant had since edited.
 *
 * One component, four states: idle, saving, saved (which expires), failed (which
 * says why, from the server's own words).
 */
import { useEffect, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { errorMessage } from '../lib/errors';

/** How long a confirmation stays up before the bar returns to idle. */
const SAVED_VISIBLE_MS = 4000;

interface Props {
	/** Label for the primary button in its resting state. */
	label: string;
	/** Confirmation shown after a successful write. */
	savedLabel: string;
	/** What to say when the server gave no message of its own. */
	errorFallback: string;
	isPending: boolean;
	/** Blocks the save while the form holds something the server would reject. */
	disabled?: boolean;
	isSuccess: boolean;
	isError: boolean;
	error: unknown;
	/** Clears the mutation state, so a confirmation does not outlive its edit. */
	onReset: () => void;
	/** Extra controls that belong beside the save button. */
	children?: ReactNode;
}

export const SaveBar = ({
	label,
	savedLabel,
	errorFallback,
	isPending,
	disabled = false,
	isSuccess,
	isError,
	error,
	onReset,
	children,
}: Props) => {
	useEffect(() => {
		if (!isSuccess) {
			return;
		}

		const timer = window.setTimeout(onReset, SAVED_VISIBLE_MS);

		return () => {
			window.clearTimeout(timer);
		};
	}, [isSuccess, onReset]);

	return (
		<div className="cr-savebar">
			<button
				type="submit"
				className="cr-btn is-primary"
				disabled={isPending || disabled}
			>
				{isPending ? __('Saving…', 'cart-rebound') : label}
			</button>

			{children}

			{isSuccess && <span className="cr-saved">{savedLabel}</span>}

			{isError && (
				<span className="cr-savebar__error" role="alert">
					{errorMessage(error, errorFallback)}
				</span>
			)}
		</div>
	);
};
