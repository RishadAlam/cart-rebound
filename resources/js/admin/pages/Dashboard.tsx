/**
 * Dashboard — recovery performance at a glance.
 *
 * Laid out as a report rather than a wall of counts: an Overview strip of
 * headline metrics, a dual-series revenue trend, and two supporting reports
 * (latest cart activity, and which products get abandoned most).
 *
 * The Overview strip is lifetime/current state — status counts are a snapshot
 * and the recovery rate runs off purge-immune lifetime counters — while the
 * chart and the product report follow the selected time range. The headings say
 * so, so the two scopes are never confused for one another.
 */
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { isAxiosError } from 'axios';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Hint } from '../components/Hint';
import { RevenueChart } from '../components/RevenueChart';
import { MetricSkeleton, TableSkeleton } from '../components/Skeletons';
import {
	useCarts,
	useProductReport,
	useSettings,
	useStats,
	useTimeseries,
} from '../hooks/useApi';
import {
	formatCount,
	formatExact,
	formatMoney,
	formatWhen,
} from '../lib/format';
import { statusLabel } from '../lib/status';
import type { Cart, ProductReportRow, Stats } from '../types/api';

const RANGES = [7, 30, 90];
const REPORT_ROWS = 6;

const ArrowIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<path
			d="M5.5 10.5 10.5 5.5M6.4 5.5h4.1v4.1"
			stroke="currentColor"
			strokeWidth="1.3"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const Metric = ({
	label,
	value,
	hint,
	tone,
}: {
	label: string;
	value: ReactNode;
	hint: string;
	tone?: 'risk' | 'won';
}) => (
	<div className="cr-metric">
		<div className="cr-metric__top">
			<span className="cr-metric__label">{label}</span>
			<Hint text={hint} />
		</div>
		<p
			className={
				tone ? `cr-metric__value is-${tone}` : 'cr-metric__value'
			}
		>
			{value}
		</p>
	</div>
);

const Overview = ({ stats }: { stats: Stats }) => {
	const count = (key: string): number => stats.counts[key] ?? 0;

	return (
		<div className="cr-metrics">
			<Metric
				label={__('Recoverable carts', 'cart-rebound')}
				value={formatCount(count('abandoned'))}
				hint={__(
					'Abandoned carts that are still open to recovery.',
					'cart-rebound'
				)}
			/>
			<Metric
				label={__('Recovered carts', 'cart-rebound')}
				value={formatCount(count('recovered'))}
				hint={__(
					'Abandoned carts that came back and paid.',
					'cart-rebound'
				)}
			/>
			<Metric
				label={__('Lost carts', 'cart-rebound')}
				value={formatCount(count('lost'))}
				hint={__(
					'Paid orders later refunded or cancelled, plus carts you marked lost by hand. Carts that were never recovered are deleted at the end of the retention window, not counted here.',
					'cart-rebound'
				)}
			/>
			<Metric
				tone="risk"
				label={__('Recoverable revenue', 'cart-rebound')}
				value={formatMoney(stats.recoverable_revenue, stats.currency)}
				hint={__(
					'Total value of the carts currently sitting in Abandoned.',
					'cart-rebound'
				)}
			/>
			<Metric
				tone="won"
				label={__('Recovered revenue', 'cart-rebound')}
				value={formatMoney(stats.recovered_revenue, stats.currency)}
				hint={__(
					'Paid order value won back from recovered carts.',
					'cart-rebound'
				)}
			/>
			<Metric
				label={__('Recovery rate', 'cart-rebound')}
				value={`${stats.recovery_rate}%`}
				hint={__(
					'Share of all abandoned carts that were recovered, measured over the plugin’s lifetime so cleanup cannot inflate it.',
					'cart-rebound'
				)}
			/>
		</div>
	);
};

const RangePicker = ({
	days,
	onChange,
}: {
	days: number;
	onChange: (next: number) => void;
}) => (
	<div
		className="cr-range"
		role="group"
		aria-label={__('Chart time range', 'cart-rebound')}
	>
		{RANGES.map((option) => (
			<button
				key={option}
				type="button"
				className={
					option === days
						? 'cr-range__btn is-active'
						: 'cr-range__btn'
				}
				aria-pressed={option === days}
				onClick={() => {
					onChange(option);
				}}
			>
				{sprintf(
					/* translators: %d: number of days. */
					__('%dd', 'cart-rebound'),
					option
				)}
			</button>
		))}
	</div>
);

const ViewAll = ({ to }: { to: string }) => (
	<Link className="cr-viewall" to={to}>
		{__('View all', 'cart-rebound')}
		<ArrowIcon />
	</Link>
);

const customerOf = (cart: Cart): string => {
	const name = `${cart.first_name} ${cart.last_name}`.trim();

	if (name !== '') {
		return name;
	}

	return cart.email !== '' ? cart.email : __('Guest', 'cart-rebound');
};

const RecentCarts = () => {
	const { data, isLoading, isError } = useCarts({
		status: '',
		email: '',
		page: 1,
		per_page: REPORT_ROWS,
		orderby: 'last_activity',
		order: 'desc',
	});

	const items = data?.items ?? [];

	return (
		<section className="cr-card cr-report">
			<header className="cr-report__head">
				<h2 className="cr-report__title">
					{__('Recent activity', 'cart-rebound')}
				</h2>
				<ViewAll to="/carts" />
			</header>

			{isError ? (
				<div className="cr-report__empty">
					{__('Could not load recent carts.', 'cart-rebound')}
				</div>
			) : (
				<div className="cr-table-wrap">
					<table className="cr-table">
						<thead>
							<tr>
								<th>{__('Customer', 'cart-rebound')}</th>
								<th className="cr-num">
									{__('Cart total', 'cart-rebound')}
								</th>
								<th>{__('Status', 'cart-rebound')}</th>
								<th>{__('Last activity', 'cart-rebound')}</th>
							</tr>
						</thead>
						<tbody>
							{isLoading && <TableSkeleton columns={4} />}

							{!isLoading && items.length === 0 && (
								<tr>
									<td
										colSpan={4}
										className="cr-report__empty"
									>
										{__(
											'No carts tracked yet.',
											'cart-rebound'
										)}
									</td>
								</tr>
							)}

							{!isLoading &&
								items.map((cart) => (
									<tr key={cart.id}>
										<td className="cr-cell-email">
											{customerOf(cart)}
										</td>
										<td className="cr-money cr-num">
											{formatMoney(
												cart.cart_total,
												data?.currency ?? ''
											)}
										</td>
										<td>
											<span
												className={`cr-badge is-${cart.status}`}
											>
												{statusLabel(cart.status)}
											</span>
										</td>
										<td
											className="cr-muted cr-nowrap"
											title={formatExact(
												cart.last_activity
											)}
										>
											{formatWhen(cart.last_activity)}
										</td>
									</tr>
								))}
						</tbody>
					</table>
				</div>
			)}
		</section>
	);
};

const ProductReport = ({
	days,
	rows,
	currency,
	isLoading,
	isError,
}: {
	days: number;
	rows: ProductReportRow[];
	currency: string;
	isLoading: boolean;
	isError: boolean;
}) => (
	<section className="cr-card cr-report">
		<header className="cr-report__head">
			<div>
				<h2 className="cr-report__title">
					{__('Product report', 'cart-rebound')}
				</h2>
				{/* The rows are the six worst by value lost, not every product
				    abandoned in the window — which is what "Last 30 days" alone
				    implied. */}
				<p className="cr-report__sub">
					{sprintf(
						/* translators: 1: number of rows shown, 2: number of days. */
						_n(
							'Top %1$d by value lost · last %2$d day',
							'Top %1$d by value lost · last %2$d days',
							days,
							'cart-rebound'
						),
						REPORT_ROWS,
						days
					)}
				</p>
			</div>
		</header>

		{isError ? (
			<div className="cr-report__empty">
				{__('Could not load the product report.', 'cart-rebound')}
			</div>
		) : (
			<div className="cr-table-wrap">
				<table className="cr-table">
					<thead>
						<tr>
							<th>{__('Product', 'cart-rebound')}</th>
							<th className="cr-num">
								{__('Abandoned', 'cart-rebound')}
							</th>
							<th className="cr-num">
								{__('Recovered', 'cart-rebound')}
							</th>
							<th className="cr-num">
								{__('Value lost', 'cart-rebound')}
							</th>
						</tr>
					</thead>
					<tbody>
						{isLoading && <TableSkeleton columns={4} />}

						{!isLoading && rows.length === 0 && (
							<tr>
								<td colSpan={4} className="cr-report__empty">
									{__(
										'No products abandoned in this period.',
										'cart-rebound'
									)}
								</td>
							</tr>
						)}

						{!isLoading &&
							rows.map((row) => (
								<tr key={row.product_id}>
									<td className="cr-cell-email">
										<ProductName row={row} />
									</td>
									<td className="cr-num">
										{formatCount(row.abandoned)}
									</td>
									<td className="cr-num">
										{formatCount(row.recovered)}
									</td>
									<td className="cr-num">
										{formatMoney(row.lost_value, currency)}
									</td>
								</tr>
							))}
					</tbody>
				</table>
			</div>
		)}
	</section>
);

/**
 * A product's name, linked to its editor when the viewer can open it.
 *
 * The report exists to be acted on — "this product leaks the most money" is a
 * prompt to go and look at it — so the row carries you there instead of making
 * you search the catalogue for the name you just read.
 * @param root0     Component props.
 * @param root0.row The report row.
 */
const ProductName = ({ row }: { row: ProductReportRow }) => {
	const label =
		row.name !== ''
			? row.name
			: sprintf(
					/* translators: %d: product id. */
					__('Product #%d', 'cart-rebound'),
					row.product_id
				);

	if (row.edit_url === '') {
		return <>{label}</>;
	}

	return <a href={row.edit_url}>{label}</a>;
};

/**
 * The one thing a new store gets wrong, said plainly on the screen they land on.
 *
 * Cart Rebound tracks carts the moment it is activated, so the dashboard fills
 * with abandoned carts and recoverable revenue whether or not a single email is
 * configured to go out. A merchant reads those numbers as "it is working". This
 * says so when it is not, and links to the switch — a warning that does not
 * carry you to the fix is just an accusation.
 */
const NotSendingNotice = () => (
	<div className="cr-notice is-warning cr-notice--action">
		<div>
			<strong>
				{__('No recovery emails are going out.', 'cart-rebound')}
			</strong>{' '}
			{__(
				'Carts are being tracked and counted here, but nothing is being sent to bring shoppers back.',
				'cart-rebound'
			)}
		</div>
		<Link className="cr-btn is-primary is-sm" to="/settings">
			{__('Turn on recovery email', 'cart-rebound')}
		</Link>
	</div>
);

export const Dashboard = () => {
	const [days, setDays] = useState(30);
	const settings = useSettings();
	const stats = useStats();
	const series = useTimeseries(days);
	const products = useProductReport(days, REPORT_ROWS);

	if (stats.isError || (!stats.isLoading && !stats.data)) {
		const sessionExpired =
			isAxiosError(stats.error) && stats.error.response?.status === 401;

		return (
			<div className="cr-notice is-error" role="alert">
				{sessionExpired
					? __(
							'Your session has expired. Please reload the page.',
							'cart-rebound'
						)
					: __('Could not load statistics.', 'cart-rebound')}
			</div>
		);
	}

	return (
		<div className="cr-dash">
			{settings.data?.recovery_email_enabled === false && (
				<NotSendingNotice />
			)}

			<section className="cr-card cr-overview">
				<header className="cr-overview__head">
					<div>
						<h2 className="cr-overview__title">
							{__('Overview', 'cart-rebound')}
						</h2>
						<p className="cr-overview__sub">
							{__(
								'Where your tracked carts stand right now. The recovery rate is measured over the plugin’s lifetime, so cleanup cannot inflate it.',
								'cart-rebound'
							)}
						</p>
					</div>
				</header>

				{stats.data ? (
					<Overview stats={stats.data} />
				) : (
					<MetricSkeleton />
				)}
			</section>

			<section className="cr-card cr-trend">
				<header className="cr-trend__head">
					<div>
						<h2 className="cr-overview__title">
							{__('Revenue over time', 'cart-rebound')}
						</h2>
						<p className="cr-overview__sub">
							{__(
								'Value abandoned versus value won back, by day.',
								'cart-rebound'
							)}
						</p>
					</div>
					<RangePicker days={days} onChange={setDays} />
				</header>

				{series.isLoading || !series.data ? (
					<div
						className="cr-skeleton"
						style={{ height: 288, borderRadius: 10 }}
					/>
				) : (
					<RevenueChart
						points={series.data}
						currency={stats.data?.currency ?? ''}
					/>
				)}
			</section>

			<div className="cr-reports">
				<RecentCarts />
				<ProductReport
					days={days}
					rows={products.data ?? []}
					currency={stats.data?.currency ?? ''}
					isLoading={products.isLoading}
					isError={products.isError}
				/>
			</div>
		</div>
	);
};
