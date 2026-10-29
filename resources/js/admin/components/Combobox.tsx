// The popover is `position: fixed` from the trigger's rect so it escapes overflow-clipping
// ancestors (e.g. the carts table) and still renders above a <dialog> backdrop.
import {
	useCallback,
	useEffect,
	useId,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
	type FocusEvent,
	type KeyboardEvent,
} from 'react';
import { __ } from '@wordpress/i18n';

export interface ComboOption {
	value: string;
	label: string;
}

interface Position {
	left: number;
	width: number;
	top: number;
	bottom: number;
	openUp: boolean;
	maxHeight: number;
}

const SEARCH_THRESHOLD = 7;
const MIN_POP_WIDTH = 200;
const VIEWPORT_GUTTER = 8;

export const Combobox = ({
	id,
	options,
	value,
	onChange,
	placeholder = __('Select…', 'cart-rebound'),
	ariaLabel,
	disabled = false,
	compact = false,
	searchable,
	tone,
	pill = false,
}: {
	id?: string;
	options: ComboOption[];
	value: string;
	onChange: (value: string) => void;
	placeholder?: string;
	ariaLabel?: string;
	disabled?: boolean;
	compact?: boolean;
	searchable?: boolean;
	tone?: string;
	pill?: boolean;
}) => {
	const [open, setOpen] = useState(false);
	const [query, setQuery] = useState('');
	const [activeIndex, setActiveIndex] = useState(0);
	const [pos, setPos] = useState<Position>({
		left: 0,
		width: 0,
		top: 0,
		bottom: 0,
		openUp: false,
		maxHeight: 280,
	});

	const baseId = useId();
	const listId = `${baseId}-list`;
	const optionId = (index: number) => `${baseId}-opt-${index}`;
	const rootRef = useRef<HTMLDivElement>(null);
	const triggerRef = useRef<HTMLButtonElement>(null);
	const popRef = useRef<HTMLDivElement>(null);
	const inputRef = useRef<HTMLInputElement>(null);

	const canSearch = searchable ?? options.length > SEARCH_THRESHOLD;
	const selected = options.find((option) => option.value === value);

	const filtered = useMemo(() => {
		const term = query.trim().toLowerCase();

		if (!canSearch || term === '') {
			return options;
		}

		return options.filter(
			(option) =>
				option.label.toLowerCase().includes(term) ||
				option.value.toLowerCase().includes(term)
		);
	}, [options, query, canSearch]);

	const reposition = useCallback(() => {
		const el = triggerRef.current;

		if (!el) {
			return;
		}

		const rect = el.getBoundingClientRect();
		const margin = 6;
		const below = window.innerHeight - rect.bottom - margin;
		const above = rect.top - margin;
		const openUp = below < 220 && above > below;
		const popWidth = Math.max(rect.width, MIN_POP_WIDTH);
		const maxLeft =
			document.documentElement.clientWidth - popWidth - VIEWPORT_GUTTER;

		setPos({
			left: Math.max(VIEWPORT_GUTTER, Math.min(rect.left, maxLeft)),
			width: rect.width,
			top: rect.bottom + margin,
			bottom: window.innerHeight - rect.top + margin,
			openUp,
			maxHeight: Math.max(150, Math.min(300, openUp ? above : below)),
		});
	}, []);

	useLayoutEffect(() => {
		if (open) {
			reposition();
		}
	}, [open, reposition]);

	useEffect(() => {
		if (!open) {
			return;
		}

		const onScrollOrResize = () => reposition();
		const onDocMouseDown = (event: globalThis.MouseEvent) => {
			const target = event.target as Node;

			if (
				!rootRef.current?.contains(target) &&
				!popRef.current?.contains(target)
			) {
				setOpen(false);
			}
		};

		window.addEventListener('scroll', onScrollOrResize, true);
		window.addEventListener('resize', onScrollOrResize);
		document.addEventListener('mousedown', onDocMouseDown);

		if (canSearch) {
			inputRef.current?.focus();
		}

		return () => {
			window.removeEventListener('scroll', onScrollOrResize, true);
			window.removeEventListener('resize', onScrollOrResize);
			document.removeEventListener('mousedown', onDocMouseDown);
		};
	}, [open, canSearch, reposition]);

	const openMenu = () => {
		if (disabled) {
			return;
		}

		setQuery('');
		setActiveIndex(
			Math.max(
				0,
				options.findIndex((option) => option.value === value)
			)
		);
		setOpen(true);
	};

	const choose = (next: string) => {
		onChange(next);
		setOpen(false);
		setQuery('');
		triggerRef.current?.focus();
	};

	const onKeyDown = (event: KeyboardEvent) => {
		if (event.key === 'ArrowDown') {
			event.preventDefault();

			if (!open) {
				openMenu();

				return;
			}

			setActiveIndex((index) => Math.min(filtered.length - 1, index + 1));
		} else if (event.key === 'ArrowUp') {
			event.preventDefault();
			setActiveIndex((index) => Math.max(0, index - 1));
		} else if (event.key === 'Enter' && open) {
			event.preventDefault();
			const option = filtered[activeIndex];

			if (option) {
				choose(option.value);
			}
		} else if (event.key === 'Escape' && open) {
			event.preventDefault();
			setOpen(false);
			triggerRef.current?.focus();
		}
	};

	// Tabbing away leaves the popover floating over unrelated content; a null
	// relatedTarget (click on blank space) is left to the mousedown handler.
	const onBlur = (event: FocusEvent<HTMLDivElement>) => {
		const next = event.relatedTarget;

		if (open && next && !rootRef.current?.contains(next)) {
			setOpen(false);
		}
	};

	const activeId =
		open && filtered[activeIndex] ? optionId(activeIndex) : undefined;

	return (
		<div
			className={`cr-combo${compact ? ' is-compact' : ''}`}
			ref={rootRef}
			onBlur={onBlur}
		>
			<button
				ref={triggerRef}
				id={id}
				type="button"
				className={`cr-combo__trigger${pill ? ' is-pill' : ''}${
					tone ? ` cr-combo__trigger--${tone}` : ''
				}`}
				disabled={disabled}
				role="combobox"
				aria-haspopup="listbox"
				aria-expanded={open}
				aria-controls={open ? listId : undefined}
				aria-activedescendant={canSearch ? undefined : activeId}
				aria-label={ariaLabel}
				onClick={() => {
					if (open) {
						setOpen(false);
					} else {
						openMenu();
					}
				}}
				onKeyDown={onKeyDown}
			>
				<span
					className={`cr-combo__value${selected ? '' : ' is-placeholder'}`}
				>
					{selected ? selected.label : placeholder}
				</span>
				<svg
					className="cr-combo__chevron"
					viewBox="0 0 12 12"
					width="12"
					height="12"
					fill="none"
					aria-hidden="true"
				>
					<path
						d="m3 4.5 3 3 3-3"
						stroke="currentColor"
						strokeWidth="1.5"
						strokeLinecap="round"
						strokeLinejoin="round"
					/>
				</svg>
			</button>

			{open && (
				<div
					ref={popRef}
					className="cr-combo__pop"
					style={{
						left: pos.left,
						width: Math.max(pos.width, MIN_POP_WIDTH),
						maxHeight: pos.maxHeight,
						...(pos.openUp
							? { bottom: pos.bottom }
							: { top: pos.top }),
					}}
				>
					{canSearch && (
						<input
							ref={inputRef}
							className="cr-combo__search"
							type="text"
							value={query}
							placeholder={__('Search…', 'cart-rebound')}
							aria-label={__('Search options', 'cart-rebound')}
							aria-controls={listId}
							aria-activedescendant={activeId}
							onChange={(event) => {
								setQuery(event.target.value);
								setActiveIndex(0);
							}}
							onKeyDown={onKeyDown}
						/>
					)}

					<div
						className="cr-combo__list"
						id={listId}
						role="listbox"
						aria-label={ariaLabel}
					>
						{filtered.length === 0 ? (
							<div className="cr-combo__empty">
								{__('No matches', 'cart-rebound')}
							</div>
						) : (
							filtered.map((option, index) => (
								<button
									key={option.value}
									id={optionId(index)}
									type="button"
									role="option"
									tabIndex={-1}
									aria-selected={option.value === value}
									className={`cr-combo__option${
										index === activeIndex
											? ' is-active'
											: ''
									}${option.value === value ? ' is-selected' : ''}`}
									onMouseEnter={() => {
										setActiveIndex(index);
									}}
									onClick={() => {
										choose(option.value);
									}}
								>
									{option.label}
								</button>
							))
						)}
					</div>
				</div>
			)}
		</div>
	);
};
