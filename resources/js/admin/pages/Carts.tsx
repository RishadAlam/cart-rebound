/**
 * Carts page — filterable, sortable, paginated list of tracked carts.
 *
 * Each row keeps a calm surface: an inline color-coded status select and three
 * icon actions (recover, send email, delete). The heavier "mark recovered"
 * order picker lives in a native <dialog> so it escapes the table's horizontal
 * scroll container instead of being clipped by it.
 */
import { useEffect, useMemo, useRef, useState } from 'react';
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import { Combobox } from '../components/Combobox';
import {
	useBulkCarts,
	useCarts,
	useDeleteCart,
	useMarkRecovered,
	useOrders,
	useSendEmail,
	useTemplates,
	useUpdateStatus,
} from '../hooks/useApi';
import type { Cart, EmailTemplate, Order, SortOrder } from '../types/api';

const FILTER_STATUSES = [
	'',
	'active',
	'abandoned',
	'recovered',
	'completed',
	'lost',
];
const CHANGE_STATUSES = [
	'active',
	'abandoned',
	'recovered',
	'completed',
	'lost',
];
const PER_PAGE = 20;
const COLUMN_COUNT = 8;

type Feedback = { type: 'success' | 'error'; message: string };

// Column key → the backend sort column it maps to.
const SORTABLE = {
	email: 'email',
	items: 'items_count',
	total: 'cart_total',
	status: 'status',
	activity: 'last_activity',
	order: 'order_id',
} as const;

const messageOf = (error: unknown): string =>
	error instanceof Error
		? error.message
		: __('Something went wrong.', 'cart-rebound');

const statusLabel = (status: string): string => {
	switch (status) {
		case 'active':
			return _x('Active', 'cart status', 'cart-rebound');
		case 'abandoned':
			return _x('Abandoned', 'cart status', 'cart-rebound');
		case 'recovered':
			return _x('Recovered', 'cart status', 'cart-rebound');
		case 'completed':
			return _x('Completed', 'cart status', 'cart-rebound');
		case 'lost':
			return _x('Lost', 'cart status', 'cart-rebound');
		default:
			return status;
	}
};

const orderLabel = (order: Order): string => {
	const who = order.email !== '' ? order.email : __('guest', 'cart-rebound');

	return sprintf(
		/* translators: 1: order number, 2: customer, 3: total, 4: currency code. */
		__('#%1$s · %2$s · %3$s %4$s', 'cart-rebound'),
		order.number,
		who,
		order.total.toFixed(2),
		order.currency
	);
};

const emailButtonTitle = (cart: Cart): string => {
	if (cart.order_id > 0) {
		return __('This cart already converted to an order', 'cart-rebound');
	}

	if (cart.email === '') {
		return __('No email captured for this cart', 'cart-rebound');
	}

	if (cart.items_count <= 0) {
		return __('This cart has no items to recover', 'cart-rebound');
	}

	return __('Send the recovery email now', 'cart-rebound');
};

const templateLabel = (template: EmailTemplate): string => {
	const name =
		template.name !== '' ? template.name : __('Untitled', 'cart-rebound');

	if (!template.is_default) {
		return name;
	}

	return sprintf(
		/* translators: %s: template name. */
		__('%s (default)', 'cart-rebound'),
		name
	);
};

const RecoverIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<path
			d="M6.5 9.5 9.5 6.5M6.9 4.4l.8-.8a2.7 2.7 0 0 1 3.8 3.8l-.8.8M9.1 11.6l-.8.8a2.7 2.7 0 0 1-3.8-3.8l.8-.8"
			stroke="currentColor"
			strokeWidth="1.3"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const MailIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<rect
			x="2.5"
			y="3.5"
			width="11"
			height="9"
			rx="1.6"
			stroke="currentColor"
			strokeWidth="1.3"
		/>
		<path
			d="m3.2 4.8 4.8 3.5 4.8-3.5"
			stroke="currentColor"
			strokeWidth="1.3"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const TrashIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<path
			d="M3.4 4.7h9.2M6.4 4.7V3.4a.9.9 0 0 1 .9-.9h1.4a.9.9 0 0 1 .9.9v1.3M5.2 4.7l.5 7.8a1 1 0 0 0 1 .9h2.6a1 1 0 0 0 1-.9l.5-7.8"
			stroke="currentColor"
			strokeWidth="1.3"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const Dash = () => <span className="cr-muted">—</span>;

const Spinner = ({ size = 15 }: { size?: number }) => (
	<svg
		className="cr-spinner"
		width={size}
		height={size}
		viewBox="0 0 16 16"
		aria-hidden="true"
	>
		<circle
			cx="8"
			cy="8"
			r="6"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			opacity="0.25"
		/>
		<path
			d="M8 2a6 6 0 0 1 6 6"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
		/>
	</svg>
);

const StatusSelect = ({
	cart,
	pending,
	onChange,
}: {
	cart: Cart;
	pending: boolean;
	onChange: (next: string) => void;
}) => (
	<span className="cr-status-wrap">
		<Combobox
			compact
			pill
			tone={cart.status}
			ariaLabel={sprintf(
				/* translators: %s: current cart status. */
				__('Status: %s. Change it', 'cart-rebound'),
				statusLabel(cart.status)
			)}
			value={cart.status}
			disabled={pending}
			options={CHANGE_STATUSES.map((option) => ({
				value: option,
				label: statusLabel(option),
			}))}
			onChange={onChange}
		/>
		{pending && <Spinner size={14} />}
	</span>
);

const SortHeader = ({
	label,
	column,
	sort,
	onSort,
	align,
}: {
	label: string;
	column: string;
	sort: { by: string; order: SortOrder };
	onSort: (column: string) => void;
	align?: 'right';
}) => {
	const active = sort.by === column;
	const direction = sort.order === 'asc' ? 'ascending' : 'descending';
	const directionLabel =
		sort.order === 'asc'
			? __('ascending', 'cart-rebound')
			: __('descending', 'cart-rebound');
	const glyph = sort.order === 'asc' ? '↑' : '↓';
	const arrow = active ? glyph : '';

	return (
		<th
			aria-sort={active ? direction : 'none'}
			style={align === 'right' ? { textAlign: 'right' } : undefined}
		>
			<button
				type="button"
				className={`cr-sort${active ? ' is-active' : ''}${
					align === 'right' ? ' is-right' : ''
				}`}
				onClick={() => {
					onSort(column);
				}}
				aria-label={
					active
						? sprintf(
								/* translators: 1: column label, 2: sort direction. */
								__(
									'Sort by %1$s, currently %2$s',
									'cart-rebound'
								),
								label,
								directionLabel
							)
						: sprintf(
								/* translators: %s: column label. */
								__('Sort by %s', 'cart-rebound'),
								label
							)
				}
			>
				{label}
				<span className="cr-sort__arrow" aria-hidden="true">
					{arrow}
				</span>
			</button>
		</th>
	);
};

const CartRow = ({
	cart,
	selected,
	onToggle,
	onRecover,
	onSendEmail,
	notify,
}: {
	cart: Cart;
	selected: boolean;
	onToggle: (id: number, checked: boolean) => void;
	onRecover: (cart: Cart) => void;
	onSendEmail: (cart: Cart) => void;
	notify: (feedback: Feedback) => void;
}) => {
	const remove = useDeleteCart();
	const status = useUpdateStatus();

	const onStatusChange = (next: string) => {
		status.mutate(
			{ id: cart.id, status: next },
			{
				onSuccess: () => {
					notify({
						type: 'success',
						message: sprintf(
							/* translators: %s: cart status. */
							__('Status set to %s.', 'cart-rebound'),
							statusLabel(next)
						),
					});
				},
				onError: (error: unknown) => {
					notify({ type: 'error', message: messageOf(error) });
				},
			}
		);
	};

	const onDelete = () => {
		remove.mutate(cart.id, {
			onError: (error: unknown) => {
				notify({ type: 'error', message: messageOf(error) });
			},
		});
	};

	return (
		<tr className={selected ? 'is-selected' : undefined}>
			<td className="cr-check">
				<input
					type="checkbox"
					checked={selected}
					aria-label={
						/* translators: %d: cart ID. */
						sprintf(__('Select cart %d', 'cart-rebound'), cart.id)
					}
					onChange={(event) => {
						onToggle(cart.id, event.target.checked);
					}}
				/>
			</td>
			<td className="cr-cell-email">
				{cart.email !== '' ? cart.email : <Dash />}
			</td>
			<td>{cart.items_count}</td>
			<td className="cr-money" style={{ textAlign: 'right' }}>
				{cart.cart_total.toFixed(2)}
			</td>
			<td>
				<StatusSelect
					cart={cart}
					pending={status.isPending}
					onChange={onStatusChange}
				/>
			</td>
			<td className="cr-muted cr-nowrap">{cart.last_activity}</td>
			<td style={{ textAlign: 'right' }}>
				{cart.order_id > 0 ? `#${cart.order_id}` : <Dash />}
			</td>
			<td>
				<div className="cr-row-actions">
					{cart.order_id === 0 && (
						<button
							type="button"
							className="cr-iconbtn"
							onClick={() => {
								onRecover(cart);
							}}
							title={__('Mark recovered', 'cart-rebound')}
							aria-label={__(
								'Mark this cart recovered against an order',
								'cart-rebound'
							)}
						>
							<RecoverIcon />
						</button>
					)}
					<button
						type="button"
						className="cr-iconbtn"
						onClick={() => {
							onSendEmail(cart);
						}}
						disabled={
							cart.email === '' ||
							cart.items_count <= 0 ||
							cart.order_id > 0
						}
						title={emailButtonTitle(cart)}
						aria-label={__('Send recovery email', 'cart-rebound')}
					>
						<MailIcon />
					</button>
					<button
						type="button"
						className="cr-iconbtn is-danger"
						onClick={onDelete}
						disabled={remove.isPending}
						title={__('Delete cart', 'cart-rebound')}
						aria-label={__('Delete this cart', 'cart-rebound')}
					>
						{remove.isPending ? <Spinner /> : <TrashIcon />}
					</button>
				</div>
			</td>
		</tr>
	);
};

const RecoverDialog = ({
	cart,
	orders,
	onClose,
	notify,
}: {
	cart: Cart | null;
	orders: Order[];
	onClose: () => void;
	notify: (feedback: Feedback) => void;
}) => {
	const ref = useRef<HTMLDialogElement>(null);
	const [picked, setPicked] = useState('');
	const [custom, setCustom] = useState('');
	const mark = useMarkRecovered();

	useEffect(() => {
		const el = ref.current;

		if (!el) {
			return;
		}

		if (cart) {
			setPicked('');
			setCustom('');

			if (!el.open) {
				el.showModal();
			}
		} else if (el.open) {
			el.close();
		}
	}, [cart]);

	const parsedCustom = Number.parseInt(custom, 10);
	const parsedPicked = Number.parseInt(picked, 10);
	const orderId = parsedCustom > 0 ? parsedCustom : parsedPicked;
	const canSubmit = orderId > 0 && !mark.isPending;

	const confirm = () => {
		if (!cart || !(orderId > 0)) {
			return;
		}

		mark.mutate(
			{ id: cart.id, order_id: orderId },
			{
				onSuccess: () => {
					notify({
						type: 'success',
						message: __('Cart marked recovered.', 'cart-rebound'),
					});
					onClose();
				},
				onError: (error: unknown) => {
					notify({ type: 'error', message: messageOf(error) });
				},
			}
		);
	};

	return (
		// Backdrop click-to-close is a mouse nicety; keyboard dismissal (Esc) is
		// handled natively by <dialog> via onClose, so the a11y interaction rules
		// don't apply here.
		// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions, jsx-a11y/click-events-have-key-events
		<dialog
			ref={ref}
			className="cr-dialog"
			aria-labelledby="cr-recover-title"
			onClose={onClose}
			onClick={(event) => {
				if (event.target === ref.current) {
					onClose();
				}
			}}
		>
			<div className="cr-dialog__body">
				<h2 id="cr-recover-title" className="cr-dialog__title">
					{__('Mark cart recovered', 'cart-rebound')}
				</h2>
				<p className="cr-dialog__desc">
					{sprintf(
						/* translators: %s: customer email address or "this cart". */
						__(
							'Link %s to the order it converted to so the recovered revenue is attributed.',
							'cart-rebound'
						),
						cart && cart.email !== ''
							? cart.email
							: __('this cart', 'cart-rebound')
					)}
				</p>

				<div className="cr-field">
					<span className="cr-field__label">
						{__('Recent order', 'cart-rebound')}
					</span>
					<Combobox
						ariaLabel={__('Recent order', 'cart-rebound')}
						placeholder={__('Select an order…', 'cart-rebound')}
						value={picked}
						onChange={setPicked}
						options={[
							{
								value: '',
								label: __('Select an order…', 'cart-rebound'),
							},
							...orders.map((order) => ({
								value: String(order.id),
								label: orderLabel(order),
							})),
						]}
					/>
				</div>

				<div className="cr-field">
					<label
						htmlFor="cr-recover-custom"
						className="cr-field__label"
					>
						{__('Or enter an order ID', 'cart-rebound')}
					</label>
					<input
						id="cr-recover-custom"
						className="cr-input"
						type="number"
						min={1}
						value={custom}
						placeholder={__('e.g. 1024', 'cart-rebound')}
						onChange={(event) => {
							setCustom(event.target.value);
						}}
					/>
				</div>

				<div className="cr-dialog__actions">
					<button
						type="button"
						className="cr-btn is-ghost"
						onClick={onClose}
						disabled={mark.isPending}
					>
						{__('Cancel', 'cart-rebound')}
					</button>
					<button
						type="button"
						className="cr-btn is-primary"
						onClick={confirm}
						disabled={!canSubmit}
					>
						{mark.isPending && <Spinner size={14} />}
						{mark.isPending
							? __('Linking…', 'cart-rebound')
							: __('Mark recovered', 'cart-rebound')}
					</button>
				</div>
			</div>
		</dialog>
	);
};

const SendDialog = ({
	cart,
	templates,
	onClose,
	notify,
}: {
	cart: Cart | null;
	templates: EmailTemplate[];
	onClose: () => void;
	notify: (feedback: Feedback) => void;
}) => {
	const ref = useRef<HTMLDialogElement>(null);
	const [templateId, setTemplateId] = useState('');
	const send = useSendEmail();

	useEffect(() => {
		const el = ref.current;

		if (!el) {
			return;
		}

		if (cart) {
			const initial =
				templates.find((template) => template.is_default) ??
				templates[0];
			setTemplateId(initial ? initial.id : '');

			if (!el.open) {
				el.showModal();
			}
		} else if (el.open) {
			el.close();
		}
		// Only re-run when the target cart changes, not on template refetch.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [cart]);

	const confirm = () => {
		if (!cart) {
			return;
		}

		send.mutate(
			templateId !== ''
				? { id: cart.id, template_id: templateId }
				: { id: cart.id },
			{
				onSuccess: () => {
					notify({
						type: 'success',
						message: __('Recovery email sent.', 'cart-rebound'),
					});
					onClose();
				},
				onError: (error: unknown) => {
					notify({ type: 'error', message: messageOf(error) });
				},
			}
		);
	};

	return (
		// Backdrop click-to-close is a mouse nicety; Esc is handled natively.
		// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions, jsx-a11y/click-events-have-key-events
		<dialog
			ref={ref}
			className="cr-dialog"
			aria-labelledby="cr-send-title"
			onClose={onClose}
			onClick={(event) => {
				if (event.target === ref.current) {
					onClose();
				}
			}}
		>
			<div className="cr-dialog__body">
				<h2 id="cr-send-title" className="cr-dialog__title">
					{__('Send recovery email', 'cart-rebound')}
				</h2>
				<p className="cr-dialog__desc">
					{sprintf(
						/* translators: %s: customer email address or "this shopper". */
						__(
							'Email %s now, using the template you choose.',
							'cart-rebound'
						),
						cart && cart.email !== ''
							? cart.email
							: __('this shopper', 'cart-rebound')
					)}
				</p>

				<div className="cr-field">
					<span className="cr-field__label">
						{__('Template', 'cart-rebound')}
					</span>
					<Combobox
						ariaLabel={__('Template', 'cart-rebound')}
						value={templateId}
						onChange={setTemplateId}
						options={templates.map((template) => ({
							value: template.id,
							label: templateLabel(template),
						}))}
					/>
				</div>

				<div className="cr-dialog__actions">
					<button
						type="button"
						className="cr-btn is-ghost"
						onClick={onClose}
						disabled={send.isPending}
					>
						{__('Cancel', 'cart-rebound')}
					</button>
					<button
						type="button"
						className="cr-btn is-primary"
						onClick={confirm}
						disabled={send.isPending}
					>
						{send.isPending && <Spinner size={14} />}
						{send.isPending
							? __('Sending…', 'cart-rebound')
							: __('Send email', 'cart-rebound')}
					</button>
				</div>
			</div>
		</dialog>
	);
};

const skeletonWidth = (col: number): number | string => {
	if (col === 0) {
		return 16;
	}

	return col === 1 ? '80%' : '50%';
};

const SkeletonRows = () => (
	<>
		{Array.from({ length: 6 }, (_unusedRowValue, row) => (
			<tr key={row}>
				{Array.from(
					{ length: COLUMN_COUNT },
					(_unusedColumnValue, col) => (
						<td key={col}>
							<div
								className="cr-skeleton"
								style={{
									height: 14,
									width: skeletonWidth(col),
								}}
							/>
						</td>
					)
				)}
			</tr>
		))}
	</>
);

export const Carts = () => {
	const [status, setStatus] = useState('');
	const [page, setPage] = useState(1);
	const [sort, setSort] = useState<{ by: string; order: SortOrder }>({
		by: 'last_activity',
		order: 'desc',
	});
	const [selected, setSelected] = useState<Set<number>>(new Set());
	const [bulkStatus, setBulkStatus] = useState('');
	const [feedback, setFeedback] = useState<Feedback | null>(null);
	const [recoverCart, setRecoverCart] = useState<Cart | null>(null);
	const [sendCart, setSendCart] = useState<Cart | null>(null);

	const { data, isLoading, isFetching, isError } = useCarts({
		status,
		email: '',
		page,
		per_page: PER_PAGE,
		orderby: sort.by,
		order: sort.order,
	});
	const { data: orders } = useOrders();
	const { data: templates } = useTemplates();
	const bulk = useBulkCarts();

	const items = useMemo(() => data?.items ?? [], [data]);

	// A selection only makes sense for rows currently on screen, so prune it to
	// the visible ids on every list change — filter/page/sort switch, or a
	// refetch after a row action removed or moved rows.
	useEffect(() => {
		const visible = new Set(items.map((cart) => cart.id));

		setSelected((current) => {
			const next = new Set([...current].filter((id) => visible.has(id)));

			return next.size === current.size ? current : next;
		});
	}, [items]);

	useEffect(() => {
		if (selected.size === 0) {
			setBulkStatus('');
		}
	}, [selected]);

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

	const totalPages = data
		? Math.max(1, Math.ceil(data.total / data.per_page))
		: 1;
	const isEmpty = !isLoading && !isError && !!data && items.length === 0;
	const allChecked = items.length > 0 && selected.size === items.length;

	const toggleAll = (checked: boolean) => {
		setSelected(
			checked ? new Set(items.map((cart) => cart.id)) : new Set()
		);
	};

	const toggleOne = (id: number, checked: boolean) => {
		setSelected((current) => {
			const next = new Set(current);

			if (checked) {
				next.add(id);
			} else {
				next.delete(id);
			}

			return next;
		});
	};

	const onSort = (column: string) => {
		setPage(1);
		setSort((current) =>
			current.by === column
				? {
						by: column,
						order: current.order === 'asc' ? 'desc' : 'asc',
					}
				: { by: column, order: 'asc' }
		);
	};

	const runBulk = (
		payload: { action: 'delete' } | { action: 'status'; status: string }
	) => {
		const ids = Array.from(selected);

		bulk.mutate(
			{ ...payload, ids },
			{
				onSuccess: (affected) => {
					setSelected(new Set());
					setBulkStatus('');
					setFeedback({
						type: 'success',
						message:
							payload.action === 'delete'
								? sprintf(
										/* translators: %d: number of deleted carts. */
										_n(
											'Deleted %d cart.',
											'Deleted %d carts.',
											affected,
											'cart-rebound'
										),
										affected
									)
								: sprintf(
										/* translators: %d: number of updated carts. */
										_n(
											'Updated %d cart.',
											'Updated %d carts.',
											affected,
											'cart-rebound'
										),
										affected
									),
					});
				},
				onError: (error: unknown) => {
					// Reset the select so the same status can be retried (an
					// unchanged <option> emits no onChange event).
					setBulkStatus('');
					setFeedback({ type: 'error', message: messageOf(error) });
				},
			}
		);
	};

	const onBulkDelete = () => {
		// eslint-disable-next-line no-alert
		const confirmed = window.confirm(
			sprintf(
				/* translators: %d: number of selected carts. */
				_n(
					'Delete %d selected cart?',
					'Delete %d selected carts?',
					selected.size,
					'cart-rebound'
				),
				selected.size
			)
		);

		if (confirmed) {
			runBulk({ action: 'delete' });
		}
	};

	return (
		<div>
			<div className="cr-toolbar">
				<span className="cr-toolbar__label">
					{__('Status', 'cart-rebound')}
				</span>
				<Combobox
					compact
					ariaLabel={__('Filter carts by status', 'cart-rebound')}
					value={status}
					options={FILTER_STATUSES.map((option) => ({
						value: option,
						label:
							option === ''
								? __('All statuses', 'cart-rebound')
								: statusLabel(option),
					}))}
					onChange={(next) => {
						setStatus(next);
						setPage(1);
					}}
				/>
				<span className="cr-toolbar__spacer" />
				{isFetching && !isLoading && (
					<span className="cr-toolbar__working">
						<Spinner size={14} />
						{__('Updating…', 'cart-rebound')}
					</span>
				)}
				{data && (
					<span className="cr-toolbar__label">
						{sprintf(
							/* translators: %d: total number of carts. */
							_n(
								'%d cart',
								'%d carts',
								data.total,
								'cart-rebound'
							),
							data.total
						)}
					</span>
				)}
			</div>

			{feedback && (
				<div
					className={`cr-notice is-${feedback.type}`}
					role="status"
					style={{ marginBottom: 12 }}
				>
					{feedback.message}
				</div>
			)}

			{selected.size > 0 && (
				<div className="cr-bulkbar">
					<span className="cr-bulkbar__count">
						{sprintf(
							/* translators: %d: number of selected carts. */
							__('%d selected', 'cart-rebound'),
							selected.size
						)}
					</span>
					{bulk.isPending && <Spinner size={14} />}
					<span className="cr-bulkbar__spacer" />
					<Combobox
						compact
						ariaLabel={__(
							'Set status for selected carts',
							'cart-rebound'
						)}
						placeholder={__('Set status…', 'cart-rebound')}
						value={bulkStatus}
						disabled={bulk.isPending}
						options={[
							{
								value: '',
								label: __('Set status…', 'cart-rebound'),
							},
							...CHANGE_STATUSES.map((option) => ({
								value: option,
								label: statusLabel(option),
							})),
						]}
						onChange={(next) => {
							setBulkStatus(next);

							if (next !== '') {
								runBulk({ action: 'status', status: next });
							}
						}}
					/>
					<button
						type="button"
						className="cr-btn is-danger is-sm"
						onClick={onBulkDelete}
						disabled={bulk.isPending}
					>
						{__('Delete', 'cart-rebound')}
					</button>
					<button
						type="button"
						className="cr-btn is-ghost is-sm"
						onClick={() => {
							setSelected(new Set());
						}}
						disabled={bulk.isPending}
					>
						{__('Clear', 'cart-rebound')}
					</button>
				</div>
			)}

			<div className="cr-card">
				{isError && (
					<div
						className="cr-notice is-error"
						role="alert"
						style={{ margin: 16 }}
					>
						{__('Could not load carts.', 'cart-rebound')}
					</div>
				)}

				{isEmpty && (
					<div className="cr-empty">
						<p className="cr-empty__title">
							{__('No carts yet', 'cart-rebound')}
						</p>
						<p>
							{__(
								'Tracked carts appear here as shoppers add items and reach checkout.',
								'cart-rebound'
							)}
						</p>
					</div>
				)}

				{!isError && !isEmpty && (
					<>
						<div className="cr-table-wrap">
							<table className="cr-table">
								<thead>
									<tr>
										<th className="cr-check">
											<input
												type="checkbox"
												checked={allChecked}
												aria-label={__(
													'Select all carts on this page',
													'cart-rebound'
												)}
												disabled={items.length === 0}
												onChange={(event) => {
													toggleAll(
														event.target.checked
													);
												}}
											/>
										</th>
										<SortHeader
											label={__('Email', 'cart-rebound')}
											column={SORTABLE.email}
											sort={sort}
											onSort={onSort}
										/>
										<SortHeader
											label={__('Items', 'cart-rebound')}
											column={SORTABLE.items}
											sort={sort}
											onSort={onSort}
										/>
										<SortHeader
											label={__('Total', 'cart-rebound')}
											column={SORTABLE.total}
											sort={sort}
											onSort={onSort}
											align="right"
										/>
										<SortHeader
											label={__('Status', 'cart-rebound')}
											column={SORTABLE.status}
											sort={sort}
											onSort={onSort}
										/>
										<SortHeader
											label={__(
												'Last activity',
												'cart-rebound'
											)}
											column={SORTABLE.activity}
											sort={sort}
											onSort={onSort}
										/>
										<SortHeader
											label={__('Order', 'cart-rebound')}
											column={SORTABLE.order}
											sort={sort}
											onSort={onSort}
											align="right"
										/>
										<th>{__('Actions', 'cart-rebound')}</th>
									</tr>
								</thead>
								<tbody>
									{isLoading ? (
										<SkeletonRows />
									) : (
										items.map((cart) => (
											<CartRow
												key={cart.id}
												cart={cart}
												selected={selected.has(cart.id)}
												onToggle={toggleOne}
												onRecover={setRecoverCart}
												onSendEmail={setSendCart}
												notify={setFeedback}
											/>
										))
									)}
								</tbody>
							</table>
						</div>

						<div className="cr-pagination">
							<button
								type="button"
								className="cr-btn is-ghost is-sm"
								disabled={page <= 1}
								onClick={() => {
									setPage((current) =>
										Math.max(1, current - 1)
									);
								}}
							>
								{__('Previous', 'cart-rebound')}
							</button>
							<span>
								{sprintf(
									/* translators: 1: current page, 2: total pages. */
									__('Page %1$d of %2$d', 'cart-rebound'),
									page,
									totalPages
								)}
							</span>
							<span className="cr-pagination__spacer" />
							<button
								type="button"
								className="cr-btn is-ghost is-sm"
								disabled={page >= totalPages}
								onClick={() => {
									setPage((current) =>
										Math.min(totalPages, current + 1)
									);
								}}
							>
								{__('Next', 'cart-rebound')}
							</button>
						</div>
					</>
				)}
			</div>

			<RecoverDialog
				cart={recoverCart}
				orders={orders ?? []}
				onClose={() => {
					setRecoverCart(null);
				}}
				notify={setFeedback}
			/>

			<SendDialog
				cart={sendCart}
				templates={templates ?? []}
				onClose={() => {
					setSendCart(null);
				}}
				notify={setFeedback}
			/>
		</div>
	);
};
