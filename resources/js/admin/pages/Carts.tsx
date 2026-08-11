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
import { DEFAULT_PER_PAGE, Pagination } from '../components/Pagination';
import { formatExact, formatMoney, formatWhen } from '../lib/format';
import { statusLabel } from '../lib/status';
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
	'pending-payment',
	'recovered',
	'completed',
	'lost',
];
const CHANGE_STATUSES = [
	'active',
	'abandoned',
	'pending-payment',
	'recovered',
	'completed',
	'lost',
];
const COLUMN_COUNT = 9;

type Feedback = { type: 'success' | 'error'; message: string };

// Column key → the backend sort column it maps to.
const SORTABLE = {
	id: 'id',
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

// Each cart status paired with a plain-language meaning for the status guide.
const STATUS_GUIDE: Array<[string, string]> = [
	[
		'active',
		__(
			'Shopper is still building their cart — no order placed yet.',
			'cart-rebound'
		),
	],
	[
		'abandoned',
		__(
			'Idle past the threshold; a recovery email may be scheduled.',
			'cart-rebound'
		),
	],
	[
		'pending-payment',
		__(
			'Order placed but not paid yet (e.g. cheque or bank transfer). Items are kept and no recovery email is sent.',
			'cart-rebound'
		),
	],
	[
		'recovered',
		__(
			'An abandoned cart that came back and paid — a recovery win.',
			'cart-rebound'
		),
	],
	[
		'completed',
		__(
			'Converted to a paid order without ever being abandoned.',
			'cart-rebound'
		),
	],
	[
		'lost',
		__(
			'Abandoned and cleaned up, or a paid order later refunded or cancelled.',
			'cart-rebound'
		),
	],
];

const GuideBadge = ({ status }: { status: string }) => (
	<span className={`cr-badge is-${status}`}>{statusLabel(status)}</span>
);

const StatusGuide = () => (
	<details className="cr-guide">
		<summary className="cr-guide__summary">
			{__('What do these statuses mean?', 'cart-rebound')}
		</summary>
		<div className="cr-guide__body">
			<ul className="cr-guide__list">
				{STATUS_GUIDE.map(([key, meaning]) => (
					<li key={key} className="cr-guide__item">
						<GuideBadge status={key} />
						<span className="cr-guide__meaning">{meaning}</span>
					</li>
				))}
			</ul>
			<div className="cr-guide__flow" aria-hidden="true">
				<GuideBadge status="active" />
				<span className="cr-guide__arrow">→</span>
				<GuideBadge status="abandoned" />
				<span className="cr-guide__arrow">→</span>
				<GuideBadge status="pending-payment" />
				<span className="cr-guide__arrow">→</span>
				<GuideBadge status="recovered" />
				<span className="cr-guide__sep">/</span>
				<GuideBadge status="completed" />
			</div>
			<p className="cr-guide__note">
				{__(
					'A cancelled or failed order returns the cart to Active with its items kept; a refund moves a converted cart to Lost.',
					'cart-rebound'
				)}
			</p>
		</div>
	</details>
);

const orderLabel = (order: Order, currency: string): string => {
	const who = order.email !== '' ? order.email : __('guest', 'cart-rebound');

	// The rest of the screen prints money through formatMoney, which follows the
	// store's own WooCommerce display settings; this line was rolling its own
	// "12.00 USD" beside cells reading "$12.00".
	return sprintf(
		/* translators: 1: order number, 2: customer, 3: order total. */
		__('#%1$s · %2$s · %3$s', 'cart-rebound'),
		order.number,
		who,
		formatMoney(order.total, currency)
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

/**
 * Why this list is empty, in the merchant's terms.
 *
 * Three situations shared one sentence: a store with no carts at all, a status
 * filter that matches none, and a search with no hits. Only the first is about
 * the plugin — telling the other two "no carts yet" sends someone looking for a
 * tracking bug that is not there.
 * @param search The settled search term.
 * @param status The selected status filter.
 */
const emptyExplanation = (search: string, status: string): string => {
	if (search !== '') {
		return sprintf(
			/* translators: %s: the searched email address. */
			__(
				'Nothing matches “%s”. Clear the search to see every cart.',
				'cart-rebound'
			),
			search
		);
	}

	if (status !== '') {
		return sprintf(
			/* translators: %s: the selected status, e.g. Abandoned. */
			__(
				'No cart is currently %s. Choose “All statuses” to see the rest.',
				'cart-rebound'
			),
			statusLabel(status)
		);
	}

	return __(
		'Tracked carts appear here as shoppers add items and reach checkout.',
		'cart-rebound'
	);
};

const SearchIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<circle cx="7" cy="7" r="4.3" stroke="currentColor" strokeWidth="1.3" />
		<path
			d="m10.4 10.4 3 3"
			stroke="currentColor"
			strokeWidth="1.3"
			strokeLinecap="round"
		/>
	</svg>
);

const EyeIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<path
			d="M1.9 8s2.3-4.1 6.1-4.1S14.1 8 14.1 8s-2.3 4.1-6.1 4.1S1.9 8 1.9 8Z"
			stroke="currentColor"
			strokeWidth="1.3"
			strokeLinejoin="round"
		/>
		<circle cx="8" cy="8" r="1.7" stroke="currentColor" strokeWidth="1.3" />
	</svg>
);

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

/**
 * The linked order number, deep-linking into WooCommerce when we have a URL for
 * it. The URL is built server-side because only PHP knows whether the store is
 * on HPOS, which changes where orders are edited.
 * @param root0
 * @param root0.cart
 */
const OrderLink = ({ cart }: { cart: Cart }) => {
	if (cart.order_id <= 0) {
		return <Dash />;
	}

	const label = `#${cart.order_id}`;

	if (cart.order_edit_url === '') {
		return <>{label}</>;
	}

	return (
		<a
			className="cr-linkbtn"
			href={cart.order_edit_url}
			title={__('Open this order in WooCommerce', 'cart-rebound')}
		>
			{label}
		</a>
	);
};

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
	onView,
	notify,
	currency,
}: {
	cart: Cart;
	selected: boolean;
	onToggle: (id: number, checked: boolean) => void;
	onRecover: (cart: Cart) => void;
	onSendEmail: (cart: Cart) => void;
	onView: (cart: Cart) => void;
	notify: (feedback: Feedback) => void;
	currency: string;
}) => {
	const remove = useDeleteCart();
	const status = useUpdateStatus();

	const onStatusChange = (next: string) => {
		/*
		 * Two ways to make a cart Recovered wrote different data: this pill set
		 * only the status, while the order picker also attributed the money. A
		 * cart flipped here therefore raised the recovered *count* and left
		 * recovered revenue untouched, quietly dragging the average order value
		 * down. So the pill hands over to the picker that gets it right, unless
		 * the cart already has an order to attribute to.
		 */
		if ('recovered' === next && cart.order_id === 0) {
			onRecover(cart);

			return;
		}

		/*
		 * Abandoned is not a label — the server routes it through the abandonment
		 * detector, which opens a follow-up plan and can put a real email in
		 * front of a real shopper. That deserves a question first.
		 */
		if ('abandoned' === next) {
			// eslint-disable-next-line no-alert
			const confirmed = window.confirm(
				sprintf(
					/* translators: %d: cart ID. */
					__(
						'Mark cart #%d abandoned? Cart Rebound will schedule a recovery email for it and restart its follow-up sequence.',
						'cart-rebound'
					),
					cart.id
				)
			);

			if (!confirmed) {
				return;
			}
		}

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

	/*
	 * Deleting one cart asks first, exactly as deleting a selection does. The row
	 * button sits beside three harmless ones and takes a single click, which is
	 * the shape of an accident; and a deleted cart takes its recovery history
	 * with it — there is nothing to undo it with.
	 */
	const onDelete = () => {
		// eslint-disable-next-line no-alert
		const confirmed = window.confirm(
			cart.email !== ''
				? sprintf(
						/* translators: 1: cart ID, 2: shopper email address. */
						__(
							'Delete cart #%1$d (%2$s)? This cannot be undone.',
							'cart-rebound'
						),
						cart.id,
						cart.email
					)
				: sprintf(
						/* translators: %d: cart ID. */
						__(
							'Delete cart #%d? This cannot be undone.',
							'cart-rebound'
						),
						cart.id
					)
		);

		if (!confirmed) {
			return;
		}

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
			<td className="cr-nowrap" data-label={__('Cart', 'cart-rebound')}>
				<button
					type="button"
					className="cr-linkbtn"
					onClick={() => {
						onView(cart);
					}}
					title={__('View cart details', 'cart-rebound')}
				>
					#{cart.id}
				</button>
			</td>
			{/* The column truncates at 220px, and a long address is exactly the
			    one a merchant needs to read in full, so the untruncated value
			    stays available on hover. */}
			<td
				className="cr-cell-email"
				title={cart.email}
				data-label={__('Email', 'cart-rebound')}
			>
				{cart.email !== '' ? cart.email : <Dash />}
			</td>
			<td data-label={__('Items', 'cart-rebound')}>{cart.items_count}</td>
			<td
				className="cr-money cr-num"
				data-label={__('Total', 'cart-rebound')}
			>
				{formatMoney(cart.cart_total, currency)}
			</td>
			<td data-label={__('Status', 'cart-rebound')}>
				<StatusSelect
					cart={cart}
					pending={status.isPending}
					onChange={onStatusChange}
				/>
			</td>
			<td
				className="cr-muted cr-nowrap"
				title={formatExact(cart.last_activity)}
				data-label={__('Last activity', 'cart-rebound')}
			>
				{formatWhen(cart.last_activity)}
			</td>
			<td className="cr-num" data-label={__('Order', 'cart-rebound')}>
				<OrderLink cart={cart} />
			</td>
			<td data-label={__('Actions', 'cart-rebound')}>
				<div className="cr-row-actions">
					<button
						type="button"
						className="cr-iconbtn"
						onClick={() => {
							onView(cart);
						}}
						title={__('View cart details', 'cart-rebound')}
						aria-label={sprintf(
							/* translators: %d: cart ID. */
							__('View details for cart %d', 'cart-rebound'),
							cart.id
						)}
					>
						<EyeIcon />
					</button>
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
					{/* The title sits on the wrapper: a disabled button swallows
					    pointer events in several browsers, so the three reasons
					    this control can be dead never appeared at all. */}
					<span title={emailButtonTitle(cart)}>
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
							aria-label={sprintf(
								/* translators: %s: why the action is unavailable, or what it does. */
								__('Send recovery email — %s', 'cart-rebound'),
								emailButtonTitle(cart)
							)}
						>
							<MailIcon />
						</button>
					</span>
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

const CartDetail = ({
	cart,
	onClose,
	currency,
}: {
	cart: Cart | null;
	onClose: () => void;
	currency: string;
}) => {
	const ref = useRef<HTMLDialogElement>(null);

	useEffect(() => {
		const el = ref.current;

		if (!el) {
			return;
		}

		if (cart && !el.open) {
			el.showModal();
		} else if (!cart && el.open) {
			el.close();
		}
	}, [cart]);

	if (!cart) {
		return null;
	}

	const money = (value: number) => formatMoney(value, currency);
	const name = `${cart.first_name} ${cart.last_name}`.trim();

	const timeline: Array<[string, string]> = [
		[__('Created', 'cart-rebound'), formatExact(cart.created_at)],
	];

	if (cart.abandoned_at !== '') {
		timeline.push([
			_x('Abandoned', 'cart status', 'cart-rebound'),
			formatExact(cart.abandoned_at),
		]);
	}
	if (cart.recovered_at !== '') {
		timeline.push([
			_x('Recovered', 'cart status', 'cart-rebound'),
			formatExact(cart.recovered_at),
		]);
	}
	if (cart.completed_at !== '') {
		timeline.push([
			_x('Completed', 'cart status', 'cart-rebound'),
			formatExact(cart.completed_at),
		]);
	}
	timeline.push([
		__('Last activity', 'cart-rebound'),
		formatExact(cart.last_activity),
	]);

	const identity = name !== '' ? name : cart.email;
	const avatar = (identity.trim()[0] ?? '#').toUpperCase();
	const hasItems = cart.products.length > 0;

	return (
		// Backdrop click-to-close, as the other two dialogs on this screen have;
		// Esc is handled natively.
		// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions, jsx-a11y/click-events-have-key-events
		<dialog
			ref={ref}
			className="cr-dialog is-wide cr-detail"
			aria-labelledby="cr-detail-title"
			onClose={onClose}
			onClick={(event) => {
				if (event.target === ref.current) {
					onClose();
				}
			}}
		>
			<header className="cr-detail__head">
				<span className="cr-detail__avatar" aria-hidden="true">
					{avatar}
				</span>
				<div className="cr-detail__ident">
					<h2 id="cr-detail-title" className="cr-detail__name">
						{identity !== '' ? (
							identity
						) : (
							<span className="cr-muted">
								{__('Guest cart', 'cart-rebound')}
							</span>
						)}
					</h2>
					<p className="cr-detail__sub">
						{sprintf(
							/* translators: %d: cart id. */
							__('Cart #%d', 'cart-rebound'),
							cart.id
						)}
					</p>
				</div>
				<span className={`cr-badge is-${cart.status}`}>
					{statusLabel(cart.status)}
				</span>
			</header>

			<div className="cr-detail__body">
				<dl className="cr-detail__meta">
					<div>
						<dt>{__('Customer', 'cart-rebound')}</dt>
						<dd>{name !== '' ? name : <Dash />}</dd>
					</div>
					<div>
						<dt>{__('Email', 'cart-rebound')}</dt>
						<dd>{cart.email !== '' ? cart.email : <Dash />}</dd>
					</div>
					<div>
						<dt>{__('Phone', 'cart-rebound')}</dt>
						<dd>{cart.phone !== '' ? cart.phone : <Dash />}</dd>
					</div>
					<div>
						<dt>{__('Order', 'cart-rebound')}</dt>
						<dd>
							<OrderLink cart={cart} />
						</dd>
					</div>
					{/* The figure the Dashboard and Analytics count as recovered
					    revenue was nowhere on the cart it belongs to, so a cart
					    marked recovered by hand — which records no amount — looked
					    identical to one carrying its order's value. */}
					{cart.recovered_amount > 0 && (
						<div>
							<dt>{__('Recovered value', 'cart-rebound')}</dt>
							<dd className="cr-money">
								{money(cart.recovered_amount)}
							</dd>
						</div>
					)}
				</dl>

				<section className="cr-detail__block">
					<h3 className="cr-detail__blocktitle">
						{__('Items', 'cart-rebound')}
					</h3>
					<table className="cr-detail__items">
						<thead>
							<tr>
								<th>{__('Product', 'cart-rebound')}</th>
								<th style={{ textAlign: 'center' }}>
									{__('Qty', 'cart-rebound')}
								</th>
								<th style={{ textAlign: 'right' }}>
									{__('Total', 'cart-rebound')}
								</th>
							</tr>
						</thead>
						<tbody>
							{hasItems ? (
								cart.products.map((product, index) => (
									<tr key={`${product.product_id}-${index}`}>
										<td>{product.name}</td>
										<td style={{ textAlign: 'center' }}>
											{product.qty}
										</td>
										<td
											className="cr-money"
											style={{ textAlign: 'right' }}
										>
											{money(product.total)}
										</td>
									</tr>
								))
							) : (
								<tr>
									<td
										colSpan={3}
										className="cr-detail__empty"
									>
										{__(
											'No items recorded.',
											'cart-rebound'
										)}
									</td>
								</tr>
							)}
						</tbody>
						<tfoot>
							<tr>
								<th>{__('Cart total', 'cart-rebound')}</th>
								<td style={{ textAlign: 'center' }}>
									{cart.items_count}
								</td>
								<td
									className="cr-money"
									style={{ textAlign: 'right' }}
								>
									{money(cart.cart_total)}
								</td>
							</tr>
						</tfoot>
					</table>

					{cart.coupons.length > 0 && (
						<div className="cr-detail__coupons">
							<span className="cr-detail__couponlabel">
								{__('Coupons', 'cart-rebound')}
							</span>
							{cart.coupons.map((code) => (
								<span key={code} className="cr-chip">
									{code}
								</span>
							))}
						</div>
					)}
				</section>

				<section className="cr-detail__block">
					<h3 className="cr-detail__blocktitle">
						{__('Timeline', 'cart-rebound')}
					</h3>
					<ul className="cr-detail__timeline">
						{timeline.map(([label, value]) => (
							<li key={label}>
								<span className="cr-detail__tl-label">
									{label}
								</span>
								<span className="cr-detail__tl-value">
									{value}
								</span>
							</li>
						))}
					</ul>
				</section>
			</div>

			<footer className="cr-detail__foot">
				<button
					type="button"
					className="cr-btn is-ghost"
					onClick={onClose}
				>
					{__('Close', 'cart-rebound')}
				</button>
			</footer>
		</dialog>
	);
};

const RecoverDialog = ({
	cart,
	orders,
	onClose,
	notify,
	currency,
}: {
	cart: Cart | null;
	orders: Order[];
	onClose: () => void;
	notify: (feedback: Feedback) => void;
	currency: string;
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
				/*
				 * The failure stays in the dialog. Sent to the page-level notice it
				 * rendered behind this modal's own backdrop — and auto-dismissed
				 * four seconds later, so by the time the merchant closed the dialog
				 * to look for it, it was gone.
				 */
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
						onChange={(next) => {
							setPicked(next);
							setCustom('');
						}}
						options={[
							{
								value: '',
								label: __('Select an order…', 'cart-rebound'),
							},
							...orders.map((order) => ({
								value: String(order.id),
								label: orderLabel(order, currency),
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
							setPicked('');
						}}
					/>
				</div>

				{/*
				 * Two controls choose one value and the typed one silently won, with
				 * nothing on screen naming the order about to be linked. Each control
				 * now clears the other, and the resolved choice is stated in words —
				 * a disabled button was the only previous signal that nothing was set.
				 */}
				<p className="cr-dialog__resolved">
					{orderId > 0
						? sprintf(
								/* translators: %d: WooCommerce order ID. */
								__('Will link order #%d.', 'cart-rebound'),
								orderId
							)
						: __(
								'Pick a recent order, or type an order ID, to continue.',
								'cart-rebound'
							)}
				</p>

				{mark.isError && (
					<div className="cr-notice is-error" role="alert">
						{messageOf(mark.error)}
					</div>
				)}

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
				/*
				 * The failure stays in the dialog — see RecoverDialog. A page-level
				 * notice renders behind this modal's backdrop and expires before the
				 * merchant can close the dialog to read it.
				 */
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

				{templates.length === 0 && (
					<div className="cr-notice is-warning">
						{__(
							'No templates exist yet, so this send would use the built-in default wording. Create a template first to control what the shopper reads.',
							'cart-rebound'
						)}
					</div>
				)}

				{send.isError && (
					<div className="cr-notice is-error" role="alert">
						{messageOf(send.error)}
					</div>
				)}

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
	const [perPage, setPerPage] = useState(DEFAULT_PER_PAGE);
	const [sort, setSort] = useState<{ by: string; order: SortOrder }>({
		by: 'last_activity',
		order: 'desc',
	});
	const [search, setSearch] = useState('');
	// The value the query actually uses, settled a beat after typing stops. A
	// request per keystroke would put eight in flight for one email address, and
	// the answers can arrive out of order.
	const [searchQuery, setSearchQuery] = useState('');
	const [selected, setSelected] = useState<Set<number>>(new Set());
	const [bulkStatus, setBulkStatus] = useState('');
	const [feedback, setFeedback] = useState<Feedback | null>(null);
	const [recoverCart, setRecoverCart] = useState<Cart | null>(null);
	const [sendCart, setSendCart] = useState<Cart | null>(null);
	const [detailCart, setDetailCart] = useState<Cart | null>(null);

	useEffect(() => {
		const timer = window.setTimeout(() => {
			setSearchQuery(search.trim());
			setPage(1);
		}, 300);

		return () => {
			window.clearTimeout(timer);
		};
	}, [search]);

	const { data, isLoading, isFetching, isError } = useCarts({
		status,
		email: searchQuery,
		page,
		per_page: perPage,
		orderby: sort.by,
		order: sort.order,
	});
	const { data: orders } = useOrders();
	const { data: templates } = useTemplates();
	const bulk = useBulkCarts();

	const items = useMemo(() => data?.items ?? [], [data]);
	// One store currency for the whole page: every row, dialog, and order label
	// reads the same code instead of trusting per-row values.
	const currency = data?.currency ?? '';

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

	/*
	 * Keep the cursor inside the list.
	 *
	 * Deleting the rows on the last page left `page` pointing past the end: the
	 * query came back empty, the empty state replaced the table — and took the
	 * pagination bar, and therefore the Previous button, with it. The merchant was
	 * stranded on a page that no longer existed with no way back except a filter
	 * change.
	 */
	useEffect(() => {
		const pages = Math.max(1, Math.ceil((data?.total ?? 0) / perPage));

		if (page > pages) {
			setPage(pages);
		}
	}, [data?.total, perPage, page]);

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

	/*
	 * A fresh column opens at the end a merchant actually wants: newest activity,
	 * biggest basket, highest order. Ascending-first meant the first click on
	 * "Total" showed the smallest carts in the store — a list nobody asked for —
	 * and every one of those columns needed a second click to be useful.
	 */
	const onSort = (column: string) => {
		const descendingFirst =
			column === SORTABLE.activity ||
			column === SORTABLE.total ||
			column === SORTABLE.items ||
			column === SORTABLE.id ||
			column === SORTABLE.order;

		setPage(1);
		setSort((current) =>
			current.by === column
				? {
						by: column,
						order: current.order === 'asc' ? 'desc' : 'asc',
					}
				: { by: column, order: descendingFirst ? 'desc' : 'asc' }
		);
	};

	const runBulk = (
		payload: { action: 'delete' } | { action: 'status'; status: string }
	) => {
		const ids = Array.from(selected);

		// Same reasoning as the row pill: a bulk move to Abandoned schedules a
		// recovery email for every cart in the selection.
		if (payload.action === 'status' && payload.status === 'abandoned') {
			// eslint-disable-next-line no-alert
			const confirmed = window.confirm(
				sprintf(
					/* translators: %d: number of selected carts. */
					_n(
						'Mark %d cart abandoned? Cart Rebound will schedule a recovery email for it and restart its follow-up sequence.',
						'Mark %d carts abandoned? Cart Rebound will schedule a recovery email for each one and restart their follow-up sequences.',
						ids.length,
						'cart-rebound'
					),
					ids.length
				)
			);

			if (!confirmed) {
				setBulkStatus('');

				return;
			}
		}

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
				<div className="cr-search">
					<label
						className="screen-reader-text"
						htmlFor="cr-cart-search"
					>
						{__('Search carts by email', 'cart-rebound')}
					</label>
					<SearchIcon />
					<input
						id="cr-cart-search"
						type="search"
						className="cr-search__input"
						value={search}
						placeholder={__('Search by email…', 'cart-rebound')}
						onChange={(event) => {
							setSearch(event.target.value);
						}}
					/>
				</div>

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

			<StatusGuide />

			{feedback && (
				<div
					className={`cr-notice is-${feedback.type} cr-notice--inset`}
					role="status"
				>
					{feedback.message}
				</div>
			)}

			{selected.size > 0 && (
				<div className="cr-bulkbar">
					<span className="cr-bulkbar__count">
						{sprintf(
							/* translators: %d: number of selected carts. */
							_n(
								'%d cart selected',
								'%d carts selected',
								selected.size,
								'cart-rebound'
							),
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
						className="cr-notice is-error cr-notice--inset"
						role="alert"
					>
						{__('Could not load carts.', 'cart-rebound')}
					</div>
				)}

				{/* Three situations used to share one sentence: a store with no
				    carts at all, a status filter that matches none, and a search
				    with no hits. Only the first is about the plugin, and telling
				    the other two "no carts yet" sends a merchant looking for a
				    tracking bug that is not there. */}
				{isEmpty && (
					<div className="cr-empty">
						<p className="cr-empty__title">
							{searchQuery !== '' || status !== ''
								? __('No carts match', 'cart-rebound')
								: __('No carts yet', 'cart-rebound')}
						</p>
						<p>{emptyExplanation(searchQuery, status)}</p>
					</div>
				)}

				{!isError && !isEmpty && (
					<>
						<div className="cr-table-wrap">
							<table className="cr-table cr-table--carts">
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
											label={__('ID', 'cart-rebound')}
											column={SORTABLE.id}
											sort={sort}
											onSort={onSort}
										/>
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
												onView={setDetailCart}
												notify={setFeedback}
												currency={currency}
											/>
										))
									)}
								</tbody>
							</table>
						</div>

						<Pagination
							page={page}
							perPage={perPage}
							total={data?.total ?? 0}
							onPage={setPage}
							onPerPage={(next) => {
								setPerPage(next);
								setPage(1);
							}}
						/>
					</>
				)}
			</div>

			<RecoverDialog
				cart={recoverCart}
				orders={orders ?? []}
				currency={currency}
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

			<CartDetail
				cart={detailCart}
				currency={currency}
				onClose={() => {
					setDetailCart(null);
				}}
			/>
		</div>
	);
};
