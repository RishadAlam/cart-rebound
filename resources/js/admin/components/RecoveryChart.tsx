import { useId, useState } from 'react';
import type { CSSProperties, KeyboardEvent } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useElementWidth } from '../hooks/useElementWidth';
import { formatMoney, formatMoneyTick, formatNumber } from '../lib/format';
import type { Bucket } from '../lib/period';
import { formatDay, formatDayRange } from '../lib/time';

const HEIGHT = 248;
const PAD = { top: 14, right: 4, bottom: 30, left: 60 };

export const MIN_DAY_SLOT = 14;
export const PLOT_GUTTER = PAD.left + PAD.right;
const TICK_COUNTS = [4, 5, 3];
const PAIR_GAP = 2;
const MAX_BAR = 24;
const PAIR_SHARE = 0.8;
const LABEL_SPACING = 72;
const TIP_EDGE = 110;

const niceStep = (max: number, ticks: number): number => {
	const raw = max / ticks;
	const base = 10 ** Math.floor(Math.log10(raw));
	const fraction = raw / base;

	if (fraction <= 1) {
		return base;
	}

	if (fraction <= 2) {
		return 2 * base;
	}

	if (fraction <= 2.5) {
		return 2.5 * base;
	}

	if (fraction <= 5) {
		return 5 * base;
	}

	return 10 * base;
};

const columnPath = (x: number, y: number, w: number, h: number): string => {
	const r = Math.min(4, w / 2, h);
	const base = y + h;

	return `M${x} ${base}V${y + r}A${r} ${r} 0 0 1 ${x + r} ${y}H${
		x + w - r
	}A${r} ${r} 0 0 1 ${x + w} ${y + r}V${base}Z`;
};

const cartCount = (count: number): string =>
	sprintf(
		/* translators: %s: number of carts. */
		_n('%s cart', '%s carts', count, 'cart-rebound'),
		formatNumber(count, 0)
	);

export const RecoveryLegend = () => (
	<ul className="cr-rchart__legend">
		<li>
			<span
				className="cr-rchart__swatch is-abandoned"
				aria-hidden="true"
			/>
			{__('Abandoned', 'cart-rebound')}
		</li>
		<li>
			<span
				className="cr-rchart__swatch is-recovered"
				aria-hidden="true"
			/>
			{__('Won back', 'cart-rebound')}
		</li>
	</ul>
);

const BucketTable = ({
	buckets,
	currency,
	unit,
}: {
	buckets: Bucket[];
	currency: string;
	unit: 'day' | 'week';
}) => (
	<details className="cr-rchart__details">
		<summary>{__('Show these numbers as a table', 'cart-rebound')}</summary>
		<div className="cr-table-wrap">
			<table className="cr-table">
				<thead>
					<tr>
						<th>
							{unit === 'day'
								? __('Day', 'cart-rebound')
								: __('Week', 'cart-rebound')}
						</th>
						<th className="cr-num">
							{__('Carts abandoned', 'cart-rebound')}
						</th>
						<th className="cr-num">
							{__('Value abandoned', 'cart-rebound')}
						</th>
						<th className="cr-num">
							{__('Carts won back', 'cart-rebound')}
						</th>
						<th className="cr-num">
							{__('Value won back', 'cart-rebound')}
						</th>
					</tr>
				</thead>
				<tbody>
					{[...buckets].reverse().map((bucket) => (
						<tr key={bucket.start}>
							<td className="cr-nowrap">
								{formatDayRange(bucket.start, bucket.end)}
							</td>
							<td className="cr-num">
								{formatNumber(bucket.abandoned, 0)}
							</td>
							<td className="cr-num">
								{formatMoney(bucket.abandonedValue, currency)}
							</td>
							<td className="cr-num">
								{formatNumber(bucket.recovered, 0)}
							</td>
							<td className="cr-num">
								{formatMoney(bucket.recoveredValue, currency)}
							</td>
						</tr>
					))}
				</tbody>
			</table>
		</div>
	</details>
);

export const RecoveryChart = ({
	buckets,
	currency,
	unit,
}: {
	buckets: Bucket[];
	currency: string;
	unit: 'day' | 'week';
}) => {
	const { ref, width } = useElementWidth();
	const [active, setActive] = useState<number | null>(null);
	const hintId = useId();

	const max = buckets.reduce(
		(peak, bucket) =>
			Math.max(peak, bucket.abandonedValue, bucket.recoveredValue),
		0
	);

	if (buckets.length === 0 || max <= 0) {
		return (
			<div className="cr-rchart__blank">
				<p className="cr-rchart__blanktitle">
					{__('Nothing to chart for this period', 'cart-rebound')}
				</p>
				<p>
					{__(
						'Carts that are abandoned or won back in this period will show here.',
						'cart-rebound'
					)}
				</p>
			</div>
		);
	}

	const { step, ticks } = TICK_COUNTS.map((count) => ({
		step: niceStep(max, count),
		ticks: count,
	})).reduce((best, next) =>
		next.step * next.ticks < best.step * best.ticks ? next : best
	);
	const axisMax = step * ticks;
	const innerW = Math.max(0, width - PAD.left - PAD.right);
	const innerH = HEIGHT - PAD.top - PAD.bottom;
	const baseline = PAD.top + innerH;
	const slot = innerW / buckets.length;
	const barW = Math.max(
		1,
		Math.min(MAX_BAR, (slot * PAIR_SHARE - PAIR_GAP) / 2)
	);
	const labelEvery = Math.max(
		1,
		Math.ceil(
			buckets.length / Math.max(1, Math.floor(innerW / LABEL_SPACING))
		)
	);
	const current = active === null ? undefined : buckets[active];
	const focusIndex = Math.min(
		active ?? buckets.length - 1,
		buckets.length - 1
	);
	const focusBucket = buckets[focusIndex];

	const yAt = (value: number): number =>
		baseline - (value / axisMax) * innerH;
	const centerOf = (index: number): number => PAD.left + slot * (index + 0.5);

	const describe = (bucket: Bucket): string =>
		sprintf(
			/* translators: 1: a day or date range, 2: money abandoned, 3: number of carts abandoned, e.g. "3 carts", 4: money won back, 5: number of carts won back. */
			__(
				'%1$s: %2$s abandoned in %3$s, %4$s won back from %5$s.',
				'cart-rebound'
			),
			formatDayRange(bucket.start, bucket.end),
			formatMoney(bucket.abandonedValue, currency),
			cartCount(bucket.abandoned),
			formatMoney(bucket.recoveredValue, currency),
			cartCount(bucket.recovered)
		);

	const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
		const last = buckets.length - 1;
		const from = active ?? last;
		const next: Record<string, number> = {
			ArrowLeft: Math.max(0, from - 1),
			ArrowRight: Math.min(last, from + 1),
			Home: 0,
			End: last,
		};

		if (event.key === 'Escape') {
			setActive(null);

			return;
		}

		if (event.key in next) {
			event.preventDefault();
			setActive(next[event.key] ?? from);
		}
	};

	const tipStyle = (index: number): CSSProperties => {
		const x = centerOf(index);

		if (x < TIP_EDGE) {
			return { left: Math.max(0, x - slot / 2) };
		}

		if (x > width - TIP_EDGE) {
			return {
				left: Math.min(width, x + slot / 2),
				translate: '-100% 0',
			};
		}

		return { left: x, translate: '-50% 0' };
	};

	return (
		<div className="cr-rchart">
			<div
				className="cr-rchart__plot"
				ref={ref}
				role="slider"
				tabIndex={0}
				aria-label={
					unit === 'day'
						? __(
								'Abandoned and won-back value by day',
								'cart-rebound'
							)
						: __(
								'Abandoned and won-back value by week',
								'cart-rebound'
							)
				}
				aria-describedby={hintId}
				aria-valuemin={0}
				aria-valuemax={buckets.length - 1}
				aria-valuenow={focusIndex}
				aria-valuetext={focusBucket ? describe(focusBucket) : ''}
				onKeyDown={onKeyDown}
				onFocus={() => {
					setActive((index) => index ?? buckets.length - 1);
				}}
				onBlur={() => {
					setActive(null);
				}}
			>
				<p id={hintId} className="cr-sr-only">
					{unit === 'day'
						? __(
								'Use the left and right arrow keys to read each day.',
								'cart-rebound'
							)
						: __(
								'Use the left and right arrow keys to read each week.',
								'cart-rebound'
							)}
				</p>

				{width > 0 && (
					<svg
						width={width}
						height={HEIGHT}
						aria-hidden="true"
						onPointerLeave={(event) => {
							if (event.pointerType === 'mouse') {
								setActive(null);
							}
						}}
					>
						{Array.from({ length: ticks + 1 }, (_unused, tick) => {
							const y = yAt(step * tick);

							return (
								<g key={tick}>
									<line
										className={
											tick === 0
												? 'cr-rchart__base'
												: 'cr-rchart__grid'
										}
										x1={PAD.left}
										y1={y}
										x2={width - PAD.right}
										y2={y}
									/>
									<text
										className="cr-rchart__axis"
										x={PAD.left - 10}
										y={y + 4}
										textAnchor="end"
									>
										{formatMoneyTick(step * tick, currency)}
									</text>
								</g>
							);
						})}

						{current && active !== null && (
							<rect
								className="cr-rchart__hover"
								x={centerOf(active) - slot / 2 + 1}
								y={PAD.top}
								width={Math.max(0, slot - 2)}
								height={innerH}
								rx={4}
							/>
						)}

						{buckets.map((bucket, index) => {
							const center = centerOf(index);
							const lostH = baseline - yAt(bucket.abandonedValue);
							const wonH = baseline - yAt(bucket.recoveredValue);

							return (
								<g key={bucket.start}>
									{lostH > 0 && (
										<path
											className="cr-rchart__col is-abandoned"
											d={columnPath(
												center - PAIR_GAP / 2 - barW,
												baseline - lostH,
												barW,
												lostH
											)}
										/>
									)}
									{wonH > 0 && (
										<path
											className="cr-rchart__col is-recovered"
											d={columnPath(
												center + PAIR_GAP / 2,
												baseline - wonH,
												barW,
												wonH
											)}
										/>
									)}
								</g>
							);
						})}

						{buckets.map((bucket, index) => {
							const last = index === buckets.length - 1;
							const crowdsLast =
								!last &&
								buckets.length - 1 - index < labelEvery;

							if (
								(index % labelEvery !== 0 && !last) ||
								crowdsLast
							) {
								return null;
							}

							return (
								<text
									key={bucket.start}
									className="cr-rchart__axis"
									x={
										last
											? width - PAD.right
											: centerOf(index)
									}
									y={HEIGHT - 9}
									textAnchor={last ? 'end' : 'middle'}
								>
									{formatDay(bucket.start)}
								</text>
							);
						})}

						{buckets.map((bucket, index) => (
							<rect
								key={bucket.start}
								className="cr-rchart__hit"
								x={PAD.left + slot * index}
								y={PAD.top}
								width={slot}
								height={innerH}
								onPointerEnter={() => {
									setActive(index);
								}}
								onPointerDown={() => {
									setActive(index);
								}}
							/>
						))}
					</svg>
				)}

				{current && active !== null && (
					<div
						className="cr-rchart__tip"
						style={tipStyle(active)}
						aria-hidden="true"
					>
						<p className="cr-rchart__tiphead">
							{formatDayRange(current.start, current.end)}
						</p>
						<p className="cr-rchart__tiprow">
							<span className="cr-rchart__key is-abandoned" />
							<strong>
								{formatMoney(current.abandonedValue, currency)}
							</strong>
							<span>
								{sprintf(
									/* translators: %s: number of carts, e.g. "3 carts". */
									__('abandoned · %s', 'cart-rebound'),
									cartCount(current.abandoned)
								)}
							</span>
						</p>
						<p className="cr-rchart__tiprow">
							<span className="cr-rchart__key is-recovered" />
							<strong>
								{formatMoney(current.recoveredValue, currency)}
							</strong>
							<span>
								{sprintf(
									/* translators: %s: number of carts, e.g. "3 carts". */
									__('won back · %s', 'cart-rebound'),
									cartCount(current.recovered)
								)}
							</span>
						</p>
					</div>
				)}
			</div>

			<BucketTable buckets={buckets} currency={currency} unit={unit} />
		</div>
	);
};
