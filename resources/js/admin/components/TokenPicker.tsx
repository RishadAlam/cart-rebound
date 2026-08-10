/**
 * Choosing several things from a list that may be long.
 *
 * A checkbox per option is fine for three options and unusable for fifty. A
 * real WooCommerce store with a few plugins installed has dozens of roles, and
 * rendering them all flat turns a two-line setting into a wall you have to read
 * end to end before you can be sure nothing is ticked.
 *
 * So: what you have chosen is always visible as removable chips, and everything
 * else is behind a filter you type into. The common case — nothing selected, or
 * two things selected — takes two lines instead of forty.
 */
import { useMemo, useRef, useState, type ChangeEvent } from 'react';
import { __, sprintf, _n } from '@wordpress/i18n';

export interface TokenOption {
	value: string;
	label: string;
}

interface Props {
	id: string;
	options: TokenOption[];
	/** Currently chosen values. */
	selected: string[];
	onChange: (selected: string[]) => void;
	/** Shown in the filter box. */
	placeholder?: string;
	/** Shown when nothing is chosen. */
	emptyLabel: string;
}

export const TokenPicker = ({
	id,
	options,
	selected,
	onChange,
	placeholder,
	emptyLabel,
}: Props) => {
	const [open, setOpen] = useState(false);
	const [query, setQuery] = useState('');
	const searchRef = useRef<HTMLInputElement>(null);

	const chosen = useMemo(
		() => options.filter((option) => selected.includes(option.value)),
		[options, selected]
	);

	// Already-chosen options leave the list: they are shown as chips above it,
	// and offering them twice is what makes a picker feel like it lost track.
	const available = useMemo(() => {
		const needle = query.trim().toLowerCase();

		return options.filter(
			(option) =>
				!selected.includes(option.value) &&
				(needle === '' ||
					option.label.toLowerCase().includes(needle) ||
					option.value.toLowerCase().includes(needle))
		);
	}, [options, selected, query]);

	const add = (value: string) => {
		onChange([...selected, value]);
		setQuery('');
		searchRef.current?.focus();
	};

	const remove = (value: string) => {
		onChange(selected.filter((item) => item !== value));
	};

	return (
		<div className="cr-tokens-field">
			{chosen.length === 0 ? (
				<p className="cr-tokens-field__empty">{emptyLabel}</p>
			) : (
				<ul className="cr-chips">
					{chosen.map((option) => (
						<li key={option.value}>
							<button
								type="button"
								className="cr-chip is-removable"
								onClick={() => {
									remove(option.value);
								}}
								aria-label={sprintf(
									/* translators: %s: the option being removed. */
									__('Remove %s', 'cart-rebound'),
									option.label
								)}
							>
								{option.label}
								<span aria-hidden="true">×</span>
							</button>
						</li>
					))}
				</ul>
			)}

			{open ? (
				<div className="cr-tokens-field__picker">
					<input
						ref={searchRef}
						id={id}
						type="search"
						className="cr-input is-compact"
						value={query}
						placeholder={
							placeholder ?? __('Type to filter…', 'cart-rebound')
						}
						onChange={(event: ChangeEvent<HTMLInputElement>) => {
							setQuery(event.target.value);
						}}
					/>

					<ul className="cr-tokens-field__list">
						{available.length === 0 && (
							<li className="cr-tokens-field__none">
								{__('Nothing left to add.', 'cart-rebound')}
							</li>
						)}

						{available.map((option) => (
							<li key={option.value}>
								<button
									type="button"
									className="cr-tokens-field__option"
									onClick={() => {
										add(option.value);
									}}
								>
									{option.label}
								</button>
							</li>
						))}
					</ul>

					<button
						type="button"
						className="cr-linkbtn"
						onClick={() => {
							setOpen(false);
							setQuery('');
						}}
					>
						{__('Done', 'cart-rebound')}
					</button>
				</div>
			) : (
				<button
					type="button"
					className="cr-btn is-ghost is-sm"
					onClick={() => {
						setOpen(true);
						window.setTimeout(() => searchRef.current?.focus(), 0);
					}}
				>
					{sprintf(
						/* translators: %d: number of options still available. */
						_n(
							'Add an exclusion (%d available)',
							'Add an exclusion (%d available)',
							available.length,
							'cart-rebound'
						),
						available.length
					)}
				</button>
			)}
		</div>
	);
};
