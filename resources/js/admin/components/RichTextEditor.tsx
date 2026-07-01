/**
 * A focused rich-text editor for email bodies — dependency-free, built on a
 * contentEditable surface with a grouped formatting toolbar.
 *
 * It is uncontrolled: the initial HTML is written once on mount and edits are
 * emitted via onChange. Remount it (change its `key`) to load a different value,
 * which keeps the caret stable instead of fighting React re-renders.
 *
 * Selection handling: toolbar buttons preventDefault on mousedown so the editor
 * keeps focus and the live selection (execCommand applies to it directly — no
 * focus() call, which would collapse it). Controls that must steal focus (the
 * colour inputs, the merge-tag <select>) save the caret on blur and restore it
 * before acting. Colours use styleWithCSS so they emit inline styles that
 * survive wp_kses_post (which strips legacy <font> tags).
 */
import {
	useEffect,
	useRef,
	useState,
	type MouseEvent,
	type ReactNode,
} from 'react';

export interface MergeTag {
	label: string;
	value: string;
}

const IconUndo = () => (
	<svg
		viewBox="0 0 16 16"
		width="13"
		height="13"
		fill="none"
		aria-hidden="true"
	>
		<path
			d="M6 4.5 3 7.5l3 3M3.4 7.5H9a3.5 3.5 0 0 1 0 7H6.5"
			stroke="currentColor"
			strokeWidth="1.4"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const IconRedo = () => (
	<svg
		viewBox="0 0 16 16"
		width="13"
		height="13"
		fill="none"
		aria-hidden="true"
	>
		<path
			d="m10 4.5 3 3-3 3M12.6 7.5H7a3.5 3.5 0 0 0 0 7h2.5"
			stroke="currentColor"
			strokeWidth="1.4"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const alignPaths: Record<string, string> = {
	Left: 'M2.5 4h11M2.5 8h7M2.5 12h9.5',
	Center: 'M2.5 4h11M4.5 8h7M3.5 12h9',
	Right: 'M2.5 4h11M6.5 8h7M4 12h9.5',
};

const IconAlign = ({ dir }: { dir: 'Left' | 'Center' | 'Right' }) => (
	<svg
		viewBox="0 0 16 16"
		width="13"
		height="13"
		fill="none"
		aria-hidden="true"
	>
		<path
			d={alignPaths[dir]}
			stroke="currentColor"
			strokeWidth="1.4"
			strokeLinecap="round"
		/>
	</svg>
);

const INLINE = [
	{ command: 'bold', label: 'Bold', content: <strong>B</strong> },
	{ command: 'italic', label: 'Italic', content: <em>I</em> },
	{
		command: 'underline',
		label: 'Underline',
		content: <span style={{ textDecoration: 'underline' }}>U</span>,
	},
	{
		command: 'strikeThrough',
		label: 'Strikethrough',
		content: <span style={{ textDecoration: 'line-through' }}>S</span>,
	},
] as const;

const BLOCKS = [
	{ block: '<h2>', label: 'Heading', text: 'H1' },
	{ block: '<h3>', label: 'Subheading', text: 'H2' },
	{ block: '<p>', label: 'Paragraph', text: '¶' },
	{ block: '<blockquote>', label: 'Quote', text: '❝' },
] as const;

const ALIGN = ['Left', 'Center', 'Right'] as const;

// Commands whose on/off state is reflected in the toolbar.
const STATEFUL = [
	'bold',
	'italic',
	'underline',
	'strikeThrough',
	'insertUnorderedList',
	'insertOrderedList',
	'justifyLeft',
	'justifyCenter',
	'justifyRight',
];

export const RichTextEditor = ({
	value,
	onChange,
	tags = [],
	ariaLabel = 'Email body',
}: {
	value: string;
	onChange: (html: string) => void;
	tags?: MergeTag[];
	ariaLabel?: string;
}) => {
	const ref = useRef<HTMLDivElement>(null);
	const seeded = useRef(false);
	const savedRange = useRef<Range | null>(null);
	const [active, setActive] = useState<Record<string, boolean>>({});

	useEffect(() => {
		if (ref.current && !seeded.current) {
			ref.current.innerHTML = value;
			seeded.current = true;
		}
	}, [value]);

	const emit = () => {
		if (ref.current) {
			onChange(ref.current.innerHTML);
		}
	};

	const getSelection = (): Selection | null =>
		ref.current?.ownerDocument.defaultView?.getSelection() ?? null;

	const saveSelection = () => {
		const selection = getSelection();

		if (
			selection &&
			selection.rangeCount > 0 &&
			ref.current?.contains(selection.anchorNode)
		) {
			savedRange.current = selection.getRangeAt(0).cloneRange();
		}
	};

	const restoreSelection = () => {
		const selection = getSelection();

		if (selection && savedRange.current) {
			selection.removeAllRanges();
			selection.addRange(savedRange.current);
		}
	};

	// Reflect which formats apply at the caret so the toolbar can light up.
	const syncActive = () => {
		const doc = ref.current?.ownerDocument;

		if (!doc) {
			return;
		}

		const next: Record<string, boolean> = {};

		STATEFUL.forEach((command) => {
			try {
				next[command] = doc.queryCommandState(command);
			} catch {
				next[command] = false;
			}
		});

		setActive(next);
	};

	const refresh = () => {
		saveSelection();
		syncActive();
	};

	// execCommand is deprecated but remains the only broadly-supported,
	// dependency-free way to format a contentEditable. No focus() call: the
	// button's mousedown-preventDefault already kept focus + selection.
	const run = (command: string, argument?: string) => {
		document.execCommand(command, false, argument);
		emit();
		refresh();
	};

	// For controls that stole focus (colour inputs): re-focus, restore the caret,
	// then apply. `css` emits inline styles so colours survive wp_kses_post.
	const runRestored = (command: string, argument?: string, css = false) => {
		ref.current?.focus();
		restoreSelection();

		if (css) {
			document.execCommand('styleWithCSS', false, 'true');
		}

		document.execCommand(command, false, argument);

		if (css) {
			document.execCommand('styleWithCSS', false, 'false');
		}

		emit();
		refresh();
	};

	const insert = (text: string) => {
		ref.current?.focus();
		restoreSelection();
		document.execCommand('insertText', false, text);
		emit();
		refresh();
	};

	const addLink = () => {
		// eslint-disable-next-line no-alert
		const url = window.prompt('Link URL (https://…)');

		if (url) {
			run('createLink', url);
		}
	};

	const insertImage = (url: string, alt: string) => {
		if (url === '') {
			return;
		}

		ref.current?.focus();
		restoreSelection();

		const safeUrl = url.replace(/"/g, '&quot;');
		const safeAlt = alt.replace(/"/g, '&quot;');

		document.execCommand(
			'insertHTML',
			false,
			`<img src="${safeUrl}" alt="${safeAlt}" style="max-width:100%;height:auto;" />`
		);
		emit();
		refresh();
	};

	const addImage = () => {
		const media = window.wp?.media;

		if (!media) {
			// eslint-disable-next-line no-alert
			const url = window.prompt('Image URL (https://…)');

			if (url) {
				insertImage(url, '');
			}

			return;
		}

		saveSelection();

		const frame = media({
			title: 'Insert image',
			button: { text: 'Insert into email' },
			multiple: false,
			library: { type: 'image' },
		});

		frame.on('select', () => {
			const attachment = frame.state().get('selection').first().toJSON();

			insertImage(attachment.url ?? '', attachment.alt ?? '');
		});

		frame.open();
	};

	// Keep the editor's selection when a toolbar button is pressed.
	const keepSelection = (event: MouseEvent) => {
		event.preventDefault();
	};

	const button = (
		key: string,
		label: string,
		onClick: () => void,
		content: ReactNode,
		stateKey?: string
	) => (
		<button
			key={key}
			type="button"
			className={`cr-rte__btn${
				stateKey && active[stateKey] ? ' is-active' : ''
			}`}
			title={label}
			aria-label={label}
			aria-pressed={stateKey ? Boolean(active[stateKey]) : undefined}
			onMouseDown={keepSelection}
			onClick={onClick}
		>
			{content}
		</button>
	);

	return (
		<div className="cr-rte">
			<div className="cr-rte__bar" role="toolbar" aria-label="Formatting">
				<div className="cr-rte__group">
					{button('undo', 'Undo', () => run('undo'), <IconUndo />)}
					{button('redo', 'Redo', () => run('redo'), <IconRedo />)}
				</div>

				<div className="cr-rte__group">
					{INLINE.map((tool) =>
						button(
							tool.command,
							tool.label,
							() => run(tool.command),
							tool.content,
							tool.command
						)
					)}
				</div>

				<div className="cr-rte__group">
					<input
						type="color"
						className="cr-rte__color"
						title="Text colour"
						aria-label="Text colour"
						defaultValue="#111827"
						onChange={(changeEvent) => {
							runRestored(
								'foreColor',
								changeEvent.target.value,
								true
							);
						}}
					/>
					<input
						type="color"
						className="cr-rte__color"
						title="Highlight colour"
						aria-label="Highlight colour"
						defaultValue="#fde68a"
						onChange={(changeEvent) => {
							runRestored(
								'hiliteColor',
								changeEvent.target.value,
								true
							);
						}}
					/>
				</div>

				<div className="cr-rte__group">
					{BLOCKS.map((item) =>
						button(
							item.block,
							item.label,
							() => run('formatBlock', item.block),
							item.text
						)
					)}
				</div>

				<div className="cr-rte__group">
					{ALIGN.map((dir) =>
						button(
							`align${dir}`,
							`Align ${dir.toLowerCase()}`,
							() => run(`justify${dir}`),
							<IconAlign dir={dir} />,
							`justify${dir}`
						)
					)}
				</div>

				<div className="cr-rte__group">
					{button(
						'ul',
						'Bulleted list',
						() => run('insertUnorderedList'),
						'• —',
						'insertUnorderedList'
					)}
					{button(
						'ol',
						'Numbered list',
						() => run('insertOrderedList'),
						'1.',
						'insertOrderedList'
					)}
					{button(
						'outdent',
						'Decrease indent',
						() => run('outdent'),
						'«'
					)}
					{button(
						'indent',
						'Increase indent',
						() => run('indent'),
						'»'
					)}
				</div>

				<div className="cr-rte__group">
					{button('link', 'Insert link', addLink, 'Link')}
					{button(
						'unlink',
						'Remove link',
						() => run('unlink'),
						'Unlink'
					)}
					{button('image', 'Insert image', addImage, 'Image')}
					{button(
						'hr',
						'Divider',
						() => run('insertHorizontalRule'),
						'―'
					)}
				</div>

				<div className="cr-rte__group">
					{button(
						'clear',
						'Clear formatting',
						() => run('removeFormat'),
						'Clear'
					)}
				</div>

				{tags.length > 0 && (
					<>
						<span className="cr-rte__spacer" />
						<select
							className="cr-select is-compact"
							aria-label="Insert merge tag"
							value=""
							onChange={(changeEvent) => {
								if (changeEvent.target.value !== '') {
									insert(changeEvent.target.value);
								}
							}}
						>
							<option value="">Insert tag…</option>
							{tags.map((tag) => (
								<option key={tag.value} value={tag.value}>
									{tag.label}
								</option>
							))}
						</select>
					</>
				)}
			</div>

			<div className="cr-rte__canvas">
				<div
					ref={ref}
					className="cr-rte__content"
					contentEditable
					suppressContentEditableWarning
					role="textbox"
					tabIndex={0}
					aria-multiline="true"
					aria-label={ariaLabel}
					onInput={emit}
					onKeyUp={refresh}
					onMouseUp={refresh}
					onFocus={syncActive}
					onBlur={() => {
						saveSelection();
						emit();
					}}
				/>
			</div>
		</div>
	);
};
