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
import { __, sprintf } from '@wordpress/i18n';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ProSurface } from '../components/ProSurface';
import { useProQuery } from '../hooks/useAddons';
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
import { formatCount, formatDelay } from '../lib/format';
import type { SequenceStep, SequenceStepStatus } from '../types/api';

const MAX_STEPS = 20;

const MINUTES_PER_HOUR = 60;
const MINUTES_PER_DAY = 1440;

type DelayUnit = 'minutes' | 'hours' | 'days';

/**
 * Split a stored delay into the largest unit that divides it cleanly.
 *
 * Delays are stored in minutes because that is what the scheduler needs, but
 * nobody types 4320 meaning "three days" without doing arithmetic first — and
 * arithmetic in a form field is where the typo that mails everyone at the wrong
 * hour comes from. Only exact divisions are promoted, so 90 minutes stays 90
 * minutes rather than becoming a misleading "1 hour".
 * @param minutes The stored delay.
 */
const splitDelay = (minutes: number): { value: number; unit: DelayUnit } => {
	const safe = Math.max(1, Math.round(minutes));

	if (safe % MINUTES_PER_DAY === 0) {
		return { value: safe / MINUTES_PER_DAY, unit: 'days' };
	}

	if (safe % MINUTES_PER_HOUR === 0) {
		return { value: safe / MINUTES_PER_HOUR, unit: 'hours' };
	}

	return { value: safe, unit: 'minutes' };
};

/**
 * Put a value and unit back together as minutes.
 * @param value The entered number.
 * @param unit  The chosen unit.
 */
const toMinutes = (value: number, unit: DelayUnit): number => {
	const safe = Math.max(1, Math.round(value));

	if (unit === 'days') {
		return safe * MINUTES_PER_DAY;
	}

	if (unit === 'hours') {
		return safe * MINUTES_PER_HOUR;
	}

	return safe;
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
	status,
	templates,
	onChange,
	onRemove,
	removable,
}: {
	step: SequenceStep;
	index: number;
	/** Absent until the step has been saved and the add-on has seen it. */
	status: SequenceStepStatus | undefined;
	templates: Array<{ id: string; name: string }>;
	onChange: (index: number, patch: Partial<SequenceStep>) => void;
	onRemove: (index: number) => void;
	removable: boolean;
}) => {
	const id = `cr-step-${index}`;
	const delay = splitDelay(step.delay_minutes);

	return (
		<li className={step.enabled ? 'cr-step' : 'cr-step is-off'}>
			<div className="cr-step__head">
				<span className="cr-step__number">{index + 1}</span>

				<div className="cr-step__heading">
					<h3 className="cr-step__title">
						{formatDelay(step.delay_minutes)}
					</h3>
					{status && (
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

				<span className="cr-switch">
					<input
						id={`${id}-enabled`}
						type="checkbox"
						checked={step.enabled}
						aria-label={__('Enable this step', 'cart-rebound')}
						onChange={(event: ChangeEvent<HTMLInputElement>) => {
							onChange(index, { enabled: event.target.checked });
						}}
					/>
					<span className="cr-switch__track">
						<span className="cr-switch__thumb" />
					</span>
				</span>
			</div>

			<div className="cr-step__grid">
				<div className="cr-field">
					<label htmlFor={`${id}-delay`} className="cr-field__label">
						{__('Send after', 'cart-rebound')}
					</label>
					<div className="cr-duration">
						<input
							id={`${id}-delay`}
							className="cr-input"
							type="number"
							min={1}
							value={delay.value}
							onChange={(
								event: ChangeEvent<HTMLInputElement>
							) => {
								const parsed = Number.parseInt(
									event.target.value,
									10
								);

								onChange(index, {
									delay_minutes: toMinutes(
										Number.isNaN(parsed) ? 1 : parsed,
										delay.unit
									),
								});
							}}
						/>
						<select
							className="cr-input"
							value={delay.unit}
							aria-label={__('Delay unit', 'cart-rebound')}
							onChange={(
								event: ChangeEvent<HTMLSelectElement>
							) => {
								onChange(index, {
									delay_minutes: toMinutes(
										delay.value,
										event.target.value as DelayUnit
									),
								});
							}}
						>
							<option value="minutes">
								{__('minutes', 'cart-rebound')}
							</option>
							<option value="hours">
								{__('hours', 'cart-rebound')}
							</option>
							<option value="days">
								{__('days', 'cart-rebound')}
							</option>
						</select>
					</div>
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
					<select
						id={`${id}-template`}
						className="cr-input"
						value={step.template_id}
						onChange={(event: ChangeEvent<HTMLSelectElement>) => {
							onChange(index, {
								template_id: event.target.value,
							});
						}}
					>
						<option value="">
							{__('Default template', 'cart-rebound')}
						</option>
						{templates.map((template) => (
							<option key={template.id} value={template.id}>
								{template.name}
							</option>
						))}
					</select>
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
				<label className="cr-check" htmlFor={`${id}-coupon`}>
					<input
						id={`${id}-coupon`}
						type="checkbox"
						checked={step.coupon}
						onChange={(event: ChangeEvent<HTMLInputElement>) => {
							onChange(index, { coupon: event.target.checked });
						}}
					/>
					<span>{__('Include a unique coupon', 'cart-rebound')}</span>
				</label>

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
		{ settings: sampleProSettings(), features: [] }
	);
	const overview = useProQuery(
		'sequence',
		['pro', 'sequence'],
		fetchSequence,
		sampleSequence()
	);
	const options = useProQuery(
		'sequence',
		['pro', 'options'],
		fetchProOptions,
		sampleProOptions(),
		{ staleTime: 60_000 }
	);

	const [steps, setSteps] = useState<SequenceStep[] | null>(null);

	useEffect(() => {
		setSteps(settings.data.settings.sequence_steps);
	}, [settings.data]);

	const save = useMutation({
		mutationFn: updateProSettings,
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: ['pro'] });
		},
	});

	const current = steps ?? settings.data.settings.sequence_steps;

	const patchStep = (index: number, patch: Partial<SequenceStep>) => {
		setSteps((previous) =>
			(previous ?? current).map((step, position) =>
				position === index ? { ...step, ...patch } : step
			)
		);
	};

	const removeStep = (index: number) => {
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

	const onSubmit = (event: FormEvent<HTMLFormElement>) => {
		event.preventDefault();
		save.mutate({ sequence_steps: current });
	};

	// Ordering the editor by delay matches what the runner does with the plan,
	// so the list on screen is the order the shopper experiences.
	const ordered = [...current]
		.map((step, index) => ({ step, index }))
		.sort(
			(left, right) => left.step.delay_minutes - right.step.delay_minutes
		);

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

					<ul className="cr-steps">
						{ordered.map(({ step, index }) => (
							<StepCard
								key={index}
								step={step}
								index={index}
								status={overview.data.steps.find(
									(row) => row.index === index
								)}
								templates={options.data.templates}
								onChange={patchStep}
								onRemove={removeStep}
								removable={current.length > 1}
							/>
						))}
					</ul>

					{current.length < MAX_STEPS && (
						<button
							type="button"
							className="cr-btn is-ghost"
							onClick={addStep}
						>
							{__('Add a step', 'cart-rebound')}
						</button>
					)}
				</div>

				<div className="cr-savebar">
					<button
						type="submit"
						className="cr-btn is-primary"
						disabled={save.isPending}
					>
						{save.isPending
							? __('Saving…', 'cart-rebound')
							: __('Save sequence', 'cart-rebound')}
					</button>
					{save.isSuccess && (
						<span className="cr-saved">
							{__('Sequence saved.', 'cart-rebound')}
						</span>
					)}
				</div>
			</form>
		</ProSurface>
	);
};
