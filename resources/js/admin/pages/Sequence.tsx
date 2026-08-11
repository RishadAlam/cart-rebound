/**
 * Sequence — the ordered follow-ups an abandoned cart receives.
 *
 * Every delay on this screen is measured from the moment the cart was
 * abandoned, never from the previous send. That is the runner's actual
 * behaviour, and it is the one thing about a sequence a merchant has to be able
 * to picture: "1 hour, 1 day, 3 days" is a schedule, whereas a chain of
 * relative offsets silently shifts whenever a step runs late.
 */
import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Combobox } from '../components/Combobox';
import { DurationField } from '../components/DurationField';
import { Switch } from '../components/Field';
import { ProSurface } from '../components/ProSurface';
import { SaveBar } from '../components/SaveBar';
import { useProQuery } from '../hooks/useAddons';
import { useUnsavedGuard } from '../hooks/useUnsavedGuard';
import {
	fetchProOptions,
	fetchProSettings,
	fetchSequence,
	updateProSettings,
} from '../api/pro';
import {
	sampleProOptions,
	sampleProSettings,
	sampleSequence,
} from '../lib/sample';
import {
	formatCount,
	formatDelay,
	formatMoney,
	formatPercent,
} from '../lib/format';
import type {
	ProSettings,
	ProSettingsResponse,
	SequenceStep,
	SequenceStepStatus,
} from '../types/api';

const MAX_STEPS = 20;

const HOURS_PER_DAY = 24;

/** Joins the coupon terms. Not translated: it carries no words to translate. */
const POLICY_SEPARATOR = ' · ';

/*
 * The sample store, built once.
 *
 * Building it inside the component made a new object on every render, which a
 * reset effect then treated as new data — so a locked screen set state, and
 * therefore re-rendered, without ever stopping. The sample is deterministic by
 * design (see lib/sample.ts), so one instance per page load is all it needs.
 */
const SAMPLE_SETTINGS: ProSettingsResponse = {
	settings: sampleProSettings(),
	features: [],
};
const SAMPLE_OVERVIEW = sampleSequence();
const SAMPLE_OPTIONS = sampleProOptions();

/**
 * Say a coupon's lifetime the way the shopper experiences it.
 *
 * Stored in hours because that is what the minter needs; "72 hours" is a number
 * a merchant has to convert before they know whether it is generous.
 * @param hours Lifetime in hours.
 */
const formatExpiry = (hours: number): string => {
	const safe = Math.max(1, Math.round(hours));

	if (safe % HOURS_PER_DAY === 0) {
		const days = safe / HOURS_PER_DAY;

		return sprintf(
			/* translators: %d: number of days. */
			_n(
				'expires after %d day',
				'expires after %d days',
				days,
				'cart-rebound'
			),
			days
		);
	}

	return sprintf(
		/* translators: %d: number of hours. */
		_n(
			'expires after %d hour',
			'expires after %d hours',
			safe,
			'cart-rebound'
		),
		safe
	);
};

/**
 * The coupon policy, in the terms the shopper will meet it on.
 *
 * The policy itself is set on Rules, one screen away, which meant that ticking
 * "include a unique coupon" here was agreeing to an amount, an expiry and a
 * minimum spend that were nowhere on screen. A merchant cannot weigh a discount
 * they cannot see, so the terms are named at the control that spends the money.
 * @param settings The add-on settings this screen already holds.
 * @param currency Store currency code, for a fixed-amount discount.
 */
const couponPolicy = (settings: ProSettings, currency: string): string[] => {
	const amount =
		'percent' === settings.coupon_type
			? formatPercent(settings.coupon_amount)
			: formatMoney(settings.coupon_amount, currency);

	const parts = [
		sprintf(
			/* translators: %s: discount amount, e.g. "10%" or "$5.00". */
			__('%s off', 'cart-rebound'),
			amount
		),
		formatExpiry(settings.coupon_expiry_hours),
	];

	if (settings.coupon_min_amount > 0) {
		parts.push(
			sprintf(
				/* translators: %s: minimum order value as money. */
				__('%s minimum spend', 'cart-rebound'),
				formatMoney(settings.coupon_min_amount, currency)
			)
		);
	}

	if (settings.coupon_restrict_email) {
		parts.push(__('locked to the recipient', 'cart-rebound'));
	}

	if (settings.coupon_free_shipping) {
		parts.push(__('free shipping included', 'cart-rebound'));
	}

	return parts;
};

/**
 * A new step lands a day after the one before it, which is the usual shape.
 * @param steps The steps already in the plan.
 */
const nextDelay = (steps: SequenceStep[]): number => {
	const last = steps[steps.length - 1];

	return last ? last.delay_minutes + 1440 : 60;
};

const StepCard = ({
	step,
	index,
	position,
	status,
	templates,
	policy,
	couponAuto,
	onChange,
	onRemove,
	removable,
}: {
	step: SequenceStep;
	/** Position in the stored plan; identifies this step to the server. */
	index: number;
	/** Position on screen, which is delay order. */
	position: number;
	/** Absent until the step has been saved and the add-on has seen it. */
	status: SequenceStepStatus | undefined;
	templates: Array<{ id: string; name: string }>;
	/** The coupon terms this step would hand out, from the Rules policy. */
	policy: string[];
	/** False when Rules is set to fall back to a template's static code. */
	couponAuto: boolean;
	onChange: (index: number, patch: Partial<SequenceStep>) => void;
	onRemove: (index: number) => void;
	removable: boolean;
}) => {
	const id = `cr-step-${index}`;

	return (
		<li className={step.enabled ? 'cr-step' : 'cr-step is-off'}>
			<div className="cr-step__head">
				{/* Numbered by position on screen, which is delay order. The badge
				    printed the pre-sort array index, so adding a step that sorts
				    earlier made the list read 1, 3, 2. */}
				<span className="cr-step__number">{position + 1}</span>

				<div className="cr-step__heading">
					<h3 className="cr-step__title">
						{formatDelay(step.delay_minutes)}
					</h3>
					{/* A disabled step is not in the runner's plan, so it has no
					    queue and no sends of its own to report. */}
					{status && step.enabled && (
						<p className="cr-step__stats">
							{sprintf(
								/* translators: 1: carts waiting on this step, 2: emails already sent. */
								__('%1$s waiting · %2$s sent', 'cart-rebound'),
								formatCount(status.queued),
								formatCount(status.sent)
							)}
						</p>
					)}
				</div>

				<Switch
					id={`${id}-enabled`}
					checked={step.enabled}
					label={__('Enable this step', 'cart-rebound')}
					onChange={(enabled) => {
						onChange(index, { enabled });
					}}
				/>
			</div>

			<div className="cr-step__grid">
				<div className="cr-field">
					<label htmlFor={`${id}-delay`} className="cr-field__label">
						{__('Send after', 'cart-rebound')}
					</label>
					<DurationField
						id={`${id}-delay`}
						minutes={step.delay_minutes}
						unitLabel={__('Delay unit', 'cart-rebound')}
						onChange={(minutes) => {
							onChange(index, { delay_minutes: minutes });
						}}
					/>
					<p className="cr-field__hint">
						{__(
							'Counted from abandonment, not from the previous email.',
							'cart-rebound'
						)}
					</p>
				</div>

				<div className="cr-field">
					<label
						htmlFor={`${id}-template`}
						className="cr-field__label"
					>
						{__('Template', 'cart-rebound')}
					</label>
					<Combobox
						id={`${id}-template`}
						ariaLabel={__('Template for this step', 'cart-rebound')}
						value={step.template_id}
						options={[
							{
								value: '',
								label: __('Default template', 'cart-rebound'),
							},
							...templates.map((template) => ({
								value: template.id,
								label: template.name,
							})),
						]}
						onChange={(next) => {
							onChange(index, { template_id: next });
						}}
					/>
					{status && !status.template_ok && (
						<p className="cr-field__hint is-warning">
							{__(
								'This template no longer exists — the step falls back to the default.',
								'cart-rebound'
							)}
						</p>
					)}
				</div>
			</div>

			<div className="cr-step__foot">
				<div className="cr-step__coupon">
					<label className="cr-check" htmlFor={`${id}-coupon`}>
						<input
							id={`${id}-coupon`}
							type="checkbox"
							checked={step.coupon}
							aria-describedby={
								step.coupon ? `${id}-coupon-policy` : undefined
							}
							onChange={(
								event: ChangeEvent<HTMLInputElement>
							) => {
								onChange(index, {
									coupon: event.target.checked,
								});
							}}
						/>
						<span>
							{__('Include a unique coupon', 'cart-rebound')}
						</span>
					</label>

					{/*
					 * The terms sit under the box rather than beside it: a policy
					 * with a minimum spend runs long, and on a phone this row is
					 * already sharing its width with "Remove step".
					 */}
					{step.coupon && (
						<p
							id={`${id}-coupon-policy`}
							className={
								couponAuto
									? 'cr-step__policy'
									: 'cr-step__policy is-warning'
							}
						>
							{couponAuto
								? policy.join(POLICY_SEPARATOR)
								: __(
										'Unique coupons are switched off in Rules, so this step sends whatever static code its template carries.',
										'cart-rebound'
									)}
						</p>
					)}
				</div>

				{removable && (
					<button
						type="button"
						className="cr-linkbtn is-danger"
						onClick={() => {
							onRemove(index);
						}}
					>
						{__('Remove step', 'cart-rebound')}
					</button>
				)}
			</div>
		</li>
	);
};

export const Sequence = () => {
	const queryClient = useQueryClient();

	const settings = useProQuery(
		'sequence',
		['pro', 'settings'],
		fetchProSettings,
		SAMPLE_SETTINGS
	);
	const overview = useProQuery(
		'sequence',
		['pro', 'sequence'],
		fetchSequence,
		SAMPLE_OVERVIEW
	);
	const options = useProQuery(
		'sequence',
		['pro', 'options'],
		fetchProOptions,
		SAMPLE_OPTIONS,
		{ staleTime: 60_000 }
	);

	const [steps, setSteps] = useState<SequenceStep[] | null>(null);

	/*
	 * Reset the editor when a different set of steps arrives — not when the
	 * response object is merely a new object. Depending on the wrapper meant that
	 * a locked screen, which is handed a fresh sample every render, set state on
	 * every render and re-rendered forever.
	 */
	useEffect(() => {
		setSteps(settings.data.settings.sequence_steps);
	}, [settings.data.settings.sequence_steps]);

	const save = useMutation({
		mutationFn: updateProSettings,
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: ['pro'] });
		},
	});

	const current = steps ?? settings.data.settings.sequence_steps;
	const saved = settings.data.settings.sequence_steps;

	/*
	 * Whether the editor still matches what the server planned.
	 *
	 * The queued/sent counters are the add-on's view of the *saved* plan, matched
	 * to a card by position. Add, remove or retime a step and those positions stop
	 * meaning the same thing — "3 waiting" would then be attached to a step that
	 * does not exist yet. So while there are unsaved edits the counters stand down
	 * rather than lie.
	 */
	const dirty = JSON.stringify(current) !== JSON.stringify(saved);

	useUnsavedGuard(dirty);

	const currency = window.CartRebound?.currency.code ?? '';
	const policy = couponPolicy(settings.data.settings, currency);
	const couponAuto = settings.data.settings.coupon_auto;
	const anyCoupon = current.some((step) => step.coupon);

	const patchStep = (index: number, patch: Partial<SequenceStep>) => {
		setSteps((previous) =>
			(previous ?? current).map((step, position) =>
				position === index ? { ...step, ...patch } : step
			)
		);
	};

	/*
	 * Removing a step is not a formatting change: the carts queued on it are
	 * re-planned against a shorter sequence the moment this is saved, and the
	 * counter on the card says how many that is. So it asks, and it says.
	 */
	const removeStep = (index: number) => {
		const queued = overview.data.steps.find((row) => row.index === index);
		const waiting = queued?.queued ?? 0;

		// eslint-disable-next-line no-alert
		const confirmed = window.confirm(
			waiting > 0
				? sprintf(
						/* translators: 1: step number, 2: carts currently waiting on it. */
						_n(
							'Remove step %1$d? %2$d cart is waiting on it and will move to the next step that still applies.',
							'Remove step %1$d? %2$d carts are waiting on it and will move to the next step that still applies.',
							waiting,
							'cart-rebound'
						),
						index + 1,
						waiting
					)
				: sprintf(
						/* translators: %d: step number. */
						__('Remove step %d?', 'cart-rebound'),
						index + 1
					)
		);

		if (!confirmed) {
			return;
		}

		setSteps((previous) =>
			(previous ?? current).filter(
				(_step, position) => position !== index
			)
		);
	};

	const addStep = () => {
		setSteps((previous) => {
			const base = previous ?? current;

			return [
				...base,
				{
					enabled: true,
					delay_minutes: nextDelay(base),
					template_id: '',
					coupon: false,
				},
			];
		});
	};

	/*
	 * Two steps at the same delay are not a schedule.
	 *
	 * The runner sorts the plan by delay and advances a single cursor, so a tie is
	 * resolved arbitrarily: one of the two is skipped for every cart, and which
	 * one is not something the merchant chose. Nothing rejected it — the add-on
	 * kept both, the screen listed both, and one simply never sent.
	 */
	const duplicateDelay = (() => {
		const seen = new Set<number>();

		for (const step of current) {
			if (!step.enabled) {
				continue;
			}

			if (seen.has(step.delay_minutes)) {
				return step.delay_minutes;
			}

			seen.add(step.delay_minutes);
		}

		return null;
	})();

	const onSubmit = (event: FormEvent<HTMLFormElement>) => {
		event.preventDefault();

		if (duplicateDelay !== null) {
			return;
		}

		save.mutate({ sequence_steps: current });
	};

	// Ordering the editor by delay matches what the runner does with the plan,
	// so the list on screen is the order the shopper experiences.
	const ordered = [...current]
		.map((step, index) => ({ step, index }))
		.sort(
			(left, right) => left.step.delay_minutes - right.step.delay_minutes
		);

	/*
	 * While the real settings are in flight this screen is handed the sample
	 * store, and an editor pre-filled with someone else's sequence is worse than
	 * an empty one: save it and you have overwritten your own plan with a demo.
	 */
	if (settings.isError || overview.isError) {
		return (
			<div className="cr-notice is-error" role="alert">
				{__(
					'Could not load your follow-up sequence. Reload the page to try again.',
					'cart-rebound'
				)}
			</div>
		);
	}

	if (settings.isLoading) {
		return (
			<div className="cr-card cr-section">
				<div
					className="cr-skeleton"
					style={{ height: 16, width: '35%' }}
				/>
				<div
					className="cr-skeleton"
					style={{ height: 150, width: '100%', marginTop: 16 }}
				/>
				<div
					className="cr-skeleton"
					style={{ height: 150, width: '100%', marginTop: 12 }}
				/>
			</div>
		);
	}

	return (
		<ProSurface
			feature="sequence"
			title={__('Turn one email into a sequence', 'cart-rebound')}
			summary={__(
				'The free plugin sends a single follow-up. Pro sends an ordered series, each with its own timing, template, and coupon.',
				'cart-rebound'
			)}
			points={[
				__(
					'Up to 20 steps, each timed from the moment the cart was abandoned',
					'cart-rebound'
				),
				__(
					'A step stops the moment the cart converts, is paid for, or unsubscribes',
					'cart-rebound'
				),
				__(
					'Attach a unique, expiring coupon to the step where it earns its discount',
					'cart-rebound'
				),
			]}
		>
			<form onSubmit={onSubmit} className="cr-card">
				<div className="cr-section">
					<h2 className="cr-section__title">
						{__('Follow-up sequence', 'cart-rebound')}
					</h2>
					<p className="cr-section__desc">
						{__(
							'Each step is sent to a cart that is still abandoned when its moment arrives. A cart that converts, is paid for, or unsubscribes leaves the sequence immediately.',
							'cart-rebound'
						)}
					</p>

					{/*
					 * The coupon policy is set on Rules and spent here, so it is
					 * stated once, in full, next to the steps that hand it out —
					 * and only when a step actually does.
					 */}
					{anyCoupon && (
						<p className="cr-section__desc cr-section__desc--policy">
							{couponAuto
								? sprintf(
										/* translators: %s: the coupon terms, e.g. "10% off · expires after 48 hours". */
										__(
											'Steps marked below mint a fresh code per cart: %s.',
											'cart-rebound'
										),
										policy.join(POLICY_SEPARATOR)
									)
								: __(
										'Unique coupons are switched off, so steps marked below fall back to the static code on their template.',
										'cart-rebound'
									)}{' '}
							<Link to="/rules">
								{__('Change the coupon policy', 'cart-rebound')}
							</Link>
						</p>
					)}

					{!overview.data.running && (
						<div className="cr-notice is-warning">
							{__(
								'Recovery email is switched off in Settings, so no step is being sent. Turn it back on to run the sequence.',
								'cart-rebound'
							)}
						</div>
					)}

					{overview.data.warnings.map((warning) => (
						<div key={warning} className="cr-notice is-warning">
							{warning}
						</div>
					))}

					{duplicateDelay !== null && (
						<div className="cr-notice is-error" role="alert">
							{sprintf(
								/* translators: %s: the shared delay, e.g. "1 day after abandonment". */
								__(
									'Two enabled steps are both set to %s. Give them different delays — a shopper cannot receive two follow-ups at the same moment, so one of them would simply never send.',
									'cart-rebound'
								),
								formatDelay(duplicateDelay)
							)}
						</div>
					)}

					<ul className="cr-steps">
						{ordered.map(({ step, index }, position) => (
							<StepCard
								key={index}
								step={step}
								index={index}
								position={position}
								status={
									dirty
										? undefined
										: overview.data.steps.find(
												(row) => row.index === index
											)
								}
								templates={options.data.templates}
								policy={policy}
								couponAuto={couponAuto}
								onChange={patchStep}
								onRemove={removeStep}
								removable={current.length > 1}
							/>
						))}
					</ul>

					{/* The button used to be unmounted at the cap, so the only way
					    to learn there was one was to notice it had gone. */}
					{current.length < MAX_STEPS ? (
						<button
							type="button"
							className="cr-btn is-ghost"
							onClick={addStep}
						>
							{__('Add a step', 'cart-rebound')}
						</button>
					) : (
						<p className="cr-field__hint">
							{sprintf(
								/* translators: %d: the maximum number of steps. */
								__(
									'A sequence holds at most %d steps. Remove one to add another.',
									'cart-rebound'
								),
								MAX_STEPS
							)}
						</p>
					)}
				</div>

				<SaveBar
					label={__('Save sequence', 'cart-rebound')}
					savedLabel={__('Sequence saved.', 'cart-rebound')}
					errorFallback={__(
						'The sequence could not be saved. Please try again.',
						'cart-rebound'
					)}
					isPending={save.isPending}
					disabled={duplicateDelay !== null}
					isSuccess={save.isSuccess}
					isError={save.isError}
					error={save.error}
					onReset={save.reset}
				/>
			</form>
		</ProSurface>
	);
};
