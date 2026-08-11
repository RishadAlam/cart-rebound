/**
 * A delay, entered as a number and a unit.
 *
 * Every delay in the plugin is stored in minutes, because that is what the
 * scheduler needs. Nobody types 4320 meaning "three days" without doing
 * arithmetic first, and arithmetic in a form field is where the typo that mails
 * everyone at the wrong hour comes from.
 *
 * A stored value is shown in the largest unit that divides it exactly, so 1440
 * reads as "1 day" while 90 stays "90 minutes" rather than becoming a
 * misleading "1 hour".
 */
import { useState, type ChangeEvent } from 'react';
import { __ } from '@wordpress/i18n';
import { Combobox } from './Combobox';

const MINUTES_PER_HOUR = 60;
const MINUTES_PER_DAY = 1440;

export type DurationUnit = 'minutes' | 'hours' | 'days';

/**
 * Split a stored delay into the largest unit that divides it cleanly.
 * @param minutes The stored delay.
 */
export const splitDuration = (
	minutes: number
): { value: number; unit: DurationUnit } => {
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
export const toMinutes = (value: number, unit: DurationUnit): number => {
	const safe = Math.max(1, Math.round(value));

	if (unit === 'days') {
		return safe * MINUTES_PER_DAY;
	}

	if (unit === 'hours') {
		return safe * MINUTES_PER_HOUR;
	}

	return safe;
};

interface Props {
	id: string;
	/** The stored delay, in minutes. */
	minutes: number;
	/** Receives the new delay, in minutes. */
	onChange: (minutes: number) => void;
	/** Names the unit select for assistive tech. */
	unitLabel?: string;
	/** Field injects this so the hint below is announced with the control. */
	'aria-describedby'?: string;
}

export const DurationField = ({
	id,
	minutes,
	onChange,
	unitLabel,
	'aria-describedby': describedBy,
}: Props) => {
	const { value, unit } = splitDuration(minutes);

	/*
	 * The box has to be allowed to be empty for a moment.
	 *
	 * Coercing every keystroke to a valid number meant the field could never be
	 * cleared: backspacing "30" put a "1" in the box as the last digit went, and
	 * a number input gives no caret control, so the merchant ended up typing
	 * around a digit they never asked for. The draft holds whatever is being
	 * typed; the committed value is only touched when it parses.
	 */
	const [draft, setDraft] = useState<string | null>(null);

	return (
		<div className="cr-duration">
			<input
				id={id}
				className="cr-input"
				type="number"
				min={1}
				{...(describedBy === undefined
					? {}
					: { 'aria-describedby': describedBy })}
				value={draft ?? value}
				onChange={(event: ChangeEvent<HTMLInputElement>) => {
					const raw = event.target.value;

					setDraft(raw);

					const parsed = Number.parseInt(raw, 10);

					if (!Number.isNaN(parsed) && parsed >= 1) {
						onChange(toMinutes(parsed, unit));
					}
				}}
				onBlur={() => {
					setDraft(null);
				}}
			/>
			<Combobox
				compact
				ariaLabel={unitLabel ?? __('Unit', 'cart-rebound')}
				value={unit}
				options={[
					{ value: 'minutes', label: __('minutes', 'cart-rebound') },
					{ value: 'hours', label: __('hours', 'cart-rebound') },
					{ value: 'days', label: __('days', 'cart-rebound') },
				]}
				onChange={(next) => {
					onChange(toMinutes(value, next as DurationUnit));
				}}
			/>
		</div>
	);
};
