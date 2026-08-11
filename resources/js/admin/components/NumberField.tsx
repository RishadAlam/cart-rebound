/**
 * A number that can be half-typed.
 *
 * Every numeric setting on this admin coerced each keystroke straight into a
 * valid number, so the box could never be empty: backspacing "30" to clear it
 * put a "1" there the moment the last digit went, and because `type="number"`
 * gives no caret control the merchant then typed around a digit they had not
 * asked for. Clearing a field and retyping it is the ordinary way people change
 * a number, and it did not work anywhere.
 *
 * The draft holds whatever is in the box while it is being edited. The committed
 * value only moves when what is typed parses, and the field settles back to the
 * committed value on blur — so an abandoned edit restores rather than persisting
 * a "1" nobody chose.
 */
import { useState, type ChangeEvent } from 'react';

interface Props {
	id: string;
	value: number;
	onChange: (value: number) => void;
	/** Smallest value the setting accepts. */
	min?: number;
	/** Step for the browser's own controls; `0.01` for money. */
	step?: string;
	/** Parse as a decimal rather than a whole number. */
	decimal?: boolean;
	/** Marks the input as describing an invalid value. */
	invalid?: boolean;
	/*
	 * Field injects this so the hint below the control is announced with it.
	 * A component swallows props it does not forward, which is why every wrapped
	 * field on the admin stayed undescribed while a bare <input> did not.
	 */
	'aria-describedby'?: string;
}

export const NumberField = ({
	id,
	value,
	onChange,
	min = 0,
	step,
	decimal = false,
	invalid = false,
	'aria-describedby': describedBy,
}: Props) => {
	const [draft, setDraft] = useState<string | null>(null);

	return (
		<input
			id={id}
			className="cr-input"
			type="number"
			min={min}
			{...(step === undefined ? {} : { step })}
			{...(invalid ? { 'aria-invalid': true } : {})}
			{...(describedBy === undefined
				? {}
				: { 'aria-describedby': describedBy })}
			value={draft ?? value}
			onChange={(event: ChangeEvent<HTMLInputElement>) => {
				const raw = event.target.value;

				setDraft(raw);

				const parsed = decimal
					? Number.parseFloat(raw)
					: Number.parseInt(raw, 10);

				if (!Number.isNaN(parsed) && parsed >= min) {
					onChange(parsed);
				}
			}}
			onBlur={() => {
				setDraft(null);
			}}
		/>
	);
};
