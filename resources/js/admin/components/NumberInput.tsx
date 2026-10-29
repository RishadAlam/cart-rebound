import { useState, type InputHTMLAttributes } from 'react';

// Holds the raw text while typing so clearing the field doesn't snap it to the
// minimum before the next digit is typed.
export const NumberInput = ({
	value,
	onChange,
	min = 1,
	onBlur,
	...rest
}: {
	value: number;
	onChange: (value: number) => void;
	min?: number;
} & Omit<
	InputHTMLAttributes<HTMLInputElement>,
	'value' | 'onChange' | 'min' | 'type'
>) => {
	const [draft, setDraft] = useState<string | null>(null);

	return (
		<input
			{...rest}
			type="number"
			min={min}
			value={draft ?? String(value)}
			onChange={(event) => {
				const raw = event.target.value;
				const parsed = Number.parseInt(raw, 10);

				setDraft(raw);

				if (!Number.isNaN(parsed)) {
					onChange(Math.max(min, parsed));
				}
			}}
			onBlur={(event) => {
				setDraft(null);
				onBlur?.(event);
			}}
		/>
	);
};
