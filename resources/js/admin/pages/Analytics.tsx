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
import { Link } from 'react-router-dom';
import { __, sprintf } from '@wordpress/i18n';
import { Hint } from '../components/Hint';
import { ProSurface } from '../components/ProSurface';
import { MetricSkeleton } from '../components/Skeletons';
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

/**
 * A calendar date, counted back from the store's own today.
 *
 * This used to read `new Date(Date.now() - …).toISOString()`, which is a UTC
 * calendar day derived from the *browser's* clock. For a shop east or west of
 * UTC that is a different day from the one the merchant is having, so the
 * "last 30 days" window silently began and ended on the wrong dates — carts
 * from this morning missing at one end, an extra day of someone else's at the
 * other. The site's date ships with the page; the arithmetic from there is
 * plain calendar arithmetic and stays in that frame.
 * @param days How many days back from the store's today.
 */
const isoDaysAgo = (days: number): string => {
	const today = window.CartRebound?.today ?? '';
	const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(today);
	const base = parts
		? Date.UTC(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]))
		: Date.now();

	return new Date(base - days * 86_400_000).toISOString().slice(0, 10);
};

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

/**
 * The same tile the dashboard uses.
 *
 * This screen had grown its own, built on an older class whose label and value
 * are inline — so they ran together into "Recovered revenue29.00$". Two tile
 * implementations is one too many; there is now one, and it stacks.
 * @param root0       Component props.
 * @param root0.label What the number measures.
 * @param root0.value The number.
 * @param root0.tone  Colours the value when it is money won or money at risk.
 * @param root0.hint
 */
const Metric = ({
	label,
	value,
	hint,
	tone,
}: {
	label: string;
	value: string;
	/*
	 * Which population, over which window. Two labels on this screen are word
	 * for word the Dashboard's — where they mean lifetime figures — so without
	 * this a merchant comparing the two screens concludes one of them is wrong.
	 */
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

/**
 * Append the window to a metric's explanation.
 *
 * Two of these tiles carry exactly the Dashboard's labels, where the same words
 * describe lifetime figures. Saying which window this screen means, on every
 * tile, is what stops the two screens reading as a contradiction.
 * @param what What the metric measures.
 */
const rangeHint = (what: string): string =>
	sprintf(
		/* translators: %s: what the metric measures. */
		__('%s Counts the selected date range only.', 'cart-rebound'),
		what
	);

export const Analytics = () => {
	const [days, setDays] = useState(30);
	const [exporting, setExporting] = useState(false);
	const [exportError, setExportError] = useState('');

	const currency = window.CartRebound?.currency.code ?? '';
	const range = useMemo(
		() => ({ from: isoDaysAgo(days - 1), to: isoDaysAgo(0) }),
		[days]
	);

	// Built once per range rather than once per render: the sample is
	// deterministic, so rebuilding it only makes the chart redraw for nothing.
	const sample = useMemo(
		() => sampleAnalytics(days, currency),
		[days, currency]
	);

	const analytics = useProQuery(
		'analytics',
		['pro', 'analytics', range.from, range.to],
		() => fetchAnalytics(range),
		sample
	);

	const { summary, timeseries, steps, products } = analytics.data;

	/*
	 * Opens and clicks are independent settings. One combined flag made whichever
	 * was off print "0 · 0%" — a measurement, apparently, rather than the absence
	 * of one. An add-on older than this screen sends only the combined flag, so
	 * fall back to it and those installs behave exactly as they did.
	 */
	const opensTracked = summary.tracking_opens ?? summary.tracking_available;
	const clicksTracked = summary.tracking_clicks ?? summary.tracking_available;

	/*
	 * A failed export used to do nothing at all: the button went back to "Export
	 * CSV" and no file arrived, which is indistinguishable from a browser that
	 * blocked the download. The error says so instead.
	 *
	 * The object URL is revoked on the next frame rather than immediately —
	 * revoking it in the same task can cancel the download it was created for.
	 */
	const onExport = () => {
		setExporting(true);
		setExportError('');

		void fetchAnalyticsCsv({ ...range, report: 'summary' })
			.then((csv) => {
				const url = URL.createObjectURL(
					new Blob([csv], { type: 'text/csv;charset=utf-8' })
				);
				const link = document.createElement('a');

				link.href = url;
				link.download = `cart-rebound-${range.from}-${range.to}.csv`;
				link.click();

				window.setTimeout(() => {
					URL.revokeObjectURL(url);
				}, 0);
			})
			.catch((error: unknown) => {
				setExportError(
					error instanceof Error
						? error.message
						: __('Could not prepare the export.', 'cart-rebound')
				);
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

				{exportError !== '' && (
					<div className="cr-notice is-error" role="alert">
						{exportError}
					</div>
				)}

				{/*
				 * Until the request lands, `useProQuery` hands back the sample
				 * store — the same figures the locked preview shows. Rendering
				 * those as the merchant's own numbers, in the merchant's own
				 * currency, is worse than rendering nothing: it is a claim about
				 * their revenue that happens to be fiction. So the screen waits.
				 */}
				{analytics.isError && (
					<div
						className="cr-notice is-error cr-notice--inset"
						role="alert"
					>
						{__(
							'Could not load your analytics for this range. Reload the page to try again.',
							'cart-rebound'
						)}
					</div>
				)}

				{analytics.isLoading && !analytics.isError && (
					<>
						<MetricSkeleton />
						<div className="cr-card cr-section">
							<div
								className="cr-skeleton"
								style={{ height: 288, borderRadius: 10 }}
							/>
						</div>
					</>
				)}

				{!analytics.isLoading && !analytics.isError && (
					<>
						<div className="cr-metrics">
							<Metric
								label={__('Recovered revenue', 'cart-rebound')}
								hint={rangeHint(
									__(
										'Paid order value from carts recovered in this window.',
										'cart-rebound'
									)
								)}
								value={formatMoney(
									summary.recovered_revenue,
									summary.currency
								)}
								tone="won"
							/>
							<Metric
								label={__('Recovery rate', 'cart-rebound')}
								hint={rangeHint(
									__(
										'Share of the carts abandoned in this window that came back and paid.',
										'cart-rebound'
									)
								)}
								value={formatPercent(summary.recovery_rate)}
							/>
							<Metric
								label={__('Carts abandoned', 'cart-rebound')}
								hint={rangeHint(
									__(
										'Carts that became abandoned in this window.',
										'cart-rebound'
									)
								)}
								value={formatCount(summary.abandoned_carts)}
							/>
							<Metric
								label={__('Abandoned value', 'cart-rebound')}
								hint={rangeHint(
									__(
										'What those carts were worth when they were left behind.',
										'cart-rebound'
									)
								)}
								value={formatMoney(
									summary.abandoned_value,
									summary.currency
								)}
								tone="risk"
							/>
							<Metric
								label={__(
									'Average recovered order',
									'cart-rebound'
								)}
								hint={rangeHint(
									__(
										'Recovered revenue divided by the number of carts recovered.',
										'cart-rebound'
									)
								)}
								value={formatMoney(
									summary.average_order_value,
									summary.currency
								)}
							/>
							<Metric
								label={__(
									'Median time to recovery',
									'cart-rebound'
								)}
								hint={rangeHint(
									__(
										'Typical gap between a cart being abandoned and its order being paid.',
										'cart-rebound'
									)
								)}
								/*
								 * A window with no recoveries has no median. The formatter
								 * floors anything under a minute at "1 min", so an empty
								 * range used to claim carts came back within sixty seconds.
								 */
								value={
									summary.recovered_carts === 0
										? '—'
										: formatHours(summary.time_to_recovery)
								}
							/>
						</div>

						<div className="cr-card cr-section">
							<h2 className="cr-section__title">
								{__(
									'Abandoned and recovered value',
									'cart-rebound'
								)}
							</h2>
							<RevenueChart
								points={toChartSeries(timeseries)}
								currency={summary.currency}
								riskLabel={__(
									'Abandoned value',
									'cart-rebound'
								)}
								wonLabel={__(
									'Recovered revenue',
									'cart-rebound'
								)}
							/>
						</div>

						<div className="cr-card cr-section">
							<h2 className="cr-section__title">
								{__('Sequence performance', 'cart-rebound')}
							</h2>
							<p className="cr-section__desc">
								{__(
									'How each step performed over the selected range. A step that sends a lot and recovers nothing is a step worth rewriting or removing.',
									'cart-rebound'
								)}
								{opensTracked && clicksTracked ? null : (
									<>
										{' '}
										{opensTracked || clicksTracked
											? sprintf(
													/* translators: %s: "Open" or "Click". */
													__(
														'%s tracking is switched off, so that column reads as unmeasured.',
														'cart-rebound'
													),
													opensTracked
														? __(
																'Click',
																'cart-rebound'
															)
														: __(
																'Open',
																'cart-rebound'
															)
												)
											: __(
													'Open and click tracking are both switched off, so only sends and recoveries are measured here.',
													'cart-rebound'
												)}{' '}
										<Link to="/rules">
											{__(
												'Change what is measured',
												'cart-rebound'
											)}
										</Link>
									</>
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
												<th>
													{__('Step', 'cart-rebound')}
												</th>
												<th className="cr-num">
													{__('Sent', 'cart-rebound')}
												</th>
												<th className="cr-num">
													{__(
														'Opened',
														'cart-rebound'
													)}
												</th>
												<th className="cr-num">
													{__(
														'Clicked',
														'cart-rebound'
													)}
												</th>
												<th className="cr-num">
													{__(
														'Recovered',
														'cart-rebound'
													)}
												</th>
												<th className="cr-num">
													{__(
														'Revenue',
														'cart-rebound'
													)}
												</th>
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
													<td className="cr-num">
														{formatCount(row.sent)}
													</td>
													<td className="cr-num">
														{opensTracked
															? `${formatCount(row.opened)} · ${formatPercent(row.open_rate)}`
															: '—'}
													</td>
													<td className="cr-num">
														{clicksTracked
															? `${formatCount(row.clicked)} · ${formatPercent(row.click_rate)}`
															: '—'}
													</td>
													<td className="cr-num">
														{formatCount(
															row.recovered
														)}{' '}
														·{' '}
														{formatPercent(
															row.recovery_rate
														)}
													</td>
													<td className="cr-num">
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
												<th>
													{__(
														'Product',
														'cart-rebound'
													)}
												</th>
												<th className="cr-num">
													{__(
														'Abandoned',
														'cart-rebound'
													)}
												</th>
												<th className="cr-num">
													{__(
														'Recovered',
														'cart-rebound'
													)}
												</th>
												<th className="cr-num">
													{__(
														'Value lost',
														'cart-rebound'
													)}
												</th>
											</tr>
										</thead>
										<tbody>
											{products.map((row) => (
												<tr key={row.product_id}>
													{/* A snapshot taken before the name existed leaves this
											    blank, which rendered as an empty cell beside a
											    number. */}
													<td>
														{row.name !== ''
															? row.name
															: sprintf(
																	/* translators: %d: product id. */
																	__(
																		'Product #%d',
																		'cart-rebound'
																	),
																	row.product_id
																)}
													</td>
													<td className="cr-num">
														{formatCount(
															row.abandoned
														)}
													</td>
													<td className="cr-num">
														{formatCount(
															row.recovered
														)}
													</td>
													<td className="cr-num">
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
					</>
				)}
			</div>
		</ProSurface>
	);
};
