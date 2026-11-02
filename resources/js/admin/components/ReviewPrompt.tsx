import { useEffect, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useAnswerReviewPrompt } from '../hooks/useApi';
import type { ReviewChoice } from '../types/api';
import { FeedbackNotice, useFeedback } from './FeedbackNotice';

const RecoveredIcon = () => (
	<svg
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="1.6"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
	>
		<path d="M4.5 11a7.5 7.5 0 0 1 13.2-4.3" />
		<path d="M18.5 3.5v3.7h-3.7" />
		<path d="M19.5 13a7.5 7.5 0 0 1-13.2 4.3" />
		<path d="M5.5 20.5v-3.7h3.7" />
		<path d="m9 12 2.2 2.2L15.2 10" />
	</svg>
);

const ExternalIcon = () => (
	<svg
		viewBox="0 0 16 16"
		fill="none"
		stroke="currentColor"
		strokeWidth="1.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
	>
		<path d="M9.5 2.5h4v4" />
		<path d="m13.5 2.5-6 6" />
		<path d="M11.5 9.5v3a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h3" />
	</svg>
);

const ChevronIcon = () => (
	<svg
		viewBox="0 0 16 16"
		fill="none"
		stroke="currentColor"
		strokeWidth="1.6"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
	>
		<path d="m6 3.5 4.5 4.5L6 12.5" />
	</svg>
);

const AMOUNT_SLOT = '\u0000';

// The amount is bolded inside a translated sentence, so split on a placeholder
// rather than concatenating fragments translators cannot reorder.
const RecoveryResult = ({
	orderNumber,
	amount,
}: {
	orderNumber: string;
	amount: string;
}) => {
	if (amount === '') {
		return (
			<>
				{sprintf(
					/* translators: %s: order number. */
					__('Order #%s came back to your store.', 'cart-rebound'),
					orderNumber
				)}
			</>
		);
	}

	const [before, after = ''] = sprintf(
		/* translators: 1: order number, 2: formatted order total. */
		__('Order #%1$s brought %2$s back to your store.', 'cart-rebound'),
		orderNumber,
		AMOUNT_SLOT
	).split(AMOUNT_SLOT);

	return (
		<>
			{before}
			<strong className="cr-review__amount">{amount}</strong>
			{after}
		</>
	);
};

export const ReviewPrompt = ({ visible }: { visible: boolean }) => {
	const prompt = window.CartRebound?.reviewPrompt ?? null;
	const [open, setOpen] = useState(prompt !== null);
	const [failed, setFailed] = useState<ReviewChoice | null>(null);
	const [feedback, setFeedback] = useFeedback();
	const answer = useAnswerReviewPrompt();
	const statusRef = useRef<HTMLDivElement>(null);

	// The control that had focus unmounts with the banner; hand focus to the confirmation.
	useEffect(() => {
		if (!open && prompt) {
			statusRef.current?.focus();
		}
	}, [open, prompt]);

	if (!prompt) {
		return null;
	}

	const confirmations: Record<ReviewChoice, string> = {
		review: __(
			'Thanks for taking the time. The review form opened in a new tab.',
			'cart-rebound'
		),
		later: sprintf(
			/* translators: %d: number of days until the reminder. */
			_n(
				'Got it. We’ll remind you in %d day.',
				'Got it. We’ll remind you in %d days.',
				prompt.snoozeDays,
				'cart-rebound'
			),
			prompt.snoozeDays
		),
		dismiss: __('Got it. You won’t be asked again.', 'cart-rebound'),
	};

	const choose = (choice: ReviewChoice) => {
		setFailed(null);
		answer.mutate(choice, {
			onSuccess: () => {
				setOpen(false);
				setFeedback({
					type: 'success',
					message: confirmations[choice],
				});
			},
			onError: () => setFailed(choice),
		});
	};

	const saveError = (
		<p className="cr-review__error" role="alert">
			{__(
				'Your choice could not be saved. Please try again.',
				'cart-rebound'
			)}
		</p>
	);

	const notice = (
		<div ref={statusRef} tabIndex={-1} className="cr-review-status">
			<FeedbackNotice
				feedback={feedback}
				onDismiss={() => setFeedback(null)}
			/>
		</div>
	);

	if (!open || !visible) {
		return notice;
	}

	return (
		<>
			{notice}
			<section
				className="cr-review"
				aria-labelledby="cr-review-title"
				aria-busy={answer.isPending}
			>
				<div className="cr-review__main">
					<span className="cr-review__icon">
						<RecoveredIcon />
					</span>
					<div className="cr-review__body">
						<h2 id="cr-review-title" className="cr-review__title">
							{sprintf(
								/* translators: %s: number of abandoned carts Cart Rebound has recovered. */
								_n(
									'%s abandoned cart recovered.',
									'%s abandoned carts recovered.',
									prompt.recoveries,
									'cart-rebound'
								),
								prompt.recoveriesText
							)}
						</h2>
						<p className="cr-review__result">
							<RecoveryResult
								orderNumber={prompt.orderNumber}
								amount={prompt.amount}
							/>
						</p>
						<h3 className="cr-review__question">
							{__(
								'How has Cart Rebound worked for you?',
								'cart-rebound'
							)}
						</h3>
						<p className="cr-review__text">
							{__(
								'Your honest review helps other store owners decide.',
								'cart-rebound'
							)}
						</p>
					</div>
					<div className="cr-review__cta">
						<a
							className="cr-btn is-primary cr-review__write"
							href={prompt.reviewUrl}
							target="_blank"
							rel="noopener noreferrer"
							onClick={() => choose('review')}
						>
							{__('Write a review', 'cart-rebound')}
							<ExternalIcon />
							<span className="cr-sr-only">
								{__('(opens in a new tab)', 'cart-rebound')}
							</span>
						</a>
						<p className="cr-review__note">
							{__(
								'WordPress.org account required.',
								'cart-rebound'
							)}
						</p>
						{failed === 'review' && saveError}
					</div>
				</div>
				<div className="cr-review__foot">
					<details className="cr-review__help">
						<summary>
							<ChevronIcon />
							{__('Not sure what to write?', 'cart-rebound')}
						</summary>
						<p>
							{__(
								'In your own words: What did you use Cart Rebound for? What worked well, and what could be better? A sentence or two is a useful start.',
								'cart-rebound'
							)}
						</p>
					</details>
					<div className="cr-review__later">
						{failed !== null && failed !== 'review' && saveError}
						<button
							type="button"
							className="cr-review__textbtn"
							disabled={answer.isPending}
							onClick={() => choose('later')}
						>
							{sprintf(
								/* translators: %d: number of days until the reminder. */
								_n(
									'Remind me in %d day',
									'Remind me in %d days',
									prompt.snoozeDays,
									'cart-rebound'
								),
								prompt.snoozeDays
							)}
						</button>
						<button
							type="button"
							className="cr-review__textbtn"
							disabled={answer.isPending}
							onClick={() => choose('dismiss')}
						>
							{__('Don’t ask again', 'cart-rebound')}
						</button>
					</div>
				</div>
			</section>
		</>
	);
};
