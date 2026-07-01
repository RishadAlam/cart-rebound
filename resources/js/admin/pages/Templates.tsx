/**
 * Templates page — manage the recovery-email templates.
 *
 * A master/detail layout: pick a template on the left, edit it (rich-text body,
 * subject, sender, coupon) on the right. Exactly one template is the default —
 * the one automatic abandonment emails use.
 */
import { useEffect, useState, type ChangeEvent } from 'react';
import { RichTextEditor, type MergeTag } from '../components/RichTextEditor';
import {
	useCoupons,
	useCreateTemplate,
	useDeleteTemplate,
	useSetDefaultTemplate,
	useTemplates,
	useUpdateTemplate,
} from '../hooks/useApi';
import type { EmailTemplate } from '../types/api';

type Feedback = { type: 'success' | 'error'; message: string };

const BLANK: EmailTemplate = {
	id: '',
	name: '',
	subject: '',
	body: '',
	from_name: '',
	from_email: '',
	coupon: '',
	is_default: false,
};

const TAGS: MergeTag[] = [
	{ label: 'First name', value: '{first_name}' },
	{ label: 'Products', value: '{products}' },
	{ label: 'Recovery link', value: '{recovery_url}' },
	{ label: 'Coupon code', value: '{coupon_code}' },
];

const messageOf = (error: unknown): string =>
	error instanceof Error ? error.message : 'Something went wrong.';

export const Templates = () => {
	const { data: templates, isLoading } = useTemplates();
	const { data: coupons } = useCoupons();
	const create = useCreateTemplate();
	const update = useUpdateTemplate();
	const remove = useDeleteTemplate();
	const setDefault = useSetDefaultTemplate();

	const [selectedId, setSelectedId] = useState<string | null>(null);
	const [form, setForm] = useState<EmailTemplate>(BLANK);
	const [editorKey, setEditorKey] = useState(0);
	const [feedback, setFeedback] = useState<Feedback | null>(null);

	const isNew = selectedId === 'new';
	const busy = create.isPending || update.isPending;

	const load = (template: EmailTemplate, id: string) => {
		setSelectedId(id);
		setForm(template);
		setEditorKey((key) => key + 1);
	};

	// Keep a valid template selected: pick the default on first load, and
	// re-select after the current one disappears (e.g. was deleted, so the
	// refetched list no longer contains selectedId). Skips the "new" draft.
	useEffect(() => {
		if (!templates || selectedId === 'new') {
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
	}, [templates, selectedId]);

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

	const startNew = () => {
		load({ ...BLANK, name: 'New template' }, 'new');
	};

	const onSave = () => {
		if (form.name.trim() === '' || form.subject.trim() === '') {
			setFeedback({
				type: 'error',
				message: 'Name and subject are required.',
			});

			return;
		}

		const done = (saved: EmailTemplate, verb: string) => {
			load(saved, saved.id);
			setFeedback({ type: 'success', message: `Template ${verb}.` });
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
				},
				{
					onSuccess: (saved) => {
						done(saved, 'created');
					},
					onError,
				}
			);

			return;
		}

		update.mutate(form, {
			onSuccess: (saved) => {
				done(saved, 'saved');
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
					message: 'Default template set.',
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
		if (!window.confirm(`Delete the "${form.name}" template?`)) {
			return;
		}

		remove.mutate(form.id, {
			onSuccess: () => {
				setSelectedId(null);
				setFeedback({ type: 'success', message: 'Template deleted.' });
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

	let saveLabel = 'Save';

	if (busy) {
		saveLabel = 'Saving…';
	} else if (isNew) {
		saveLabel = 'Create template';
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
						<span>Templates</span>
						<button
							type="button"
							className="cr-btn is-ghost is-sm"
							onClick={startNew}
						>
							+ New
						</button>
					</div>
					{list.map((template) => (
						<button
							key={template.id}
							type="button"
							className={`cr-templates__item${
								selectedId === template.id ? ' is-active' : ''
							}`}
							onClick={() => {
								load(template, template.id);
							}}
						>
							<span className="cr-templates__name">
								{template.name !== ''
									? template.name
									: 'Untitled'}
							</span>
							{template.is_default && (
								<span className="cr-tag">Default</span>
							)}
						</button>
					))}
					{isNew && (
						<div className="cr-templates__item is-active">
							<span className="cr-templates__name">
								{form.name !== '' ? form.name : 'New template'}
							</span>
							<span className="cr-tag is-muted">Draft</span>
						</div>
					)}
				</aside>

				<section className="cr-templates__editor cr-card">
					<div className="cr-section">
						<div className="cr-templates__edithead">
							<h2 className="cr-section__title">
								{isNew ? 'New template' : 'Edit template'}
							</h2>
							{form.is_default ? (
								<span className="cr-tag">Default</span>
							) : (
								<button
									type="button"
									className="cr-btn is-ghost is-sm"
									onClick={onSetDefault}
									disabled={setDefault.isPending}
								>
									Set as default
								</button>
							)}
						</div>

						<div className="cr-field__grid">
							<div className="cr-field">
								<label
									htmlFor="cr-tpl-name"
									className="cr-field__label"
								>
									Template name
								</label>
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
									htmlFor="cr-tpl-coupon"
									className="cr-field__label"
								>
									Coupon
								</label>
								<select
									id="cr-tpl-coupon"
									className="cr-select"
									value={form.coupon}
									onChange={(event) => {
										setField('coupon', event.target.value);
									}}
								>
									<option value="">No coupon</option>
									{(coupons ?? []).map((coupon) => (
										<option
											key={coupon.code}
											value={coupon.code}
										>
											{coupon.code}
										</option>
									))}
									{form.coupon !== '' &&
										!(coupons ?? []).some(
											(coupon) =>
												coupon.code === form.coupon
										) && (
											<option value={form.coupon}>
												{form.coupon}
											</option>
										)}
								</select>
							</div>
						</div>

						<div className="cr-field">
							<label
								htmlFor="cr-tpl-subject"
								className="cr-field__label"
							>
								Subject
							</label>
							<input
								id="cr-tpl-subject"
								className="cr-input"
								type="text"
								value={form.subject}
								onChange={onText('subject')}
							/>
						</div>

						<div className="cr-field">
							<span className="cr-field__label">Body</span>
							<RichTextEditor
								key={editorKey}
								value={form.body}
								tags={TAGS}
								onChange={(html) => {
									setField('body', html);
								}}
							/>
							<p className="cr-field__hint">
								Tokens: {'{first_name}'}, {'{products}'},{' '}
								{'{recovery_url}'}, {'{coupon_code}'}. A
								“Complete your order” button is added
								automatically.
							</p>
						</div>

						<div className="cr-field__grid">
							<div className="cr-field">
								<label
									htmlFor="cr-tpl-fromname"
									className="cr-field__label"
								>
									From name
								</label>
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
									From email
								</label>
								<input
									id="cr-tpl-fromemail"
									className="cr-input"
									type="email"
									value={form.from_email}
									onChange={onText('from_email')}
								/>
							</div>
						</div>
					</div>

					<div className="cr-savebar">
						<button
							type="button"
							className="cr-btn is-primary"
							onClick={onSave}
							disabled={busy}
						>
							{saveLabel}
						</button>
						<button
							type="button"
							className="cr-btn is-danger"
							onClick={onDelete}
							disabled={
								remove.isPending || (!isNew && form.is_default)
							}
							title={
								!isNew && form.is_default
									? 'Set another template as default before deleting this one'
									: undefined
							}
						>
							{isNew ? 'Discard' : 'Delete'}
						</button>
					</div>
				</section>
			</div>
		</div>
	);
};
