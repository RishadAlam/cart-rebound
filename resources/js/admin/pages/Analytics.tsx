/**
 * Analytics — what the funnel and the sequence actually did.
 *
 * The free dashboard answers "how are we doing" with lifetime counters. This
 * answers the two questions those counters cannot: which step of the sequence
 * earns its send, and which products leak the most money. Both are decisions,
 * not vanity numbers, which is why the step table leads and the chart supports
 * it rather than the other way round.
 *
 * The chart is the dashboard's own component fed a mapped series. Two charts
 * that looked subtly different while plotting the same two quantities would be
 * a worse outcome than one shared curve.
 */
import { useMemo, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ProSurface } from '../components/ProSurface';
import { RevenueChart } from '../components/RevenueChart';
import { useProQuery } from '../hooks/useAddons';
import { fetchAnalytics, fetchAnalyticsCsv } from '../api/pro';
import { sampleAnalytics } from '../lib/sample';
import {
	formatCount,
	formatHours,
	formatMoney,
	formatPercent,
} from '../lib/format';
import type { AnalyticsPoint, TimeseriesPoint } from '../types/api';

const RANGES = [7, 30, 90];

const isoDaysAgo = (days: number): string =>
	new Date(Date.now() - days * 86_400_000).toISOString().slice(0, 10);

/**
 * The dashboard chart speaks in recoverable/recovered; map onto its vocabulary.
 * @param points The analytics series.
 */
const toChartSeries = (points: AnalyticsPoint[]): TimeseriesPoint[] =>
	points.map((point) => ({
		date: point.date,
		recoverable_revenue: point.abandoned_value,
		recovered_revenue: point.recovered_revenue,
		abandoned: point.abandoned,
		recovered: point.recovered,
	}));

const Metric = ({
	label,
	value,
	accent = false,
}: {
	label: string;
	value: string;
	accent?: boolean;
}) => (
	<div className={accent ? 'cr-stat is-accent' : 'cr-stat'}>
		<span className="cr-stat__label">{label}</span>
		<span className="cr-stat__value">{value}</span>
	</div>
);

export const Analytics = () => {
	const [days, setDays] = useState(30);
	const [exporting, setExporting] = useState(false);

	const currency = window.CartRebound?.currency.code ?? '';
	const range = useMemo(
		() => ({ from: isoDaysAgo(days - 1), to: isoDaysAgo(0) }),
		[days]
	);

	const analytics = useProQuery(
		'analytics',
		['pro', 'analytics', range.from, range.to],
		() => fetchAnalytics(range),
		sampleAnalytics(days, currency)
	);

	const { summary, timeseries, steps, products } = analytics.data;

	const onExport = () => {
		setExporting(true);

		void fetchAnalyticsCsv({ ...range, report: 'summary' })
			.then((csv) => {
				const url = URL.createObjectURL(
					new Blob([csv], { type: 'text/csv;charset=utf-8' })
				);
				const link = document.createElement('a');

				link.href = url;
				link.download = `cart-rebound-${range.from}-${range.to}.csv`;
				link.click();
				URL.revokeObjectURL(url);
			})
			.finally(() => {
				setExporting(false);
			});
	};

	return (
		<ProSurface
			feature="analytics"
			title={__('See which email earns its send', 'cart-rebound')}
			summary={__(
				'Recovered revenue over time, performance for every step of the sequence, and the products quietly costing you the most.',
				'cart-rebound'
			)}
			points={[
				__(
					'Open, click, and recovery rate per sequence step — so you can cut the step that does nothing',
					'cart-rebound'
				),
				__(
					'Recovered revenue and recovery rate over any date range',
					'cart-rebound'
				),
				__(
					'The products abandoned most often, with the value walking out with them',
					'cart-rebound'
				),
				__('CSV export of any range', 'cart-rebound'),
			]}
		>
			<div className="cr-analytics">
				<div className="cr-toolbar">
					<div
						className="cr-range"
						role="group"
						aria-label={__('Date range', 'cart-rebound')}
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
									setDays(option);
								}}
							>
								{sprintf(
									/* translators: %d: number of days. */
									__('%d days', 'cart-rebound'),
									option
								)}
							</button>
						))}
					</div>

					<span className="cr-toolbar__spacer" />

					<button
						type="button"
						className="cr-btn is-ghost is-sm"
						onClick={onExport}
						disabled={exporting}
					>
						{exporting
							? __('Preparing…', 'cart-rebound')
							: __('Export CSV', 'cart-rebound')}
					</button>
				</div>

				<div className="cr-metrics">
					<Metric
						label={__('Recovered revenue', 'cart-rebound')}
						value={formatMoney(
							summary.recovered_revenue,
							summary.currency
						)}
						accent
					/>
					<Metric
						label={__('Recovery rate', 'cart-rebound')}
						value={formatPercent(summary.recovery_rate)}
					/>
					<Metric
						label={__('Carts abandoned', 'cart-rebound')}
						value={formatCount(summary.abandoned_carts)}
					/>
					<Metric
						label={__('Value at risk', 'cart-rebound')}
						value={formatMoney(
							summary.abandoned_value,
							summary.currency
						)}
					/>
					<Metric
						label={__('Average recovered order', 'cart-rebound')}
						value={formatMoney(
							summary.average_order_value,
							summary.currency
						)}
					/>
					<Metric
						label={__('Median time to recovery', 'cart-rebound')}
						value={formatHours(summary.time_to_recovery)}
					/>
				</div>

				<div className="cr-card cr-section">
					<h2 className="cr-section__title">
						{__('Revenue at risk and recovered', 'cart-rebound')}
					</h2>
					<RevenueChart
						points={toChartSeries(timeseries)}
						currency={summary.currency}
					/>
				</div>

				<div className="cr-card cr-section">
					<h2 className="cr-section__title">
						{__('Sequence performance', 'cart-rebound')}
					</h2>
					<p className="cr-section__desc">
						{summary.tracking_available
							? __(
									'How each step performed over the selected range. A step that sends a lot and recovers nothing is a step worth rewriting or removing.',
									'cart-rebound'
								)
							: __(
									'Open and click tracking is switched off, so only sends and recoveries are measured here.',
									'cart-rebound'
								)}
					</p>

					{steps.length === 0 ? (
						<div className="cr-empty">
							<p className="cr-empty__title">
								{__(
									'No follow-ups were sent in this range.',
									'cart-rebound'
								)}
							</p>
						</div>
					) : (
						<div className="cr-table-wrap">
							<table className="cr-table">
								<thead>
									<tr>
										<th>{__('Step', 'cart-rebound')}</th>
										<th>{__('Sent', 'cart-rebound')}</th>
										<th>{__('Opened', 'cart-rebound')}</th>
										<th>{__('Clicked', 'cart-rebound')}</th>
										<th>
											{__('Recovered', 'cart-rebound')}
										</th>
										<th>{__('Revenue', 'cart-rebound')}</th>
									</tr>
								</thead>
								<tbody>
									{steps.map((row) => (
										<tr key={row.step}>
											<td>
												{sprintf(
													/* translators: %d: sequence step number. */
													__(
														'Step %d',
														'cart-rebound'
													),
													row.step
												)}
											</td>
											<td>{formatCount(row.sent)}</td>
											<td>
												{summary.tracking_available
													? `${formatCount(row.opened)} · ${formatPercent(row.open_rate)}`
													: '—'}
											</td>
											<td>
												{summary.tracking_available
													? `${formatCount(row.clicked)} · ${formatPercent(row.click_rate)}`
													: '—'}
											</td>
											<td>
												{formatCount(row.recovered)} ·{' '}
												{formatPercent(
													row.recovery_rate
												)}
											</td>
											<td>
												{formatMoney(
													row.revenue,
													summary.currency
												)}
											</td>
										</tr>
									))}
								</tbody>
							</table>
						</div>
					)}
				</div>

				<div className="cr-card cr-section">
					<h2 className="cr-section__title">
						{__('Most abandoned products', 'cart-rebound')}
					</h2>
					<p className="cr-section__desc">
						{__(
							'Ranked by the value left behind, not by how often they appear — a cheap item abandoned constantly costs less than one expensive item abandoned twice.',
							'cart-rebound'
						)}
					</p>

					{products.length === 0 ? (
						<div className="cr-empty">
							<p className="cr-empty__title">
								{__(
									'No abandoned products in this range.',
									'cart-rebound'
								)}
							</p>
						</div>
					) : (
						<div className="cr-table-wrap">
							<table className="cr-table">
								<thead>
									<tr>
										<th>{__('Product', 'cart-rebound')}</th>
										<th>
											{__('Abandoned', 'cart-rebound')}
										</th>
										<th>
											{__('Recovered', 'cart-rebound')}
										</th>
										<th>
											{__('Value lost', 'cart-rebound')}
										</th>
									</tr>
								</thead>
								<tbody>
									{products.map((row) => (
										<tr key={row.product_id}>
											<td>{row.name}</td>
											<td>
												{formatCount(row.abandoned)}
											</td>
											<td>
												{formatCount(row.recovered)}
											</td>
											<td>
												{formatMoney(
													row.lost_value,
													summary.currency
												)}
											</td>
										</tr>
									))}
								</tbody>
							</table>
						</div>
					)}
				</div>
			</div>
		</ProSurface>
	);
};
