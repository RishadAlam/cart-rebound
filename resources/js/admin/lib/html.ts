/**
 * HTML helpers for the email body editor.
 *
 * The editor stores whatever markup the contentEditable produced — one long
 * line. These helpers give the HTML view a readable version of it and keep the
 * visual view's block structure sane, without changing how the email renders:
 * line breaks are only ever added between block-level elements, where the
 * browser collapses them away. Inline runs are copied verbatim, entities and
 * all, so `&nbsp;` never turns into a bare space.
 */

// Elements that carry their own line box, so a newline around them is inert.
export const BLOCK_TAGS = new Set([
	'P',
	'DIV',
	'H1',
	'H2',
	'H3',
	'H4',
	'H5',
	'H6',
	'UL',
	'OL',
	'LI',
	'BLOCKQUOTE',
	'PRE',
	'HR',
	'TABLE',
	'THEAD',
	'TBODY',
	'TR',
	'TD',
	'TH',
	'FIGURE',
	'FIGCAPTION',
]);

// Elements with no closing tag.
const VOID_TAGS = new Set([
	'AREA',
	'BASE',
	'BR',
	'COL',
	'EMBED',
	'HR',
	'IMG',
	'INPUT',
	'LINK',
	'META',
	'SOURCE',
	'TRACK',
	'WBR',
]);

// Whitespace inside these is significant, so their markup is left untouched.
const VERBATIM_TAGS = new Set(['PRE', 'TEXTAREA']);

const INDENT = '  ';

const isBlock = (node: Node): node is HTMLElement =>
	node instanceof HTMLElement && BLOCK_TAGS.has(node.tagName);

const isBlank = (node: Node): boolean =>
	node.nodeType === Node.TEXT_NODE && (node.textContent ?? '').trim() === '';

const openTag = (el: Element): string => {
	const name = el.tagName.toLowerCase();
	const attributes = Array.from(el.attributes)
		.map(
			(attribute) =>
				` ${attribute.name}="${attribute.value.replace(/"/g, '&quot;')}"`
		)
		.join('');

	return `<${name}${attributes}>`;
};

// Serialise a run of inline siblings through a detached element so the original
// markup — entities, attribute order, self-closing style — survives untouched.
const inlineRun = (nodes: Node[], doc: Document): string => {
	const holder = doc.createElement('div');

	nodes.forEach((node) => holder.append(node.cloneNode(true)));

	return holder.innerHTML.trim();
};

const formatElement = (el: Element, depth: number): string => {
	const pad = INDENT.repeat(depth);

	if (VOID_TAGS.has(el.tagName)) {
		return pad + openTag(el);
	}

	const name = el.tagName.toLowerCase();

	if (VERBATIM_TAGS.has(el.tagName)) {
		return `${pad}${openTag(el)}${el.innerHTML}</${name}>`;
	}

	const children = Array.from(el.childNodes).filter((node) => !isBlank(node));

	if (children.length === 0) {
		return `${pad}${openTag(el)}</${name}>`;
	}

	if (!children.some(isBlock)) {
		return `${pad}${openTag(el)}${el.innerHTML.trim()}</${name}>`;
	}

	const lines = formatNodes(children, depth + 1, el.ownerDocument);

	return `${pad}${openTag(el)}\n${lines.join('\n')}\n${pad}</${name}>`;
};

// Block children get their own line; consecutive inline siblings are kept
// together on one line so no whitespace lands inside a formatted run.
const formatNodes = (nodes: Node[], depth: number, doc: Document): string[] => {
	const pad = INDENT.repeat(depth);
	const lines: string[] = [];
	let run: Node[] = [];

	const flushRun = () => {
		if (run.length === 0) {
			return;
		}

		const markup = inlineRun(run, doc);

		if (markup !== '') {
			lines.push(pad + markup);
		}

		run = [];
	};

	nodes.forEach((node) => {
		if (isBlock(node)) {
			flushRun();
			lines.push(formatElement(node, depth));

			return;
		}

		run.push(node);
	});

	flushRun();

	return lines;
};

/**
 * Re-indent HTML for reading. Returns the input unchanged if it cannot be
 * parsed, so a half-typed tag never wipes out what the author was writing.
 *
 * @param html Markup to indent.
 * @return The indented markup.
 */
export const formatHtml = (html: string): string => {
	if (html.trim() === '' || typeof document === 'undefined') {
		return html;
	}

	const holder = document.createElement('div');
	holder.innerHTML = html;

	const children = Array.from(holder.childNodes).filter(
		(node) => !isBlank(node)
	);

	if (children.length === 0) {
		return html;
	}

	return formatNodes(children, 0, document).join('\n');
};

/**
 * Wrap loose root-level nodes in a paragraph.
 *
 * Without a block wrapper, execCommand list/align commands apply to the whole
 * editable instead of the current line, which is how an entire email body ends
 * up as one bulleted list.
 *
 * @param root The editable element to normalise in place.
 */
export const wrapLooseNodes = (root: HTMLElement) => {
	const doc = root.ownerDocument;
	let buffer: ChildNode[] = [];

	const flush = () => {
		const first = buffer[0];

		if (!first) {
			return;
		}

		const paragraph = doc.createElement('p');
		first.before(paragraph);
		buffer.forEach((node) => paragraph.append(node));
		buffer = [];
	};

	Array.from(root.childNodes).forEach((node) => {
		if (isBlock(node)) {
			flush();

			return;
		}

		if (isBlank(node)) {
			return;
		}

		buffer.push(node);
	});

	flush();
};
