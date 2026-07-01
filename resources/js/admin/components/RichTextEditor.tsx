/**
 * Lightweight, dependency-free rich-text editor for email bodies.
 *
 * A contentEditable surface with a formatting toolbar. It is uncontrolled: the
 * initial HTML is written once on mount, and edits are emitted via onChange.
 * Remount it (change its `key`) to load a different value — this keeps the
 * caret stable while typing instead of fighting React re-renders.
 *
 * Selection handling: formatting buttons preventDefault on mousedown so the
 * editor keeps focus and the current selection (execCommand applies to it
 * directly — no focus() call, which would collapse it). The merge-tag <select>
 * cannot preventDefault (that would stop it opening), so the caret/selection is
 * saved on blur and restored before inserting.
 */
import { useEffect, useRef, type MouseEvent } from 'react';

export interface MergeTag {
	label: string;
	value: string;
}

const TOOLS = [
	{ command: 'bold', label: 'Bold', content: <strong>B</strong> },
	{ command: 'italic', label: 'Italic', content: <em>I</em> },
	{
		command: 'underline',
		label: 'Underline',
		content: <span style={{ textDecoration: 'underline' }}>U</span>,
	},
] as const;

const BLOCKS = [
	{ block: '<h2>', label: 'Heading', text: 'H1' },
	{ block: '<h3>', label: 'Subheading', text: 'H2' },
	{ block: '<p>', label: 'Paragraph', text: '¶' },
] as const;

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

	// Remember the current selection while it is still inside the editor, so a
	// control that steals focus (the tag <select>) can restore it afterwards.
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

	const run = (command: string, argument?: string) => {
		// execCommand is deprecated but remains the only broadly-supported,
		// dependency-free way to format a contentEditable. No focus() call: the
		// button's mousedown-preventDefault already kept focus + selection.
		document.execCommand(command, false, argument);
		emit();
		saveSelection();
	};

	const insert = (text: string) => {
		ref.current?.focus();
		restoreSelection();
		document.execCommand('insertText', false, text);
		emit();
		saveSelection();
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
		saveSelection();
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

	return (
		<div className="cr-rte">
			<div className="cr-rte__bar" role="toolbar" aria-label="Formatting">
				{TOOLS.map((tool) => (
					<button
						key={tool.command}
						type="button"
						className="cr-rte__btn"
						title={tool.label}
						aria-label={tool.label}
						onMouseDown={keepSelection}
						onClick={() => {
							run(tool.command);
						}}
					>
						{tool.content}
					</button>
				))}

				<span className="cr-rte__sep" />

				{BLOCKS.map((item) => (
					<button
						key={item.block}
						type="button"
						className="cr-rte__btn"
						title={item.label}
						aria-label={item.label}
						onMouseDown={keepSelection}
						onClick={() => {
							run('formatBlock', item.block);
						}}
					>
						{item.text}
					</button>
				))}

				<span className="cr-rte__sep" />

				<button
					type="button"
					className="cr-rte__btn"
					title="Bulleted list"
					aria-label="Bulleted list"
					onMouseDown={keepSelection}
					onClick={() => {
						run('insertUnorderedList');
					}}
				>
					• —
				</button>
				<button
					type="button"
					className="cr-rte__btn"
					title="Numbered list"
					aria-label="Numbered list"
					onMouseDown={keepSelection}
					onClick={() => {
						run('insertOrderedList');
					}}
				>
					1.
				</button>

				<span className="cr-rte__sep" />

				<button
					type="button"
					className="cr-rte__btn"
					title="Insert link"
					aria-label="Insert link"
					onMouseDown={keepSelection}
					onClick={addLink}
				>
					Link
				</button>
				<button
					type="button"
					className="cr-rte__btn"
					title="Insert image"
					aria-label="Insert image"
					onMouseDown={keepSelection}
					onClick={addImage}
				>
					Image
				</button>
				<button
					type="button"
					className="cr-rte__btn"
					title="Clear formatting"
					aria-label="Clear formatting"
					onMouseDown={keepSelection}
					onClick={() => {
						run('removeFormat');
					}}
				>
					Clear
				</button>

				{tags.length > 0 && (
					<>
						<span className="cr-rte__spacer" />
						<select
							className="cr-select is-compact"
							aria-label="Insert merge tag"
							value=""
							onChange={(event) => {
								const tag = event.target.value;

								if (tag !== '') {
									insert(tag);
									event.target.value = '';
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
				onKeyUp={saveSelection}
				onMouseUp={saveSelection}
				onBlur={() => {
					saveSelection();
					emit();
				}}
			/>
		</div>
	);
};
