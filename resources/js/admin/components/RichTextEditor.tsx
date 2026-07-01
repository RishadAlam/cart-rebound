/**
 * Lightweight, dependency-free rich-text editor for email bodies.
 *
 * A contentEditable surface with a formatting toolbar. It is uncontrolled: the
 * initial HTML is written once on mount, and edits are emitted via onChange.
 * Remount it (change its `key`) to load a different value — this keeps the
 * caret stable while typing instead of fighting React re-renders.
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

	const run = (command: string, argument?: string) => {
		ref.current?.focus();
		// execCommand is deprecated but remains the only broadly-supported,
		// dependency-free way to apply inline formatting to a contentEditable.
		document.execCommand(command, false, argument);
		emit();
	};

	const insert = (text: string) => {
		ref.current?.focus();
		document.execCommand('insertText', false, text);
		emit();
	};

	const addLink = () => {
		// eslint-disable-next-line no-alert
		const url = window.prompt('Link URL (https://…)');

		if (url) {
			run('createLink', url);
		}
	};

	// Keep the editor's selection when a toolbar control is pressed.
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
							onMouseDown={keepSelection}
							onChange={(event) => {
								if (event.target.value !== '') {
									insert(event.target.value);
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
				aria-multiline="true"
				aria-label={ariaLabel}
				onInput={emit}
				onBlur={emit}
			/>
		</div>
	);
};
