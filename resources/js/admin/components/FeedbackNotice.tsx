import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';

export type Feedback = { type: 'success' | 'error'; message: string };

const SUCCESS_TIMEOUT = 4000;

// Errors stay until dismissed or replaced: a reason that vanishes after a few
// seconds is easy to miss.
export const useFeedback = () => {
	const [feedback, setFeedback] = useState<Feedback | null>(null);

	useEffect(() => {
		if (feedback?.type !== 'success') {
			return;
		}

		const timer = window.setTimeout(() => {
			setFeedback(null);
		}, SUCCESS_TIMEOUT);

		return () => {
			window.clearTimeout(timer);
		};
	}, [feedback]);

	return [feedback, setFeedback] as const;
};

const CloseIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<path
			d="m4.5 4.5 7 7m0-7-7 7"
			stroke="currentColor"
			strokeWidth="1.4"
			strokeLinecap="round"
		/>
	</svg>
);

// The live region stays mounted so screen readers announce each new message;
// one inserted together with its text is often skipped.
export const FeedbackNotice = ({
	feedback,
	onDismiss,
}: {
	feedback: Feedback | null;
	onDismiss: () => void;
}) => (
	<>
		<span
			className="cr-sr-only"
			role="status"
			aria-live="polite"
			aria-atomic="true"
		>
			{feedback?.message ?? ''}
		</span>
		{feedback && (
			<div className={`cr-notice is-${feedback.type} cr-feedback`}>
				<span className="cr-feedback__text">{feedback.message}</span>
				<button
					type="button"
					className="cr-feedback__close"
					aria-label={__('Dismiss notice', 'cart-rebound')}
					onClick={onDismiss}
				>
					<CloseIcon />
				</button>
			</div>
		)}
	</>
);
