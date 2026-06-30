/**
 * Carts page — filterable, paginated list of tracked carts with row actions.
 */
import { useState } from 'react';
import { useCarts, useDeleteCart, useMarkRecovered } from '../hooks/useApi';
import type { Cart } from '../types/api';

const STATUSES = ['', 'active', 'abandoned', 'recovered', 'completed', 'lost'];
const KNOWN_STATUSES = new Set(STATUSES.filter((value) => value !== ''));
const PER_PAGE = 20;
const COLUMN_COUNT = 7;

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

const CartRow = ({ cart }: { cart: Cart }) => {
	const [orderId, setOrderId] = useState('');
	const remove = useDeleteCart();
	const mark = useMarkRecovered();

	const onMark = () => {
		const parsed = Number.parseInt(orderId, 10);

		if (parsed > 0) {
			mark.mutate({ id: cart.id, order_id: parsed });
		}
	};

	return (
		<tr>
			<td>{cart.email !== '' ? cart.email : <Dash />}</td>
			<td>{cart.items_count}</td>
			<td className="cr-money">{cart.cart_total.toFixed(2)}</td>
			<td>
				<StatusBadge status={cart.status} />
			</td>
			<td className="cr-muted">{cart.last_activity}</td>
			<td>{cart.order_id > 0 ? `#${cart.order_id}` : <Dash />}</td>
			<td>
				<div className="cr-row-actions">
					<input
						className="cr-input is-compact"
						style={{ width: 92 }}
						type="number"
						min={1}
						value={orderId}
						placeholder="Order ID"
						aria-label="Order ID to mark recovered"
						onChange={(event) => {
							setOrderId(event.target.value);
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
					<button
						type="button"
						className="cr-btn is-danger is-sm"
						onClick={() => {
							remove.mutate(cart.id);
						}}
						disabled={remove.isPending}
					>
						Delete
					</button>
				</div>
			</td>
		</tr>
	);
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
								width: col === 0 ? '80%' : '50%',
							}}
						/>
					</td>
				))}
			</tr>
		))}
	</>
);

export const Carts = () => {
	const [status, setStatus] = useState('');
	const [page, setPage] = useState(1);
	const { data, isLoading, isError } = useCarts({
		status,
		email: '',
		page,
		per_page: PER_PAGE,
	});

	const totalPages = data
		? Math.max(1, Math.ceil(data.total / data.per_page))
		: 1;
	const isEmpty = !isLoading && !isError && !!data && data.items.length === 0;

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
					{STATUSES.map((value) => (
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
										<th>Email</th>
										<th>Items</th>
										<th>Total</th>
										<th>Status</th>
										<th>Last activity</th>
										<th>Order</th>
										<th>Actions</th>
									</tr>
								</thead>
								<tbody>
									{isLoading ? (
										<SkeletonRows />
									) : (
										data?.items.map((cart) => (
											<CartRow
												key={cart.id}
												cart={cart}
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
