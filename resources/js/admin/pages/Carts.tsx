/**
 * Carts page — filterable, sortable, paginated list of tracked carts with
 * per-row actions (change status, mark recovered, send email, delete) and bulk
 * actions over a multi-row selection.
 */
import { useEffect, useMemo, useState } from 'react';
import {
	useBulkCarts,
	useCarts,
	useDeleteCart,
	useMarkRecovered,
	useOrders,
	useSendEmail,
	useUpdateStatus,
} from '../hooks/useApi';
import type { Cart, Order, SortOrder } from '../types/api';

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
const KNOWN_STATUSES = new Set(CHANGE_STATUSES);
const PER_PAGE = 20;
const COLUMN_COUNT = 8;

type Feedback = { type: 'success' | 'error'; message: string };

// Column key → the backend sort column it maps to (omitted = not sortable).
const SORTABLE = {
	email: 'email',
	items: 'items_count',
	total: 'cart_total',
	status: 'status',
	activity: 'last_activity',
	order: 'order_id',
} as const;

const StatusBadge = ({ status }: { status: string }) => (
	<span
		className={
			KNOWN_STATUSES.has(status) ? `cr-badge is-${status}` : 'cr-badge'
		}
	>
		{status}
	</span>
);

const Dash = () => <span className="cr-muted">—</span>;

const orderLabel = (order: Order): string => {
	const who = order.email !== '' ? order.email : 'guest';

	return `#${order.number} · ${who} · ${order.total.toFixed(2)} ${order.currency}`;
};

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
	const glyph = sort.order === 'asc' ? '↑' : '↓';
	const arrow = active ? glyph : '';

	return (
		<th
			aria-sort={active ? direction : 'none'}
			style={align === 'right' ? { textAlign: 'right' } : undefined}
		>
			<button
				type="button"
				className={`cr-sort${active ? ' is-active' : ''}`}
				onClick={() => {
					onSort(column);
				}}
				aria-label={
					active
						? `Sort by ${label}, currently ${direction}`
						: `Sort by ${label}`
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
	orders,
	selected,
	onToggle,
	notify,
}: {
	cart: Cart;
	orders: Order[];
	selected: boolean;
	onToggle: (id: number, checked: boolean) => void;
	notify: (feedback: Feedback) => void;
}) => {
	const [pickedOrder, setPickedOrder] = useState('');
	const [customOrder, setCustomOrder] = useState('');
	const remove = useDeleteCart();
	const mark = useMarkRecovered();
	const status = useUpdateStatus();
	const email = useSendEmail();

	const effectiveOrderId =
		Number.parseInt(customOrder, 10) > 0
			? Number.parseInt(customOrder, 10)
			: Number.parseInt(pickedOrder, 10);

	const onMark = () => {
		if (!(effectiveOrderId > 0)) {
			notify({
				type: 'error',
				message: 'Choose or type an order ID first.',
			});

			return;
		}

		mark.mutate(
			{ id: cart.id, order_id: effectiveOrderId },
			{
				onSuccess: () => {
					setPickedOrder('');
					setCustomOrder('');
					notify({
						type: 'success',
						message: 'Cart marked recovered.',
					});
				},
				onError: (error: unknown) => {
					notify({ type: 'error', message: messageOf(error) });
				},
			}
		);
	};

	const onStatusChange = (next: string) => {
		status.mutate(
			{ id: cart.id, status: next },
			{
				onSuccess: () => {
					notify({
						type: 'success',
						message: `Status set to ${next}.`,
					});
				},
				onError: (error: unknown) => {
					notify({ type: 'error', message: messageOf(error) });
				},
			}
		);
	};

	const onSend = () => {
		email.mutate(cart.id, {
			onSuccess: () => {
				notify({ type: 'success', message: 'Recovery email sent.' });
			},
			onError: (error: unknown) => {
				notify({ type: 'error', message: messageOf(error) });
			},
		});
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
					aria-label={`Select cart ${cart.id}`}
					onChange={(event) => {
						onToggle(cart.id, event.target.checked);
					}}
				/>
			</td>
			<td>{cart.email !== '' ? cart.email : <Dash />}</td>
			<td>{cart.items_count}</td>
			<td className="cr-money" style={{ textAlign: 'right' }}>
				{cart.cart_total.toFixed(2)}
			</td>
			<td>
				<div className="cr-status-cell">
					<StatusBadge status={cart.status} />
					<select
						className="cr-select is-compact is-xs"
						aria-label="Change status"
						value={cart.status}
						disabled={status.isPending}
						onChange={(event) => {
							onStatusChange(event.target.value);
						}}
					>
						{CHANGE_STATUSES.map((value) => (
							<option key={value} value={value}>
								{value}
							</option>
						))}
					</select>
				</div>
			</td>
			<td className="cr-muted">{cart.last_activity}</td>
			<td style={{ textAlign: 'right' }}>
				{cart.order_id > 0 ? `#${cart.order_id}` : <Dash />}
			</td>
			<td>
				<div className="cr-row-actions">
					{cart.order_id === 0 && (
						<div className="cr-recover">
							<select
								className="cr-select is-compact is-xs"
								aria-label="Pick an order to mark recovered"
								value={pickedOrder}
								onChange={(event) => {
									setPickedOrder(event.target.value);
								}}
							>
								<option value="">Order…</option>
								{orders.map((order) => (
									<option key={order.id} value={order.id}>
										{orderLabel(order)}
									</option>
								))}
							</select>
							<input
								className="cr-input is-compact is-xs"
								style={{ width: 78 }}
								type="number"
								min={1}
								value={customOrder}
								placeholder="or ID"
								aria-label="Custom order ID"
								onChange={(event) => {
									setCustomOrder(event.target.value);
								}}
							/>
							<button
								type="button"
								className="cr-btn is-ghost is-sm"
								onClick={onMark}
								disabled={mark.isPending}
							>
								Mark recovered
							</button>
						</div>
					)}
					<button
						type="button"
						className="cr-btn is-ghost is-sm"
						onClick={onSend}
						disabled={
							email.isPending ||
							cart.email === '' ||
							cart.order_id > 0
						}
						title={emailButtonTitle(cart)}
					>
						{email.isPending ? 'Sending…' : 'Send email'}
					</button>
					<button
						type="button"
						className="cr-btn is-danger is-sm"
						onClick={onDelete}
						disabled={remove.isPending}
					>
						Delete
					</button>
				</div>
			</td>
		</tr>
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
		{Array.from({ length: 6 }, (_, row) => (
			<tr key={row}>
				{Array.from({ length: COLUMN_COUNT }, (__, col) => (
					<td key={col}>
						<div
							className="cr-skeleton"
							style={{
								height: 14,
								width: skeletonWidth(col),
							}}
						/>
					</td>
				))}
			</tr>
		))}
	</>
);

const messageOf = (error: unknown): string =>
	error instanceof Error ? error.message : 'Something went wrong.';

const emailButtonTitle = (cart: Cart): string => {
	if (cart.order_id > 0) {
		return 'This cart already converted to an order';
	}

	if (cart.email === '') {
		return 'No email captured for this cart';
	}

	return 'Send the recovery email now';
};

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

	const { data, isLoading, isError } = useCarts({
		status,
		email: '',
		page,
		per_page: PER_PAGE,
		orderby: sort.by,
		order: sort.order,
	});
	const { data: orders } = useOrders();
	const bulk = useBulkCarts();

	const items = useMemo(() => data?.items ?? [], [data]);

	// A selection only makes sense for rows currently on screen. Prune it to the
	// visible ids on every list change — a filter/page/sort switch, or a refetch
	// after a row action removed or moved rows — so the count, the select-all
	// state, and bulk actions never operate on rows that are no longer shown.
	useEffect(() => {
		const visible = new Set(items.map((cart) => cart.id));

		setSelected((current) => {
			const next = new Set([...current].filter((id) => visible.has(id)));

			return next.size === current.size ? current : next;
		});
	}, [items]);

	// Keep the bulk "Set status" select empty whenever nothing is selected, so a
	// re-opened bulk bar never shows a stale choice.
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
								? `Deleted ${affected} cart${affected === 1 ? '' : 's'}.`
								: `Updated ${affected} cart${affected === 1 ? '' : 's'}.`,
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
		if (window.confirm(`Delete ${selected.size} selected cart(s)?`)) {
			runBulk({ action: 'delete' });
		}
	};

	return (
		<div>
			<div className="cr-toolbar">
				<span className="cr-toolbar__label">Status</span>
				<select
					className="cr-select is-compact"
					aria-label="Filter carts by status"
					value={status}
					onChange={(event) => {
						setStatus(event.target.value);
						setPage(1);
					}}
				>
					{FILTER_STATUSES.map((value) => (
						<option
							key={value === '' ? 'all' : value}
							value={value}
						>
							{value === '' ? 'All statuses' : value}
						</option>
					))}
				</select>
				<span className="cr-toolbar__spacer" />
				{data && (
					<span className="cr-toolbar__label">
						{data.total} cart{data.total === 1 ? '' : 's'}
					</span>
				)}
			</div>

			{feedback && (
				<div
					className={`cr-notice is-${feedback.type === 'success' ? 'success' : 'error'}`}
					role="status"
					style={{ marginBottom: 12 }}
				>
					{feedback.message}
				</div>
			)}

			{selected.size > 0 && (
				<div className="cr-bulkbar">
					<span className="cr-bulkbar__count">
						{selected.size} selected
					</span>
					<select
						className="cr-select is-compact"
						aria-label="Set status for selected carts"
						value={bulkStatus}
						disabled={bulk.isPending}
						onChange={(event) => {
							const next = event.target.value;

							setBulkStatus(next);

							if (next !== '') {
								runBulk({ action: 'status', status: next });
							}
						}}
					>
						<option value="">Set status…</option>
						{CHANGE_STATUSES.map((value) => (
							<option key={value} value={value}>
								{value}
							</option>
						))}
					</select>
					<button
						type="button"
						className="cr-btn is-danger is-sm"
						onClick={onBulkDelete}
						disabled={bulk.isPending}
					>
						Delete selected
					</button>
					<button
						type="button"
						className="cr-btn is-ghost is-sm"
						onClick={() => {
							setSelected(new Set());
						}}
						disabled={bulk.isPending}
					>
						Clear
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
						Could not load carts.
					</div>
				)}

				{isEmpty && (
					<div className="cr-empty">
						<p className="cr-empty__title">No carts yet</p>
						<p>
							Tracked carts appear here as shoppers add items and
							reach checkout.
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
												aria-label="Select all carts on this page"
												disabled={items.length === 0}
												onChange={(event) => {
													toggleAll(
														event.target.checked
													);
												}}
											/>
										</th>
										<SortHeader
											label="Email"
											column={SORTABLE.email}
											sort={sort}
											onSort={onSort}
										/>
										<SortHeader
											label="Items"
											column={SORTABLE.items}
											sort={sort}
											onSort={onSort}
										/>
										<SortHeader
											label="Total"
											column={SORTABLE.total}
											sort={sort}
											onSort={onSort}
											align="right"
										/>
										<SortHeader
											label="Status"
											column={SORTABLE.status}
											sort={sort}
											onSort={onSort}
										/>
										<SortHeader
											label="Last activity"
											column={SORTABLE.activity}
											sort={sort}
											onSort={onSort}
										/>
										<SortHeader
											label="Order"
											column={SORTABLE.order}
											sort={sort}
											onSort={onSort}
											align="right"
										/>
										<th>Actions</th>
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
												orders={orders ?? []}
												selected={selected.has(cart.id)}
												onToggle={toggleOne}
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
								Previous
							</button>
							<span>
								Page {page} of {totalPages}
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
								Next
							</button>
						</div>
					</>
				)}
			</div>
		</div>
	);
};
