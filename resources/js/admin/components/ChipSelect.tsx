// Selection order is display order. Reordering is remove + re-add rather than drag,
// so keyboard users get the same path and a two-item swap stays quick.
import { __, sprintf } from '@wordpress/i18n';
import { Combobox, type ComboOption } from './Combobox';

export const ChipSelect = ({
	options,
	value,
	onChange,
	ariaLabel,
	addLabel = __('Add…', 'cart-rebound'),
	emptyLabel = __('Nothing selected yet.', 'cart-rebound'),
	disabled = false,
}: {
	options: ComboOption[];
	value: string[];
	onChange: (next: string[]) => void;
	ariaLabel: string;
	addLabel?: string;
	emptyLabel?: string;
	disabled?: boolean;
}) => {
	const labelOf = (item: string): string =>
		options.find((option) => option.value === item)?.label ?? item;

	const remaining = options.filter((option) => !value.includes(option.value));

	return (
		<div className="cr-chips" role="group" aria-label={ariaLabel}>
			{value.length === 0 && (
				<p className="cr-chips__empty">{emptyLabel}</p>
			)}

			<ol className="cr-chips__list">
				{value.map((item) => (
					<li key={item} className="cr-chips__item">
						<span className="cr-chips__item-label">
							{labelOf(item)}
						</span>
						<button
							type="button"
							className="cr-chips__item-remove"
							disabled={disabled}
							aria-label={sprintf(
								/* translators: %s: name of the selected item. */
								__('Remove %s', 'cart-rebound'),
								labelOf(item)
							)}
							onClick={() => {
								onChange(value.filter((one) => one !== item));
							}}
						>
							<svg
								viewBox="0 0 10 10"
								width="9"
								height="9"
								aria-hidden="true"
							>
								<path
									d="m1.5 1.5 7 7m0-7-7 7"
									stroke="currentColor"
									strokeWidth="1.6"
									strokeLinecap="round"
								/>
							</svg>
						</button>
					</li>
				))}
			</ol>

			{remaining.length > 0 && (
				<Combobox
					compact
					ariaLabel={addLabel}
					placeholder={addLabel}
					value=""
					disabled={disabled}
					options={remaining}
					onChange={(next) => {
						if (next !== '') {
							onChange([...value, next]);
						}
					}}
				/>
			)}
		</div>
	);
};
