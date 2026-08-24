/**
 * Templates page — manage the recovery-email templates.
 *
 * A master/detail layout: pick a template on the left, edit it (rich-text body,
 * subject, sender, coupon) on the right. Exactly one template is the default —
 * the one automatic abandonment emails use.
 */
import { Fragment, useEffect, useRef, useState, type ChangeEvent } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ChipSelect } from '../components/ChipSelect';
import { Combobox } from '../components/Combobox';
import { RichTextEditor, type MergeTag } from '../components/RichTextEditor';
import type { TemplatePreview } from '../api/endpoints';
import {
	useCoupons,
	useCreateTemplate,
	useDeleteTemplate,
	usePreviewTemplate,
	useTestTemplate,
	useSetDefaultTemplate,
	useTemplates,
	useUpdateTemplate,
} from '../hooks/useApi';
import type {
	EmailTemplate,
	ProductColumn,
	ProductTableConfig,
} from '../types/api';

type Feedback = { type: 'success' | 'error'; message: string };

// The editor is one form saved in one go; the panes only decide what is on
// screen, so a long template never buries the fields that matter today.
type Pane = 'message' | 'table' | 'delivery';

const PANES: { id: Pane; label: string }[] = [
	{ id: 'message', label: __('Message', 'cart-rebound') },
	{ id: 'table', label: __('Product table', 'cart-rebound') },
	{ id: 'delivery', label: __('Delivery', 'cart-rebound') },
];

const BLANK: EmailTemplate = {
	id: '',
	name: '',
	subject: '',
	body: '',
	from_name: '',
	from_email: '',
	coupon: '',
	is_default: false,
	table: {
		enabled: false,
		style: 'lined',
		columns: ['name', 'quantity', 'subtotal'],
		image_size: 48,
		show_header: true,
		with_tax: false,
		link_items: true,
		show_variations: true,
		show_total_row: false,
		max_items: 0,
	},
};

const COLUMN_OPTIONS: { value: ProductColumn; label: string }[] = [
	{ value: 'image', label: __('Thumbnail', 'cart-rebound') },
	{ value: 'name', label: __('Product', 'cart-rebound') },
	{ value: 'sku', label: __('SKU', 'cart-rebound') },
	{ value: 'quantity', label: __('Quantity', 'cart-rebound') },
	{ value: 'price', label: __('Unit price', 'cart-rebound') },
	{ value: 'subtotal', label: __('Line total', 'cart-rebound') },
];

const STYLE_OPTIONS = [
	{ value: 'lined', label: __('Ruled rows', 'cart-rebound') },
	{ value: 'boxed', label: __('Boxed grid', 'cart-rebound') },
	{ value: 'plain', label: __('No rules', 'cart-rebound') },
];

const IMAGE_SIZE_OPTIONS = [
	{ value: '32', label: __('Compact — 32px', 'cart-rebound') },
	{ value: '48', label: __('Standard — 48px', 'cart-rebound') },
	{ value: '64', label: __('Roomy — 64px', 'cart-rebound') },
];

// Merge tags, grouped shopper → cart → store, in the order the picker lists
// them. TAGS drives the picker; TOKEN_DOCS explains each one under the editor.
const TOKEN_DOCS = [
	{
		token: '{first_name}',
		description: __(
			"The shopper's first name (blank if it wasn't captured).",
			'cart-rebound'
		),
	},
	{
		token: '{last_name}',
		description: __("The shopper's surname.", 'cart-rebound'),
	},
	{
		token: '{full_name}',
		description: __(
			'First and surname together, with the spacing tidied up.',
			'cart-rebound'
		),
	},
	{
		token: '{email}',
		description: __(
			'The address the recovery email is going to.',
			'cart-rebound'
		),
	},
	{
		token: '{products}',
		description: __(
			'A bulleted list of the items left in the cart.',
			'cart-rebound'
		),
	},
	{
		token: '{products_table}',
		description: __(
			'The same items as a table of name, quantity and line total.',
			'cart-rebound'
		),
	},
	{
		token: '{product_names}',
		description: __(
			'Item names on one line, separated by commas.',
			'cart-rebound'
		),
	},
	{
		token: '{items_count}',
		description: __('How many items the cart holds.', 'cart-rebound'),
	},
	{
		token: '{cart_total}',
		description: __(
			'Cart value, formatted in the store currency.',
			'cart-rebound'
		),
	},
	{
		token: '{abandoned_on}',
		description: __(
			"The date the cart was left, in the site's date format.",
			'cart-rebound'
		),
	},
	{
		token: '{recovery_url}',
		description: __(
			'A one-click link that restores the cart and reopens checkout.',
			'cart-rebound'
		),
	},
	{
		token: '{checkout_url}',
		description: __(
			'The plain checkout page address, with nothing restored.',
			'cart-rebound'
		),
	},
	{
		token: '{coupon_code}',
		description: __(
			'The coupon code selected below (blank if none is chosen).',
			'cart-rebound'
		),
	},
	{
		token: '{store_name}',
		description: __('The site title.', 'cart-rebound'),
	},
	{
		token: '{store_url}',
		description: __('The storefront home address.', 'cart-rebound'),
	},
	{
		token: '{store_email}',
		description: __(
			'Your notification address, or the site admin address.',
			'cart-rebound'
		),
	},
	{
		token: '{manager_name}',
		description: __(
			"The site admin's first name, for signing off.",
			'cart-rebound'
		),
	},
	{
		token: '{current_year}',
		description: __('This year — handy in a footer.', 'cart-rebound'),
	},
	{
		token: '{unsubscribe_url}',
		description: __(
			'An opt-out link that suppresses this address.',
			'cart-rebound'
		),
	},
];

const TAG_LABELS: Record<string, string> = {
	'{first_name}': __('Shopper first name', 'cart-rebound'),
	'{last_name}': __('Shopper surname', 'cart-rebound'),
	'{full_name}': __('Shopper full name', 'cart-rebound'),
	'{email}': __('Shopper email', 'cart-rebound'),
	'{products}': __('Left-behind items list', 'cart-rebound'),
	'{products_table}': __('Left-behind items table', 'cart-rebound'),
	'{product_names}': __('Item names in a row', 'cart-rebound'),
	'{items_count}': __('Item count', 'cart-rebound'),
	'{cart_total}': __('Cart value', 'cart-rebound'),
	'{abandoned_on}': __('Date left behind', 'cart-rebound'),
	'{recovery_url}': __('Restore cart link', 'cart-rebound'),
	'{checkout_url}': __('Checkout page link', 'cart-rebound'),
	'{coupon_code}': __('Coupon code', 'cart-rebound'),
	'{store_name}': __('Store name', 'cart-rebound'),
	'{store_url}': __('Storefront link', 'cart-rebound'),
	'{store_email}': __('Store contact email', 'cart-rebound'),
	'{manager_name}': __('Store manager name', 'cart-rebound'),
	'{current_year}': __('Current year', 'cart-rebound'),
	'{unsubscribe_url}': __('Opt-out link', 'cart-rebound'),
};

const TAGS: MergeTag[] = TOKEN_DOCS.map((doc) => ({
	label: TAG_LABELS[doc.token] ?? doc.token,
	value: doc.token,
}));

const messageOf = (error: unknown): string =>
	error instanceof Error
		? error.message
		: __('Something went wrong.', 'cart-rebound');

const EyeIcon = () => (
	<svg
		viewBox="0 0 16 16"
		width="14"
		height="14"
		fill="none"
		aria-hidden="true"
	>
		<path
			d="M1.6 8S3.9 3.6 8 3.6 14.4 8 14.4 8 12.1 12.4 8 12.4 1.6 8 1.6 8Z"
			stroke="currentColor"
			strokeWidth="1.3"
			strokeLinejoin="round"
		/>
		<circle cx="8" cy="8" r="2.1" stroke="currentColor" strokeWidth="1.3" />
	</svg>
);

export const Templates = () => {
	const { data: templates, isLoading, isFetching } = useTemplates();
	const { data: coupons } = useCoupons();
	const create = useCreateTemplate();
	const update = useUpdateTemplate();
	const remove = useDeleteTemplate();
	const setDefault = useSetDefaultTemplate();
	const preview = usePreviewTemplate();
	const test = useTestTemplate();

	const [selectedId, setSelectedId] = useState<string | null>(null);
	const [pane, setPane] = useState<Pane>('message');
	const [query, setQuery] = useState('');
	const [form, setForm] = useState<EmailTemplate>(BLANK);
	// The template as it was last loaded or saved, so the save bar can say
	// whether anything is actually pending.
	const [baseline, setBaseline] = useState<EmailTemplate>(BLANK);
	const [editorKey, setEditorKey] = useState(0);
	const [feedback, setFeedback] = useState<Feedback | null>(null);
	const [testEmail, setTestEmail] = useState('');
	const [previewData, setPreviewData] = useState<TemplatePreview | null>(
		null
	);
	const previewRef = useRef<HTMLDialogElement>(null);

	const isNew = selectedId === 'new';
	const busy = create.isPending || update.isPending;
	const dirty = JSON.stringify(form) !== JSON.stringify(baseline);

	const load = (template: EmailTemplate, id: string) => {
		setSelectedId(id);
		setForm(template);
		setBaseline(template);
		setPane('message');
		setEditorKey((key) => key + 1);
	};

	// Leaving a half-edited template silently loses the edits, so ask first.
	const loadGuarded = (template: EmailTemplate, id: string) => {
		if (id === selectedId) {
			return;
		}

		if (
			dirty &&
			// eslint-disable-next-line no-alert
			!window.confirm(
				__(
					'This template has unsaved changes. Discard them?',
					'cart-rebound'
				)
			)
		) {
			return;
		}

		load(template, id);
	};

	// Keep a valid template selected: pick the default on first load, and
	// re-select after the current one disappears (e.g. was deleted, so the
	// refetched list no longer contains selectedId). Skips the "new" draft.
	useEffect(() => {
		if (!templates || selectedId === 'new') {
			return;
		}

		// A just-created template is selected before its id can appear in the
		// cached list — the create invalidates the query, so the refetch is still
		// in flight. Rescuing here would read that gap as "the selection vanished"
		// and throw the editor onto the default template, one keystroke after the
		// merchant created this one. Wait for the list to settle instead.
		if (isFetching) {
			return;
		}

		const stillExists =
			selectedId !== null &&
			templates.some((template) => template.id === selectedId);

		if (stillExists) {
			return;
		}

		const initial =
			templates.find((template) => template.is_default) ?? templates[0];

		if (initial) {
			load(initial, initial.id);
		}
	}, [templates, selectedId, isFetching]);

	useEffect(() => {
		if (!feedback) {
			return;
		}

		const timer = window.setTimeout(() => {
			setFeedback(null);
		}, 4000);

		return () => {
			window.clearTimeout(timer);
		};
	}, [feedback]);

	useEffect(() => {
		const el = previewRef.current;

		if (!el) {
			return;
		}

		if (previewData && !el.open) {
			el.showModal();
		} else if (!previewData && el.open) {
			el.close();
		}
	}, [previewData]);

	const onPreview = () => {
		preview.mutate(
			{
				subject: form.subject,
				body: form.body,
				coupon: form.coupon,
				table: form.table,
			},
			{
				onSuccess: (data) => {
					setPreviewData(data);
				},
				onError: (error: unknown) => {
					setFeedback({ type: 'error', message: messageOf(error) });
				},
			}
		);
	};

	const setField = <K extends keyof EmailTemplate>(
		key: K,
		value: EmailTemplate[K]
	) => {
		setForm((previous) => ({ ...previous, [key]: value }));
	};

	const onText =
		(key: 'name' | 'subject' | 'from_name' | 'from_email') =>
		(event: ChangeEvent<HTMLInputElement>) => {
			setField(key, event.target.value);
		};

	const setTable = <K extends keyof ProductTableConfig>(
		key: K,
		value: ProductTableConfig[K]
	) => {
		setForm((previous) => ({
			...previous,
			table: { ...previous.table, [key]: value },
		}));
	};

	// Product-table booleans all render the same switch row.
	const tableToggle = (
		key: {
			[
				K in keyof ProductTableConfig
			]: ProductTableConfig[K] extends boolean ? K : never;
		}[keyof ProductTableConfig],
		id: string
	) => (
		<span className="cr-switch">
			<input
				id={id}
				type="checkbox"
				checked={form.table[key]}
				onChange={(event) => {
					setTable(key, event.target.checked);
				}}
			/>
			<span className="cr-switch__track">
				<span className="cr-switch__thumb" />
			</span>
		</span>
	);

	const startNew = () => {
		loadGuarded(
			{ ...BLANK, name: __('New template', 'cart-rebound') },
			'new'
		);
	};

	const onTest = () => {
		test.mutate(
			{
				email: testEmail,
				subject: form.subject,
				body: form.body,
				coupon: form.coupon,
				from_name: form.from_name,
				from_email: form.from_email,
				table: form.table,
			},
			{
				onSuccess: (data) => {
					setFeedback(
						data.sent
							? {
									type: 'success',
									message: __(
										'Test email sent.',
										'cart-rebound'
									),
								}
							: {
									type: 'error',
									message:
										data.message ??
										__(
											'Could not send the test email.',
											'cart-rebound'
										),
								}
					);
				},
				onError: (error: unknown) => {
					setFeedback({ type: 'error', message: messageOf(error) });
				},
			}
		);
	};

	const onSave = () => {
		if (form.name.trim() === '' || form.subject.trim() === '') {
			setFeedback({
				type: 'error',
				message: __('Name and subject are required.', 'cart-rebound'),
			});

			return;
		}

		const done = (saved: EmailTemplate, message: string) => {
			load(saved, saved.id);
			setFeedback({ type: 'success', message });
		};

		const onError = (error: unknown) => {
			setFeedback({ type: 'error', message: messageOf(error) });
		};

		if (isNew) {
			create.mutate(
				{
					name: form.name,
					subject: form.subject,
					body: form.body,
					from_name: form.from_name,
					from_email: form.from_email,
					coupon: form.coupon,
					is_default: form.is_default,
					table: form.table,
				},
				{
					onSuccess: (saved) => {
						done(saved, __('Template created.', 'cart-rebound'));
					},
					onError,
				}
			);

			return;
		}

		update.mutate(form, {
			onSuccess: (saved) => {
				done(saved, __('Template saved.', 'cart-rebound'));
			},
			onError,
		});
	};

	const onSetDefault = () => {
		if (isNew) {
			setField('is_default', true);

			return;
		}

		setDefault.mutate(form.id, {
			onSuccess: () => {
				setField('is_default', true);
				setFeedback({
					type: 'success',
					message: __('Default template set.', 'cart-rebound'),
				});
			},
			onError: (error: unknown) => {
				setFeedback({ type: 'error', message: messageOf(error) });
			},
		});
	};

	const onDelete = () => {
		if (isNew) {
			setSelectedId(null);

			return;
		}

		// eslint-disable-next-line no-alert
		const confirmed = window.confirm(
			sprintf(
				/* translators: %s: template name. */
				__('Delete the "%s" template?', 'cart-rebound'),
				form.name
			)
		);

		if (!confirmed) {
			return;
		}

		remove.mutate(form.id, {
			onSuccess: () => {
				setSelectedId(null);
				setFeedback({
					type: 'success',
					message: __('Template deleted.', 'cart-rebound'),
				});
			},
			onError: (error: unknown) => {
				setFeedback({ type: 'error', message: messageOf(error) });
			},
		});
	};

	if (isLoading) {
		return (
			<div className="cr-card cr-section">
				<div
					className="cr-skeleton"
					style={{ height: 16, width: '30%' }}
				/>
				<div
					className="cr-skeleton"
					style={{ height: 200, width: '100%', marginTop: 16 }}
				/>
			</div>
		);
	}

	const list = templates ?? [];
	const needle = query.trim().toLowerCase();
	const visible =
		needle === ''
			? list
			: list.filter((template) =>
					`${template.name} ${template.subject}`
						.toLowerCase()
						.includes(needle)
				);

	let saveLabel: string = __('Save', 'cart-rebound');

	if (busy) {
		saveLabel = __('Saving…', 'cart-rebound');
	} else if (isNew) {
		saveLabel = __('Create template', 'cart-rebound');
	}

	return (
		<div>
			{feedback && (
				<div
					className={`cr-notice is-${feedback.type}`}
					role="status"
					style={{ marginBottom: 12 }}
				>
					{feedback.message}
				</div>
			)}

			<div className="cr-templates">
				<aside className="cr-templates__list cr-card">
					<div className="cr-templates__listhead">
						<span>
							{sprintf(
								/* translators: %d: number of saved templates. */
								__('Templates (%d)', 'cart-rebound'),
								list.length
							)}
						</span>
						<button
							type="button"
							className="cr-btn is-ghost is-sm"
							onClick={startNew}
						>
							{__('+ New', 'cart-rebound')}
						</button>
					</div>

					{list.length > 5 && (
						<input
							type="search"
							className="cr-input is-sm cr-templates__search"
							placeholder={__(
								'Filter templates…',
								'cart-rebound'
							)}
							aria-label={__('Filter templates', 'cart-rebound')}
							value={query}
							onChange={(event) => {
								setQuery(event.target.value);
							}}
						/>
					)}

					{visible.length === 0 && list.length > 0 && (
						<p className="cr-templates__empty">
							{__('No template matches that.', 'cart-rebound')}
						</p>
					)}

					{visible.map((template) => (
						<button
							key={template.id}
							type="button"
							className={`cr-templates__item${
								selectedId === template.id ? ' is-active' : ''
							}`}
							onClick={() => {
								loadGuarded(template, template.id);
							}}
						>
							<span className="cr-templates__text">
								<span className="cr-templates__name">
									{template.name !== ''
										? template.name
										: __('Untitled', 'cart-rebound')}
								</span>
								<span className="cr-templates__sub">
									{template.subject !== ''
										? template.subject
										: __('No subject yet', 'cart-rebound')}
								</span>
							</span>
							{template.is_default && (
								<span className="cr-tag">
									{__('Default', 'cart-rebound')}
								</span>
							)}
						</button>
					))}
					{isNew && (
						<div className="cr-templates__item is-active">
							<span className="cr-templates__text">
								<span className="cr-templates__name">
									{form.name !== ''
										? form.name
										: __('New template', 'cart-rebound')}
								</span>
								<span className="cr-templates__sub">
									{__('Not saved yet', 'cart-rebound')}
								</span>
							</span>
							<span className="cr-tag is-muted">
								{__('Draft', 'cart-rebound')}
							</span>
						</div>
					)}
				</aside>

				<section className="cr-templates__editor cr-card">
					<div className="cr-templates__head">
						<div className="cr-templates__edithead">
							<div>
								<h2 className="cr-section__title">
									{form.name !== ''
										? form.name
										: __('New template', 'cart-rebound')}
								</h2>
								<p className="cr-field__hint">
									{form.is_default
										? __(
												'Automatic abandonment emails use this template.',
												'cart-rebound'
											)
										: __(
												'Saved template — not the one automatic emails use.',
												'cart-rebound'
											)}
								</p>
							</div>
							<div className="cr-templates__editactions">
								{form.is_default ? (
									<span className="cr-tag">
										{__('Default', 'cart-rebound')}
									</span>
								) : (
									<button
										type="button"
										className="cr-btn is-ghost is-sm"
										onClick={onSetDefault}
										disabled={setDefault.isPending}
									>
										{__('Set as default', 'cart-rebound')}
									</button>
								)}
							</div>
						</div>

						<div
							className="cr-tabs is-inset"
							role="tablist"
							aria-label={__('Template sections', 'cart-rebound')}
						>
							{PANES.map((item) => (
								<button
									key={item.id}
									type="button"
									role="tab"
									aria-selected={pane === item.id}
									className={`cr-tab${pane === item.id ? ' is-active' : ''}`}
									onClick={() => {
										setPane(item.id);
									}}
								>
									{item.label}
								</button>
							))}
						</div>
					</div>

					<div
						className="cr-section"
						hidden={pane !== 'message'}
						role="tabpanel"
					>
						<div className="cr-field">
							<label
								htmlFor="cr-tpl-name"
								className="cr-field__label"
							>
								{__('Template name', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'Only you see this — it names the template in the list.',
									'cart-rebound'
								)}
							</p>
							<input
								id="cr-tpl-name"
								className="cr-input"
								type="text"
								value={form.name}
								onChange={onText('name')}
							/>
						</div>

						<div className="cr-field">
							<label
								htmlFor="cr-tpl-subject"
								className="cr-field__label"
							>
								{__('Subject', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'Merge tags work here too — {first_name} and {coupon_code} are the useful ones.',
									'cart-rebound'
								)}
							</p>
							<input
								id="cr-tpl-subject"
								className="cr-input"
								type="text"
								value={form.subject}
								onChange={onText('subject')}
							/>
						</div>

						<div className="cr-field">
							<span className="cr-field__label">
								{__('Body', 'cart-rebound')}
							</span>
							<RichTextEditor
								key={editorKey}
								value={form.body}
								tags={TAGS}
								onChange={(html) => {
									setField('body', html);
								}}
								actions={
									<button
										type="button"
										className="cr-btn is-ghost is-sm"
										onClick={onPreview}
										disabled={preview.isPending}
									>
										<EyeIcon />
										{preview.isPending
											? __('Rendering…', 'cart-rebound')
											: __(
													'Preview email',
													'cart-rebound'
												)}
									</button>
								}
							/>
							<p className="cr-field__hint">
								{__(
									'Insert tag… drops a merge tag at the caret. A “Complete your order” button is added automatically below the body.',
									'cart-rebound'
								)}
							</p>
							<details className="cr-tokens">
								<summary className="cr-tokens__title">
									{sprintf(
										/* translators: %d: number of available merge tags. */
										__(
											'What each of the %d merge tags becomes',
											'cart-rebound'
										),
										TOKEN_DOCS.length
									)}
								</summary>
								<dl className="cr-tokens__list">
									{TOKEN_DOCS.map((doc) => (
										<Fragment key={doc.token}>
											<dt>
												<code className="cr-code">
													{doc.token}
												</code>
											</dt>
											<dd>{doc.description}</dd>
										</Fragment>
									))}
								</dl>
							</details>
						</div>
					</div>

					<div
						className="cr-section"
						hidden={pane !== 'table'}
						role="tabpanel"
					>
						<p className="cr-section__desc">
							{__(
								'Controls the {products_table} merge tag. Leave this off and the tag renders product, quantity and line total on ruled rows.',
								'cart-rebound'
							)}
						</p>

						<div className="cr-field--row">
							<div>
								<label
									htmlFor="cr-tbl-enabled"
									className="cr-field__label"
								>
									{__(
										'Lay the table out myself',
										'cart-rebound'
									)}
								</label>
								<p className="cr-field__hint">
									{__(
										'Choose the columns, thumbnail size and totals shown in this template.',
										'cart-rebound'
									)}
								</p>
							</div>
							{tableToggle('enabled', 'cr-tbl-enabled')}
						</div>

						{form.table.enabled && (
							<>
								<h3 className="cr-subhead">
									{__('Columns', 'cart-rebound')}
								</h3>

								<div className="cr-field">
									<span className="cr-field__label">
										{__('Shown, in order', 'cart-rebound')}
									</span>
									<p className="cr-field__hint">
										{__(
											'Left to right, in the order you add them.',
											'cart-rebound'
										)}
									</p>
									<ChipSelect
										ariaLabel={__(
											'Product table columns',
											'cart-rebound'
										)}
										addLabel={__(
											'Add column…',
											'cart-rebound'
										)}
										emptyLabel={__(
											'No columns yet — the default three are used.',
											'cart-rebound'
										)}
										options={COLUMN_OPTIONS}
										value={form.table.columns}
										onChange={(next) => {
											setTable(
												'columns',
												next as ProductColumn[]
											);
										}}
									/>
								</div>

								<h3 className="cr-subhead">
									{__('Layout', 'cart-rebound')}
								</h3>

								<div className="cr-field__grid">
									<div className="cr-field">
										<span className="cr-field__label">
											{__('Table style', 'cart-rebound')}
										</span>
										<p className="cr-field__hint">
											{__(
												'How rows are separated.',
												'cart-rebound'
											)}
										</p>
										<Combobox
											ariaLabel={__(
												'Table style',
												'cart-rebound'
											)}
											options={STYLE_OPTIONS}
											value={form.table.style}
											onChange={(next) => {
												setTable(
													'style',
													next as ProductTableConfig['style']
												);
											}}
										/>
									</div>
									<div className="cr-field">
										<span className="cr-field__label">
											{__(
												'Thumbnail size',
												'cart-rebound'
											)}
										</span>
										<p className="cr-field__hint">
											{__(
												'Used once the Thumbnail column is on.',
												'cart-rebound'
											)}
										</p>
										<Combobox
											ariaLabel={__(
												'Thumbnail size',
												'cart-rebound'
											)}
											disabled={
												!form.table.columns.includes(
													'image'
												)
											}
											options={IMAGE_SIZE_OPTIONS}
											value={String(
												form.table.image_size
											)}
											onChange={(next) => {
												setTable(
													'image_size',
													Number(next)
												);
											}}
										/>
									</div>
								</div>

								<div className="cr-field--row">
									<div>
										<label
											htmlFor="cr-tbl-header"
											className="cr-field__label"
										>
											{__(
												'Show column headings',
												'cart-rebound'
											)}
										</label>
										<p className="cr-field__hint">
											{__(
												'Turn off for a bare list of rows.',
												'cart-rebound'
											)}
										</p>
									</div>
									{tableToggle(
										'show_header',
										'cr-tbl-header'
									)}
								</div>

								<h3 className="cr-subhead">
									{__('Row detail', 'cart-rebound')}
								</h3>

								<div className="cr-field--row">
									<div>
										<label
											htmlFor="cr-tbl-tax"
											className="cr-field__label"
										>
											{__(
												'Prices include tax',
												'cart-rebound'
											)}
										</label>
										<p className="cr-field__hint">
											{__(
												'Recalculated per product at send time, so it matches the storefront.',
												'cart-rebound'
											)}
										</p>
									</div>
									{tableToggle('with_tax', 'cr-tbl-tax')}
								</div>

								<div className="cr-field--row">
									<div>
										<label
											htmlFor="cr-tbl-link"
											className="cr-field__label"
										>
											{__(
												'Link rows to the product',
												'cart-rebound'
											)}
										</label>
										<p className="cr-field__hint">
											{__(
												'The thumbnail and name open the product page.',
												'cart-rebound'
											)}
										</p>
									</div>
									{tableToggle('link_items', 'cr-tbl-link')}
								</div>

								<div className="cr-field--row">
									<div>
										<label
											htmlFor="cr-tbl-variations"
											className="cr-field__label"
										>
											{__(
												'Show the chosen variation',
												'cart-rebound'
											)}
										</label>
										<p className="cr-field__hint">
											{__(
												'Adds a small line such as “Size: Large” under the name.',
												'cart-rebound'
											)}
										</p>
									</div>
									{tableToggle(
										'show_variations',
										'cr-tbl-variations'
									)}
								</div>

								<div className="cr-field--row">
									<div>
										<label
											htmlFor="cr-tbl-total"
											className="cr-field__label"
										>
											{__(
												'Close with a cart total row',
												'cart-rebound'
											)}
										</label>
										<p className="cr-field__hint">
											{__(
												'Repeats the cart value at the foot of the table.',
												'cart-rebound'
											)}
										</p>
									</div>
									{tableToggle(
										'show_total_row',
										'cr-tbl-total'
									)}
								</div>

								<div className="cr-field--row">
									<div>
										<label
											htmlFor="cr-tbl-max"
											className="cr-field__label"
										>
											{__(
												'Rows before “and N more”',
												'cart-rebound'
											)}
										</label>
										<p className="cr-field__hint">
											{__(
												'Keeps a 30-item cart from becoming a 30-row email. 0 lists everything.',
												'cart-rebound'
											)}
										</p>
									</div>
									<input
										id="cr-tbl-max"
										className="cr-input is-narrow"
										type="number"
										min={0}
										value={form.table.max_items}
										onChange={(event) => {
											const parsed = parseInt(
												event.target.value,
												10
											);

											setTable(
												'max_items',
												Number.isNaN(parsed)
													? 0
													: Math.max(0, parsed)
											);
										}}
									/>
								</div>
							</>
						)}
					</div>

					<div
						className="cr-section"
						hidden={pane !== 'delivery'}
						role="tabpanel"
					>
						<p className="cr-section__desc">
							{__(
								'Who the email comes from, and the offer it carries.',
								'cart-rebound'
							)}
						</p>

						<div className="cr-field__grid">
							<div className="cr-field">
								<label
									htmlFor="cr-tpl-fromname"
									className="cr-field__label"
								>
									{__('From name', 'cart-rebound')}
								</label>
								<p className="cr-field__hint">
									{__(
										'Leave blank to use the WordPress default sender.',
										'cart-rebound'
									)}
								</p>
								<input
									id="cr-tpl-fromname"
									className="cr-input"
									type="text"
									value={form.from_name}
									onChange={onText('from_name')}
								/>
							</div>
							<div className="cr-field">
								<label
									htmlFor="cr-tpl-fromemail"
									className="cr-field__label"
								>
									{__('From email', 'cart-rebound')}
								</label>
								<p className="cr-field__hint">
									{__(
										'Use an address on your own domain so the mail is not treated as spoofed.',
										'cart-rebound'
									)}
								</p>
								<input
									id="cr-tpl-fromemail"
									className="cr-input"
									type="email"
									value={form.from_email}
									onChange={onText('from_email')}
								/>
							</div>
						</div>

						<div className="cr-field">
							<span className="cr-field__label">
								{__('Coupon', 'cart-rebound')}
							</span>
							<p className="cr-field__hint">
								{__(
									'The code {coupon_code} prints. Cart Rebound never generates new coupons.',
									'cart-rebound'
								)}
							</p>
							<Combobox
								ariaLabel={__('Coupon', 'cart-rebound')}
								placeholder={__('No coupon', 'cart-rebound')}
								value={form.coupon}
								onChange={(next) => {
									setField('coupon', next);
								}}
								options={[
									{
										value: '',
										label: __('No coupon', 'cart-rebound'),
									},
									...(coupons ?? []).map((coupon) => ({
										value: coupon.code,
										label:
											coupon.description !== ''
												? `${coupon.code} — ${coupon.description}`
												: coupon.code,
									})),
									...(form.coupon !== '' &&
									!(coupons ?? []).some(
										(coupon) => coupon.code === form.coupon
									)
										? [
												{
													value: form.coupon,
													label: form.coupon,
												},
											]
										: []),
								]}
							/>
						</div>
					</div>

					<div className="cr-savebar is-sticky">
						<button
							type="button"
							className="cr-btn is-primary"
							onClick={onSave}
							disabled={busy || (!isNew && !dirty)}
						>
							{saveLabel}
						</button>
						<span className="cr-savebar__state">
							{dirty
								? __('Unsaved changes', 'cart-rebound')
								: __('All changes saved', 'cart-rebound')}
						</span>
						<input
							type="email"
							className="cr-input"
							style={{ maxWidth: 200 }}
							placeholder={__('you@example.com', 'cart-rebound')}
							value={testEmail}
							onChange={(event) => {
								setTestEmail(event.target.value);
							}}
							aria-label={__(
								'Address to send a test email to',
								'cart-rebound'
							)}
						/>
						<button
							type="button"
							className="cr-btn is-ghost"
							onClick={onTest}
							disabled={test.isPending || testEmail === ''}
						>
							{test.isPending
								? __('Sending…', 'cart-rebound')
								: __('Send test', 'cart-rebound')}
						</button>
						<span className="cr-savebar__spacer" />
						<button
							type="button"
							className="cr-btn is-danger"
							onClick={onDelete}
							disabled={
								remove.isPending || (!isNew && form.is_default)
							}
							title={
								!isNew && form.is_default
									? __(
											'Set another template as default before deleting this one',
											'cart-rebound'
										)
									: undefined
							}
						>
							{isNew
								? __('Discard', 'cart-rebound')
								: __('Delete', 'cart-rebound')}
						</button>
					</div>
				</section>
			</div>

			{/* Backdrop click-to-close is a mouse nicety; Esc is handled natively. */}
			{/* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions, jsx-a11y/click-events-have-key-events */}
			<dialog
				ref={previewRef}
				className="cr-dialog is-wide"
				aria-labelledby="cr-preview-title"
				onClose={() => {
					setPreviewData(null);
				}}
				onClick={(event) => {
					if (event.target === previewRef.current) {
						setPreviewData(null);
					}
				}}
			>
				<div className="cr-dialog__body cr-preview">
					<h2 id="cr-preview-title" className="cr-dialog__title">
						{__('Email preview', 'cart-rebound')}
					</h2>

					<div className="cr-preview__mail">
						<div className="cr-preview__mailhead">
							<span
								className="cr-preview__avatar"
								aria-hidden="true"
							>
								{(
									form.from_name.trim()[0] ?? 'S'
								).toUpperCase()}
							</span>
							<div className="cr-preview__meta">
								<p className="cr-preview__from">
									{form.from_name.trim() !== ''
										? form.from_name
										: __('Your store', 'cart-rebound')}
									{form.from_email.trim() !== '' && (
										<span className="cr-preview__addr">
											{`<${form.from_email}>`}
										</span>
									)}
								</p>
								<p className="cr-preview__subjectline">
									{previewData?.subject !== ''
										? previewData?.subject
										: __('(no subject)', 'cart-rebound')}
								</p>
							</div>
							<span className="cr-preview__chip">
								{__('To: shopper', 'cart-rebound')}
							</span>
						</div>
						<iframe
							className="cr-preview__frame"
							title={__('Email preview', 'cart-rebound')}
							sandbox=""
							srcDoc={previewData?.html ?? ''}
						/>
					</div>

					<p className="cr-field__hint">
						{__(
							'Rendered with sample data (name “Jordan”, two demo items).',
							'cart-rebound'
						)}
					</p>
					<div className="cr-dialog__actions">
						<button
							type="button"
							className="cr-btn is-ghost"
							onClick={() => {
								setPreviewData(null);
							}}
						>
							{__('Close', 'cart-rebound')}
						</button>
					</div>
				</div>
			</dialog>
		</div>
	);
};
