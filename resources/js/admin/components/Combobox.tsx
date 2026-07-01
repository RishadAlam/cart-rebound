/**
 * Accessible, searchable select — a modern replacement for a native <select>.
 *
 * The popover is `position: fixed` and positioned from the trigger's rect, so it
 * escapes overflow-clipping ancestors (e.g. the scrollable carts table) and
 * still renders above a <dialog> backdrop (it stays a descendant of the dialog).
 * Search is auto-enabled once the list is long enough; keyboard navigation,
 * type-to-filter, click-outside and Escape are all handled.
 */
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
	type KeyboardEvent,
} from 'react';

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

export const Combobox = ({
	options,
	value,
	onChange,
	placeholder = 'Select…',
	ariaLabel,
	disabled = false,
	compact = false,
	searchable,
}: {
	options: ComboOption[];
	value: string;
	onChange: (value: string) => void;
	placeholder?: string;
	ariaLabel?: string;
	disabled?: boolean;
	compact?: boolean;
	searchable?: boolean;
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

		return options.filter((option) =>
			option.label.toLowerCase().includes(term)
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

		setPos({
			left: rect.left,
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

	return (
		<div
			className={`cr-combo${compact ? ' is-compact' : ''}`}
			ref={rootRef}
		>
			<button
				ref={triggerRef}
				type="button"
				className="cr-combo__trigger"
				disabled={disabled}
				aria-haspopup="listbox"
				aria-expanded={open}
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
					role="listbox"
					aria-label={ariaLabel}
					style={{
						left: pos.left,
						width: Math.max(pos.width, 200),
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
							placeholder="Search…"
							aria-label="Search options"
							onChange={(event) => {
								setQuery(event.target.value);
								setActiveIndex(0);
							}}
							onKeyDown={onKeyDown}
						/>
					)}

					<div className="cr-combo__list">
						{filtered.length === 0 ? (
							<div className="cr-combo__empty">No matches</div>
						) : (
							filtered.map((option, index) => (
								<button
									key={option.value}
									type="button"
									role="option"
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
