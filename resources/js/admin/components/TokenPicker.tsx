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
 *
 * Three things this component is careful about, each of which was wrong before:
 *
 * 1. A selection whose option is missing is still shown. Chips used to be built
 *    by intersecting the saved values with the fetched list, so a deleted
 *    category or a role from a deactivated plugin vanished from the screen while
 *    still being posted on every save and still excluding carts. Invisible rules
 *    are the ones nobody can debug.
 * 2. The chip is not the delete button. Its whole surface used to remove the
 *    exclusion on one tap, with the only warning being a hover colour that a
 *    touch screen never shows.
 * 3. The list is operable from the keyboard, with the same model as Combobox:
 *    one tab stop, arrows to move, Enter to choose, Escape to close.
 */
import {
	useMemo,
	useRef,
	useState,
	type ChangeEvent,
	type KeyboardEvent,
} from 'react';
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
	/**
	 * Names the filter box for assistive tech.
	 *
	 * The visible heading above this component is a plain span — it cannot be a
	 * `<label>` because the control it would point at does not exist until the
	 * picker is opened. So the field carries its own name.
	 */
	searchLabel: string;
	/** True when the option list could not be loaded, so nothing may be added. */
	disabled?: boolean;
}

export const TokenPicker = ({
	id,
	options,
	selected,
	onChange,
	placeholder,
	emptyLabel,
	searchLabel,
	disabled = false,
}: Props) => {
	const [open, setOpen] = useState(false);
	const [query, setQuery] = useState('');
	const [activeIndex, setActiveIndex] = useState(0);
	const [announcement, setAnnouncement] = useState('');
	const searchRef = useRef<HTMLInputElement>(null);
	const addRef = useRef<HTMLButtonElement>(null);
	const rootRef = useRef<HTMLDivElement>(null);

	/*
	 * Every saved value gets a chip, whether or not the options list still knows
	 * about it. One that no longer resolves says so, so it can be recognised and
	 * removed rather than quietly filtering carts for ever.
	 */
	const chosen = useMemo(
		() =>
			selected.map((value) => {
				const option = options.find((item) => item.value === value);

				return {
					value,
					label: option ? option.label : value,
					missing: option === undefined,
				};
			}),
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

	const add = (value: string, label: string) => {
		onChange([...selected, value]);
		setQuery('');
		setActiveIndex(0);
		setAnnouncement(
			sprintf(
				/* translators: %s: the option that was excluded. */
				__('%s excluded.', 'cart-rebound'),
				label
			)
		);
		searchRef.current?.focus();
	};

	/*
	 * Removing a chip destroys the button that was focused, which drops focus to
	 * the document body and leaves a keyboard user nowhere. Focus moves to the
	 * remaining chip in that position, or to the add button when the last one
	 * goes.
	 */
	const remove = (value: string, label: string) => {
		const position = chosen.findIndex((option) => option.value === value);

		onChange(selected.filter((item) => item !== value));
		setAnnouncement(
			sprintf(
				/* translators: %s: the option that is no longer excluded. */
				__('%s is no longer excluded.', 'cart-rebound'),
				label
			)
		);

		window.setTimeout(() => {
			const chips =
				rootRef.current?.querySelectorAll<HTMLButtonElement>(
					'.cr-chip__remove'
				);
			const next =
				chips && chips.length > 0
					? chips[Math.min(position, chips.length - 1)]
					: undefined;

			(next ?? addRef.current)?.focus();
		}, 0);
	};

	const close = () => {
		setOpen(false);
		setQuery('');
		setActiveIndex(0);
		addRef.current?.focus();
	};

	const onKeyDown = (event: KeyboardEvent) => {
		if (event.key === 'ArrowDown') {
			event.preventDefault();
			setActiveIndex((index) =>
				Math.min(available.length - 1, index + 1)
			);
		} else if (event.key === 'ArrowUp') {
			event.preventDefault();
			setActiveIndex((index) => Math.max(0, index - 1));
		} else if (event.key === 'Enter') {
			event.preventDefault();
			const option = available[activeIndex];

			if (option) {
				add(option.value, option.label);
			}
		} else if (event.key === 'Escape') {
			event.preventDefault();
			close();
		}
	};

	return (
		<div className="cr-tokens-field" ref={rootRef}>
			{/* Additions and removals are silent on screen for anyone not
			    watching the chips, so they are announced. */}
			<span className="screen-reader-text" role="status">
				{announcement}
			</span>

			{chosen.length === 0 ? (
				<p className="cr-tokens-field__empty">{emptyLabel}</p>
			) : (
				<ul className="cr-chips">
					{chosen.map((option) => (
						<li key={option.value}>
							<span
								className={
									option.missing
										? 'cr-chip is-removable is-missing'
										: 'cr-chip is-removable'
								}
							>
								{option.missing
									? sprintf(
											/* translators: %s: the stored value that no longer resolves. */
											__(
												'%s — no longer available',
												'cart-rebound'
											),
											option.value
										)
									: option.label}
								<button
									type="button"
									className="cr-chip__remove"
									onClick={() => {
										remove(option.value, option.label);
									}}
									aria-label={sprintf(
										/* translators: %s: the option being removed. */
										__('Remove %s', 'cart-rebound'),
										option.label
									)}
								>
									<span aria-hidden="true">×</span>
								</button>
							</span>
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
						aria-label={searchLabel}
						aria-controls={`${id}-list`}
						placeholder={
							placeholder ?? __('Type to filter…', 'cart-rebound')
						}
						onChange={(event: ChangeEvent<HTMLInputElement>) => {
							setQuery(event.target.value);
							setActiveIndex(0);
						}}
						onKeyDown={onKeyDown}
					/>

					<ul
						className="cr-tokens-field__list"
						id={`${id}-list`}
						role="listbox"
						aria-label={searchLabel}
					>
						{available.length === 0 && (
							<li className="cr-tokens-field__none">
								{__('Nothing left to add.', 'cart-rebound')}
							</li>
						)}

						{available.map((option, index) => (
							<li key={option.value} role="presentation">
								<button
									type="button"
									role="option"
									aria-selected={index === activeIndex}
									tabIndex={-1}
									className={
										index === activeIndex
											? 'cr-tokens-field__option is-active'
											: 'cr-tokens-field__option'
									}
									onMouseEnter={() => {
										setActiveIndex(index);
									}}
									onClick={() => {
										add(option.value, option.label);
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
						onClick={close}
					>
						{__('Done', 'cart-rebound')}
					</button>
				</div>
			) : (
				<button
					ref={addRef}
					type="button"
					className="cr-btn is-ghost is-sm"
					disabled={disabled}
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
