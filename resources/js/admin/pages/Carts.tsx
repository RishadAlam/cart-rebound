/**
 * Carts page — filterable, paginated list of tracked carts with row actions.
 */
import { useState } from 'react';
import { useCarts, useDeleteCart, useMarkRecovered } from '../hooks/useApi';
import type { Cart } from '../types/api';

const STATUSES = ['', 'active', 'abandoned', 'recovered', 'completed', 'lost'];
const PER_PAGE = 20;

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
		<tr className="border-b border-gray-100">
			<td className="px-3 py-2">
				{cart.email !== '' ? cart.email : '—'}
			</td>
			<td className="px-3 py-2">{cart.items_count}</td>
			<td className="px-3 py-2">{cart.cart_total.toFixed(2)}</td>
			<td className="px-3 py-2">
				<span className="rounded bg-gray-100 px-2 py-0.5 text-xs">
					{cart.status}
				</span>
			</td>
			<td className="px-3 py-2">{cart.last_activity}</td>
			<td className="px-3 py-2">
				{cart.order_id > 0 ? `#${cart.order_id}` : '—'}
			</td>
			<td className="px-3 py-2">
				<div className="flex items-center gap-2">
					<input
						type="number"
						min={1}
						value={orderId}
						placeholder="Order ID"
						aria-label="Order ID"
						onChange={(event) => {
							setOrderId(event.target.value);
						}}
						className="w-24 rounded border border-gray-300 px-2 py-1 text-sm"
					/>
					<button
						type="button"
						onClick={onMark}
						className="rounded bg-blue-600 px-2 py-1 text-sm text-white"
					>
						Mark recovered
					</button>
					<button
						type="button"
						onClick={() => {
							remove.mutate(cart.id);
						}}
						className="rounded bg-red-600 px-2 py-1 text-sm text-white"
					>
						Delete
					</button>
				</div>
			</td>
		</tr>
	);
};

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

	return (
		<div>
			<div className="mb-4 flex items-center gap-3">
				<label htmlFor="cr-status" className="text-sm text-gray-600">
					Status
				</label>
				<select
					id="cr-status"
					value={status}
					onChange={(event) => {
						setStatus(event.target.value);
						setPage(1);
					}}
					className="rounded border border-gray-300 px-2 py-1 text-sm"
				>
					{STATUSES.map((value) => (
						<option
							key={value === '' ? 'all' : value}
							value={value}
						>
							{value === '' ? 'All' : value}
						</option>
					))}
				</select>
			</div>

			{isLoading && <p className="text-gray-500">Loading…</p>}
			{isError && <p className="text-red-600">Could not load carts.</p>}

			{data && (
				<>
					<table className="w-full border-collapse text-left text-sm">
						<thead>
							<tr className="border-b border-gray-200 text-gray-500">
								<th className="px-3 py-2 font-medium">Email</th>
								<th className="px-3 py-2 font-medium">Items</th>
								<th className="px-3 py-2 font-medium">Total</th>
								<th className="px-3 py-2 font-medium">
									Status
								</th>
								<th className="px-3 py-2 font-medium">
									Last activity
								</th>
								<th className="px-3 py-2 font-medium">Order</th>
								<th className="px-3 py-2 font-medium">
									Actions
								</th>
							</tr>
						</thead>
						<tbody>
							{data.items.map((cart) => (
								<CartRow key={cart.id} cart={cart} />
							))}
						</tbody>
					</table>

					{data.items.length === 0 && (
						<p className="mt-4 text-gray-500">No carts found.</p>
					)}

					<div className="mt-4 flex items-center gap-3 text-sm">
						<button
							type="button"
							disabled={page <= 1}
							onClick={() => {
								setPage((current) => Math.max(1, current - 1));
							}}
							className="rounded border border-gray-300 px-3 py-1 disabled:opacity-50"
						>
							Previous
						</button>
						<span className="text-gray-600">
							Page {page} of {totalPages}
						</span>
						<button
							type="button"
							disabled={page >= totalPages}
							onClick={() => {
								setPage((current) =>
									Math.min(totalPages, current + 1)
								);
							}}
							className="rounded border border-gray-300 px-3 py-1 disabled:opacity-50"
						>
							Next
						</button>
					</div>
				</>
			)}
		</div>
	);
};
