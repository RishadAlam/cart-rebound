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
 * colour inputs, the merge-tag combobox) save the caret on blur and restore it
 * before acting. Colours use styleWithCSS so they emit inline styles that
 * survive wp_kses_post (which strips legacy <font> tags).
 */
import {
	useEffect,
	useId,
	useRef,
	useState,
	type MouseEvent,
	type ReactNode,
} from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Combobox } from './Combobox';
import { formatHtml, wrapLooseNodes } from '../lib/html';

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
	{
		command: 'bold',
		label: __('Bold', 'cart-rebound'),
		content: <strong>B</strong>,
	},
	{
		command: 'italic',
		label: __('Italic', 'cart-rebound'),
		content: <em>I</em>,
	},
	{
		command: 'underline',
		label: __('Underline', 'cart-rebound'),
		content: <span style={{ textDecoration: 'underline' }}>U</span>,
	},
	{
		command: 'strikeThrough',
		label: __('Strikethrough', 'cart-rebound'),
		content: <span style={{ textDecoration: 'line-through' }}>S</span>,
	},
] as const;

const BLOCKS = [
	{ block: '<h2>', label: __('Heading', 'cart-rebound'), text: 'H1' },
	{ block: '<h3>', label: __('Subheading', 'cart-rebound'), text: 'H2' },
	{ block: '<p>', label: __('Paragraph', 'cart-rebound'), text: '¶' },
	{ block: '<blockquote>', label: __('Quote', 'cart-rebound'), text: '❝' },
] as const;

const ALIGN = ['Left', 'Center', 'Right'] as const;
const ALIGN_LABELS = {
	Left: __('Align left', 'cart-rebound'),
	Center: __('Align center', 'cart-rebound'),
	Right: __('Align right', 'cart-rebound'),
} as const;

const widthLabel = (width: string): string =>
	/* translators: %s: an image width percentage, for example 50%. */
	sprintf(__('Width %s', 'cart-rebound'), width);

type EditorMode = 'visual' | 'html';

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
	ariaLabel = __('Email body', 'cart-rebound'),
	actions,
}: {
	value: string;
	onChange: (html: string) => void;
	tags?: MergeTag[];
	ariaLabel?: string;
	// Rendered opposite the Visual/HTML switch — for actions that act on the
	// body as a whole, such as previewing it.
	actions?: ReactNode;
}) => {
	const baseId = useId();
	const ref = useRef<HTMLDivElement>(null);
	const codeRef = useRef<HTMLTextAreaElement>(null);
	const savedRange = useRef<Range | null>(null);
	const [active, setActive] = useState<Record<string, boolean>>({});
	const [selectedImg, setSelectedImg] = useState<HTMLImageElement | null>(
		null
	);
	const [mode, setMode] = useState<EditorMode>('visual');
	const [code, setCode] = useState(value);

	// HTML waiting to be written into the contentEditable: the initial value on
	// mount, then whatever the HTML view produced when switching back. Held in a
	// ref so re-seeding never depends on render order.
	const pendingSeed = useRef<string | null>(value);
	const seedEmits = useRef(false);

	useEffect(() => {
		const el = ref.current;
		const html = pendingSeed.current;

		if (mode !== 'visual' || !el || html === null) {
			return;
		}

		el.innerHTML = html;
		wrapLooseNodes(el);
		pendingSeed.current = null;

		// New lines become <p>, not <div>, so they match what wpautop and
		// the email template produce.
		try {
			document.execCommand('defaultParagraphSeparator', false, 'p');
		} catch {
			// Not supported everywhere; the default separator is fine.
		}

		// Only edits made in the HTML view are worth emitting; seeding the
		// initial value must not mark the form as changed.
		if (seedEmits.current) {
			seedEmits.current = false;
			onChange(el.innerHTML);
		}
		// Runs when the visual view (re)mounts; the HTML to write lives in a ref.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [mode]);

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
		if (mode === 'html') {
			const field = codeRef.current;

			if (!field) {
				return;
			}

			const start = field.selectionStart;
			const end = field.selectionEnd;
			const next =
				field.value.slice(0, start) + text + field.value.slice(end);

			setCode(next);
			onChange(next);
			field.focus();

			// Park the caret after the inserted tag once React has written the
			// new value, so typing continues where the tag ended.
			window.requestAnimationFrame(() => {
				const caret = start + text.length;
				field.setSelectionRange(caret, caret);
			});

			return;
		}

		ref.current?.focus();
		restoreSelection();
		document.execCommand('insertText', false, text);
		emit();
		refresh();
	};

	// Re-indent whatever is in the HTML view now. Formatting only moves
	// whitespace between blocks, so the emitted markup renders identically.
	const formatCode = () => {
		const next = formatHtml(code);

		if (next === code) {
			return;
		}

		setCode(next);
		onChange(next);
	};

	// Switching views hands the current markup to the other one: the visual view
	// is the source of truth in visual mode, the textarea in HTML mode.
	const switchMode = (next: EditorMode) => {
		if (next === mode) {
			return;
		}

		if (next === 'html') {
			// The contentEditable emits one unbroken line; indent it so the
			// markup is readable before anyone has to edit it.
			setCode(formatHtml(ref.current?.innerHTML ?? value));
		} else {
			pendingSeed.current = code;
			seedEmits.current = true;
			setSelectedImg(null);
		}

		setMode(next);
	};

	const addLink = () => {
		// eslint-disable-next-line no-alert
		const url = window.prompt(__('Link URL (https://…)', 'cart-rebound'));

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
			const url = window.prompt(
				__('Image URL (https://…)', 'cart-rebound')
			);

			if (url) {
				insertImage(url, '');
			}

			return;
		}

		saveSelection();

		const frame = media({
			title: __('Insert image', 'cart-rebound'),
			button: { text: __('Insert into email', 'cart-rebound') },
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

	const selectNode = (node: Node) => {
		const selection = getSelection();
		const doc = ref.current?.ownerDocument;

		if (!selection || !doc) {
			return;
		}

		const range = doc.createRange();
		range.selectNode(node);
		selection.removeAllRanges();
		selection.addRange(range);
	};

	// Click-to-select an image so the contextual image toolbar can resize it
	// (Chromium removed native image resize handles in contentEditable).
	const onContentClick = (event: MouseEvent) => {
		const target = event.target;

		if (
			target instanceof HTMLImageElement &&
			ref.current?.contains(target)
		) {
			setSelectedImg(target);
			selectNode(target);
		} else {
			setSelectedImg(null);
		}
	};

	const sizeImage = (width: string) => {
		if (!selectedImg) {
			return;
		}

		if (width === '') {
			selectedImg.style.removeProperty('width');
		} else {
			selectedImg.style.width = width;
		}

		selectedImg.style.maxWidth = '100%';
		selectedImg.style.height = 'auto';
		emit();
		selectNode(selectedImg);
	};

	const alignImage = (align: 'left' | 'center' | 'right') => {
		if (!selectedImg) {
			return;
		}

		const style = selectedImg.style;
		style.removeProperty('float');
		style.removeProperty('margin');
		style.removeProperty('display');

		if (align === 'center') {
			style.display = 'block';
			style.marginLeft = 'auto';
			style.marginRight = 'auto';
		} else if (align === 'left') {
			style.float = 'left';
			style.margin = '0 14px 8px 0';
		} else {
			style.float = 'right';
			style.margin = '0 0 8px 14px';
		}

		emit();
		selectNode(selectedImg);
	};

	const removeImage = () => {
		if (!selectedImg) {
			return;
		}

		selectedImg.remove();
		setSelectedImg(null);
		emit();
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

	const modeTab = (target: EditorMode, label: string) => (
		<button
			type="button"
			role="tab"
			id={`${baseId}-tab-${target}`}
			aria-selected={mode === target}
			aria-controls={`${baseId}-panel-${target}`}
			className={`cr-rte__mode${mode === target ? ' is-active' : ''}`}
			onClick={() => {
				switchMode(target);
			}}
		>
			{label}
		</button>
	);

	const tagPicker = tags.length > 0 && (
		<Combobox
			compact
			ariaLabel={__('Insert merge tag', 'cart-rebound')}
			placeholder={__('Insert tag…', 'cart-rebound')}
			value=""
			options={tags}
			onChange={(next) => {
				if (next !== '') {
					insert(next);
				}
			}}
		/>
	);

	return (
		<div className="cr-rte">
			<div className="cr-rte__modes">
				<div
					className="cr-rte__modeset"
					role="tablist"
					aria-label={__('Editor mode', 'cart-rebound')}
				>
					{modeTab('visual', __('Visual', 'cart-rebound'))}
					{modeTab('html', __('HTML', 'cart-rebound'))}
				</div>
				{actions}
			</div>

			{mode === 'html' ? (
				<>
					<div className="cr-rte__bar">
						<div className="cr-rte__group">
							{button(
								'format',
								__('Format HTML', 'cart-rebound'),
								formatCode,
								__('Format', 'cart-rebound')
							)}
						</div>
						<span className="cr-rte__spacer" />
						{tagPicker}
					</div>
					<div
						id={`${baseId}-panel-html`}
						role="tabpanel"
						aria-labelledby={`${baseId}-tab-html`}
					>
						<textarea
							ref={codeRef}
							className="cr-rte__code"
							aria-label={ariaLabel}
							spellCheck={false}
							value={code}
							onChange={(event) => {
								setCode(event.target.value);
								onChange(event.target.value);
							}}
						/>
					</div>
				</>
			) : (
				<>
					<div
						className="cr-rte__bar"
						role="toolbar"
						aria-label={__('Formatting', 'cart-rebound')}
					>
						<div className="cr-rte__group">
							{button(
								'undo',
								__('Undo', 'cart-rebound'),
								() => run('undo'),
								<IconUndo />
							)}
							{button(
								'redo',
								__('Redo', 'cart-rebound'),
								() => run('redo'),
								<IconRedo />
							)}
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
								title={__('Text colour', 'cart-rebound')}
								aria-label={__('Text colour', 'cart-rebound')}
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
								title={__('Highlight colour', 'cart-rebound')}
								aria-label={__(
									'Highlight colour',
									'cart-rebound'
								)}
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
									ALIGN_LABELS[dir],
									() => run(`justify${dir}`),
									<IconAlign dir={dir} />,
									`justify${dir}`
								)
							)}
						</div>

						<div className="cr-rte__group">
							{button(
								'ul',
								__('Bulleted list', 'cart-rebound'),
								() => run('insertUnorderedList'),
								'• —',
								'insertUnorderedList'
							)}
							{button(
								'ol',
								__('Numbered list', 'cart-rebound'),
								() => run('insertOrderedList'),
								'1.',
								'insertOrderedList'
							)}
							{button(
								'outdent',
								__('Decrease indent', 'cart-rebound'),
								() => run('outdent'),
								'«'
							)}
							{button(
								'indent',
								__('Increase indent', 'cart-rebound'),
								() => run('indent'),
								'»'
							)}
						</div>

						<div className="cr-rte__group">
							{button(
								'link',
								__('Insert link', 'cart-rebound'),
								addLink,
								__('Link', 'cart-rebound')
							)}
							{button(
								'unlink',
								__('Remove link', 'cart-rebound'),
								() => run('unlink'),
								__('Unlink', 'cart-rebound')
							)}
							{button(
								'image',
								__('Insert image', 'cart-rebound'),
								addImage,
								__('Image', 'cart-rebound')
							)}
							{button(
								'hr',
								__('Divider', 'cart-rebound'),
								() => run('insertHorizontalRule'),
								'―'
							)}
						</div>

						<div className="cr-rte__group">
							{button(
								'clear',
								__('Clear formatting', 'cart-rebound'),
								() => run('removeFormat'),
								__('Clear', 'cart-rebound')
							)}
						</div>

						{tagPicker && (
							<>
								<span className="cr-rte__spacer" />
								{tagPicker}
							</>
						)}
					</div>

					{selectedImg && (
						<div
							className="cr-rte__imagebar"
							role="toolbar"
							aria-label={__('Image', 'cart-rebound')}
						>
							<span className="cr-rte__imagebar-label">
								{__('Image size', 'cart-rebound')}
							</span>
							<div className="cr-rte__group">
								{['25%', '50%', '75%', '100%'].map((width) =>
									button(
										`w${width}`,
										widthLabel(width),
										() => sizeImage(width),
										width
									)
								)}
								{button(
									'wauto',
									__('Original size', 'cart-rebound'),
									() => sizeImage(''),
									__('Auto', 'cart-rebound')
								)}
							</div>
							<div className="cr-rte__group">
								{button(
									'imgleft',
									ALIGN_LABELS.Left,
									() => alignImage('left'),
									<IconAlign dir="Left" />
								)}
								{button(
									'imgcenter',
									ALIGN_LABELS.Center,
									() => alignImage('center'),
									<IconAlign dir="Center" />
								)}
								{button(
									'imgright',
									ALIGN_LABELS.Right,
									() => alignImage('right'),
									<IconAlign dir="Right" />
								)}
							</div>
							<span className="cr-rte__spacer" />
							{button(
								'imgremove',
								__('Remove image', 'cart-rebound'),
								removeImage,
								__('Remove', 'cart-rebound')
							)}
						</div>
					)}

					<div
						className="cr-rte__canvas"
						id={`${baseId}-panel-visual`}
						role="tabpanel"
						aria-labelledby={`${baseId}-tab-visual`}
					>
						<div
							ref={ref}
							className="cr-rte__content"
							contentEditable
							suppressContentEditableWarning
							role="textbox"
							tabIndex={0}
							aria-multiline="true"
							aria-label={ariaLabel}
							onInput={() => {
								emit();
								setSelectedImg(null);
							}}
							onKeyUp={refresh}
							onMouseUp={refresh}
							onClick={onContentClick}
							onFocus={syncActive}
							onBlur={() => {
								saveSelection();
								emit();
							}}
						/>
					</div>
				</>
			)}
		</div>
	);
};
