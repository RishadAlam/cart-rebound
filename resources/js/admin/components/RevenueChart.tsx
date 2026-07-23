/**
 * Dual-series revenue chart for the dashboard.
 *
 * Hand-rolled SVG rather than a charting dependency: the surface is only two
 * smoothed area/line pairs, so drawing it directly keeps a ~100 kB library out
 * of the admin bundle and lets the curves use the app's own design tokens.
 *
 * Curves are Catmull-Rom splines converted to cubic béziers. A sharp drop can
 * make a spline overshoot past the baseline, so the plot is clipped to its own
 * rect instead of letting the fill bleed into the axis.
 */
import { useEffect, useMemo, useRef, useState } from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { formatMoney } from '../lib/format';
import type { TimeseriesPoint } from '../types/api';

const HEIGHT = 288;
const PAD = { top: 12, right: 14, bottom: 30, left: 56 };
const TICKS = 4;
const TENSION = 0.18;
const MAX_LABELS = 8;

interface Pt {
	x: number;
	y: number;
}

/**
 * Round an axis step up to a readable 1/2/5 × 10ⁿ increment.
 * @param max   Largest value in the series.
 * @param ticks Number of intervals on the axis.
 */
const niceStep = (max: number, ticks: number): number => {
	const raw = max / ticks;
	const exponent = Math.floor(Math.log10(raw));
	const base = 10 ** exponent;
	const fraction = raw / base;

	if (fraction <= 1) {
		return base;
	}

	if (fraction <= 2) {
		return 2 * base;
	}

	if (fraction <= 5) {
		return 5 * base;
	}

	return 10 * base;
};

/**
 * Short axis notation — 1200 becomes "1.2K".
 * @param value Raw amount.
 */
const compact = (value: number): string => {
	if (value >= 1000000) {
		return `${Math.round(value / 100000) / 10}M`;
	}

	if (value >= 1000) {
		return `${Math.round(value / 100) / 10}K`;
	}

	return String(Math.round(value));
};

/**
 * Format a bucket date. Buckets are UTC days, so the formatter is pinned to UTC
 * to stop a local timezone shifting a point onto the previous day.
 * @param iso Date in Y-m-d form.
 */
const shortDate = (iso: string): string => {
	const parts = iso.split('-');
	const year = Number(parts[0] ?? 0);
	const month = Number(parts[1] ?? 1);
	const day = Number(parts[2] ?? 1);

	return new Intl.DateTimeFormat(undefined, {
		month: 'short',
		day: '2-digit',
		timeZone: 'UTC',
	}).format(new Date(Date.UTC(year, month - 1, day)));
};

/**
 * Build a smoothed path through the given points.
 * @param pts Plotted coordinates.
 */
const smoothPath = (pts: Pt[]): string => {
	const first = pts[0];

	if (!first) {
		return '';
	}

	if (pts.length === 1) {
		return `M ${first.x} ${first.y}`;
	}

	let d = `M ${first.x.toFixed(2)} ${first.y.toFixed(2)}`;

	for (let i = 0; i < pts.length - 1; i++) {
		const p1 = pts[i];
		const p2 = pts[i + 1];

		if (!p1 || !p2) {
			continue;
		}

		const p0 = pts[i - 1] ?? p1;
		const p3 = pts[i + 2] ?? p2;

		const c1x = p1.x + (p2.x - p0.x) * TENSION;
		const c1y = p1.y + (p2.y - p0.y) * TENSION;
		const c2x = p2.x - (p3.x - p1.x) * TENSION;
		const c2y = p2.y - (p3.y - p1.y) * TENSION;

		d += ` C ${c1x.toFixed(2)} ${c1y.toFixed(2)}, ${c2x.toFixed(
			2
		)} ${c2y.toFixed(2)}, ${p2.x.toFixed(2)} ${p2.y.toFixed(2)}`;
	}

	return d;
};

/** Track the container width so the SVG can be drawn at real pixel size. */
const useWidth = () => {
	const ref = useRef<HTMLDivElement>(null);
	const [width, setWidth] = useState(0);

	useEffect(() => {
		const el = ref.current;

		if (!el) {
			return;
		}

		const observer = new ResizeObserver((entries) => {
			for (const entry of entries) {
				setWidth(entry.contentRect.width);
			}
		});

		observer.observe(el);

		return () => {
			observer.disconnect();
		};
	}, []);

	return { ref, width };
};

const Legend = () => (
	<div className="cr-chart__legend">
		<span className="cr-chart__key">
			<span className="cr-chart__swatch is-risk" aria-hidden="true" />
			{__('Recoverable revenue', 'cart-rebound')}
		</span>
		<span className="cr-chart__key">
			<span className="cr-chart__swatch is-won" aria-hidden="true" />
			{__('Recovered revenue', 'cart-rebound')}
		</span>
	</div>
);

export const RevenueChart = ({
	points,
	currency,
}: {
	points: TimeseriesPoint[];
	currency: string;
}) => {
	const { ref, width } = useWidth();
	const [active, setActive] = useState<number | null>(null);

	const max = useMemo(
		() =>
			points.reduce(
				(peak, point) =>
					Math.max(
						peak,
						point.recoverable_revenue,
						point.recovered_revenue
					),
				0
			),
		[points]
	);

	const step = max > 0 ? niceStep(max, TICKS) : 0;
	const axisMax = step * TICKS;
	const innerW = Math.max(0, width - PAD.left - PAD.right);
	const innerH = HEIGHT - PAD.top - PAD.bottom;
	const baseline = PAD.top + innerH;

	const xAt = (index: number): number => {
		if (points.length <= 1) {
			return PAD.left + innerW / 2;
		}

		return PAD.left + (index / (points.length - 1)) * innerW;
	};

	const yAt = (value: number): number => {
		if (axisMax <= 0) {
			return baseline;
		}

		return baseline - (value / axisMax) * innerH;
	};

	const risk = points.map((point, index) => ({
		x: xAt(index),
		y: yAt(point.recoverable_revenue),
	}));
	const won = points.map((point, index) => ({
		x: xAt(index),
		y: yAt(point.recovered_revenue),
	}));

	const areaOf = (pts: Pt[]): string => {
		const line = smoothPath(pts);
		const first = pts[0];
		const last = pts[pts.length - 1];

		if (line === '' || !first || !last) {
			return '';
		}

		return `${line} L ${last.x.toFixed(2)} ${baseline} L ${first.x.toFixed(
			2
		)} ${baseline} Z`;
	};

	const labelEvery = Math.max(1, Math.ceil(points.length / MAX_LABELS));
	const activePoint = active === null ? undefined : points[active];

	const onMove = (event: ReactPointerEvent<SVGRectElement>) => {
		if (points.length === 0 || innerW <= 0) {
			return;
		}

		const box = event.currentTarget.getBoundingClientRect();
		const ratio = (event.clientX - box.left) / box.width;
		const index = Math.round(ratio * (points.length - 1));

		setActive(Math.max(0, Math.min(points.length - 1, index)));
	};

	if (points.length === 0 || max <= 0) {
		return (
			<div className="cr-chart">
				<Legend />
				<div className="cr-chart__blank" style={{ height: HEIGHT }}>
					<p className="cr-chart__blanktitle">
						{__('No revenue activity yet', 'cart-rebound')}
					</p>
					<p>
						{__(
							'Once carts are abandoned and recovered in this period, the trend appears here.',
							'cart-rebound'
						)}
					</p>
				</div>
			</div>
		);
	}

	const tipLeft = Math.min(
		Math.max(xAt(active ?? 0), PAD.left + 8),
		Math.max(PAD.left + 8, width - 8)
	);

	return (
		<div className="cr-chart">
			<Legend />

			<div className="cr-chart__plot" ref={ref}>
				{width > 0 && (
					<svg
						width={width}
						height={HEIGHT}
						role="img"
						aria-label={sprintf(
							/* translators: %d: number of days covered by the chart. */
							__(
								'Recoverable and recovered revenue over the last %d days',
								'cart-rebound'
							),
							points.length
						)}
					>
						<defs>
							<linearGradient
								id="cr-fill-risk"
								x1="0"
								y1="0"
								x2="0"
								y2="1"
							>
								<stop
									className="cr-chart__stop-risk"
									offset="0%"
									stopOpacity="0.22"
								/>
								<stop
									className="cr-chart__stop-risk"
									offset="100%"
									stopOpacity="0"
								/>
							</linearGradient>
							<linearGradient
								id="cr-fill-won"
								x1="0"
								y1="0"
								x2="0"
								y2="1"
							>
								<stop
									className="cr-chart__stop-won"
									offset="0%"
									stopOpacity="0.22"
								/>
								<stop
									className="cr-chart__stop-won"
									offset="100%"
									stopOpacity="0"
								/>
							</linearGradient>
							<clipPath id="cr-plot-clip">
								<rect
									x={PAD.left}
									y={PAD.top}
									width={innerW}
									height={innerH}
								/>
							</clipPath>
						</defs>

						{/* Horizontal gridlines + value axis. */}
						{Array.from({ length: TICKS + 1 }, (_unused, tick) => {
							const value = step * tick;
							const y = yAt(value);

							return (
								<g key={tick}>
									<line
										className="cr-chart__grid"
										x1={PAD.left}
										y1={y}
										x2={width - PAD.right}
										y2={y}
									/>
									<text
										className="cr-chart__axis"
										x={PAD.left - 10}
										y={y + 4}
										textAnchor="end"
									>
										{compact(value)}
									</text>
								</g>
							);
						})}

						<g clipPath="url(#cr-plot-clip)">
							<path d={areaOf(risk)} fill="url(#cr-fill-risk)" />
							<path d={areaOf(won)} fill="url(#cr-fill-won)" />
							<path
								className="cr-chart__line is-risk"
								d={smoothPath(risk)}
							/>
							<path
								className="cr-chart__line is-won"
								d={smoothPath(won)}
							/>
						</g>

						{/* Date axis — thinned so labels never collide. */}
						{points.map((point, index) => {
							if (
								index % labelEvery !== 0 &&
								index !== points.length - 1
							) {
								return null;
							}

							return (
								<text
									key={point.date}
									className="cr-chart__axis"
									x={xAt(index)}
									y={HEIGHT - 10}
									textAnchor="middle"
								>
									{shortDate(point.date)}
								</text>
							);
						})}

						{active !== null && activePoint && (
							<g>
								<line
									className="cr-chart__crosshair"
									x1={xAt(active)}
									y1={PAD.top}
									x2={xAt(active)}
									y2={baseline}
								/>
								<circle
									className="cr-chart__dot is-risk"
									cx={xAt(active)}
									cy={yAt(activePoint.recoverable_revenue)}
									r="4"
								/>
								<circle
									className="cr-chart__dot is-won"
									cx={xAt(active)}
									cy={yAt(activePoint.recovered_revenue)}
									r="4"
								/>
							</g>
						)}

						<rect
							x={PAD.left}
							y={PAD.top}
							width={innerW}
							height={innerH}
							fill="transparent"
							onPointerMove={onMove}
							onPointerLeave={() => {
								setActive(null);
							}}
						/>
					</svg>
				)}

				{activePoint && (
					<div
						className="cr-chart__tip"
						style={{ left: tipLeft }}
						aria-hidden="true"
					>
						<p className="cr-chart__tipdate">
							{shortDate(activePoint.date)}
						</p>
						<p className="cr-chart__tiprow">
							<span className="cr-chart__swatch is-risk" />
							{formatMoney(
								activePoint.recoverable_revenue,
								currency
							)}
						</p>
						<p className="cr-chart__tiprow">
							<span className="cr-chart__swatch is-won" />
							{formatMoney(
								activePoint.recovered_revenue,
								currency
							)}
						</p>
					</div>
				)}
			</div>
		</div>
	);
};
