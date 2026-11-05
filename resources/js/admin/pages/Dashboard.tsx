import { Fragment, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { isAxiosError } from 'axios';
import { __, _n, sprintf } from '@wordpress/i18n';
import { AdminLink } from '../components/AdminLink';
import {
	MIN_DAY_SLOT,
	PLOT_GUTTER,
	RecoveryChart,
	RecoveryLegend,
} from '../components/RecoveryChart';
import { StatusBand } from '../components/StatusBand';
import {
	useCarts,
	useProductReport,
	useSettings,
	useStats,
	useTimeseries,
} from '../hooks/useApi';
import { useElementWidth } from '../hooks/useElementWidth';
import { messageOf } from '../lib/errors';
import { formatMoney, formatNumber } from '../lib/format';
import { bucketize, hasActivity, splitPeriods, tally } from '../lib/period';
import type { Tally } from '../lib/period';
import { formatAgo, formatMinutes } from '../lib/time';
import type { Cart, ProductReportRow, Settings, Stats } from '../types/api';

const RANGES = [7, 30, 90];
const WEEKLY_FROM = 60;
const OPEN_ROWS = 5;
const PRODUCT_ROWS = 5;

const InfoIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<circle cx="8" cy="8" r="6" stroke="currentColor" strokeWidth="1.3" />
		<path
			d="M8 7.3v3.3"
			stroke="currentColor"
			strokeWidth="1.4"
			strokeLinecap="round"
		/>
		<circle cx="8" cy="5.2" r="0.85" fill="currentColor" />
	</svg>
);

const ArrowIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<path
			d="M3.5 8h9M9 4.5 12.5 8 9 11.5"
			stroke="currentColor"
			strokeWidth="1.4"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const TrendIcon = ({ up }: { up: boolean }) => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<path
			d={
				up
					? 'M8 12.5v-9M4.5 7 8 3.5 11.5 7'
					: 'M8 3.5v9M4.5 9 8 12.5 11.5 9'
			}
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const SetupIcon = ({ tone }: { tone: SetupTone }) => {
	if (tone === 'ok') {
		return (
			<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
				<circle cx="8" cy="8" r="7" fill="currentColor" />
				<path
					d="m5.2 8.2 1.9 1.9 3.7-4"
					stroke="#fff"
					strokeWidth="1.6"
					strokeLinecap="round"
					strokeLinejoin="round"
				/>
			</svg>
		);
	}

	if (tone === 'warn') {
		return (
			<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
				<circle cx="8" cy="8" r="7" fill="currentColor" />
				<path
					d="M8 4.6v4.2"
					stroke="#fff"
					strokeWidth="1.7"
					strokeLinecap="round"
				/>
				<circle cx="8" cy="11.3" r="1" fill="#fff" />
			</svg>
		);
	}

	if (tone === 'off') {
		return (
			<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
				<circle
					cx="8"
					cy="8"
					r="6.2"
					stroke="currentColor"
					strokeWidth="1.6"
				/>
				<path
					d="M5.4 8h5.2"
					stroke="currentColor"
					strokeWidth="1.6"
					strokeLinecap="round"
				/>
			</svg>
		);
	}

	return (
		<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
			<circle
				cx="8"
				cy="8"
				r="6.2"
				stroke="currentColor"
				strokeWidth="1.6"
			/>
			<path
				d="M8 5v3.2l2.2 1.4"
				stroke="currentColor"
				strokeWidth="1.6"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
};

const Hint = ({ text }: { text: string }) => {
	const ref = useRef<HTMLSpanElement>(null);
	const [alignEnd, setAlignEnd] = useState(false);

	const place = () => {
		const rect = ref.current?.getBoundingClientRect();

		if (rect) {
			setAlignEnd(
				rect.left + rect.width / 2 >
					document.documentElement.clientWidth / 2
			);
		}
	};

	return (
		<span
			ref={ref}
			className="cr-hint"
			onMouseEnter={place}
			onFocus={place}
		>
			<button type="button" className="cr-hint__btn" aria-label={text}>
				<InfoIcon />
			</button>
			<span
				className={`cr-hint__bubble${alignEnd ? ' is-end' : ''}`}
				aria-hidden="true"
			>
				{text}
			</span>
		</span>
	);
};

const interpolate = (template: string, values: ReactNode[]): ReactNode => {
	let next = 0;

	return template.split(/(%(?:\d+\$)?s)/).map((chunk, index) => {
		const slot = /^%(?:(\d+)\$)?s$/.exec(chunk);
		let value: ReactNode = chunk;

		if (slot) {
			value = values[slot[1] ? Number(slot[1]) - 1 : next++];
		}

		return <Fragment key={index}>{value}</Fragment>;
	});
};

const cartCount = (count: number): string =>
	sprintf(
		/* translators: %s: number of carts. */
		_n('%s cart', '%s carts', count, 'cart-rebound'),
		formatNumber(count, 0)
	);

const lastDays = (days: number): string =>
	sprintf(
		/* translators: %d: number of days. */
		_n('the last %d day', 'the last %d days', days, 'cart-rebound'),
		days
	);

const previousDays = (days: number): string =>
	sprintf(
		/* translators: %d: number of days. */
		_n('the previous %d day', 'the previous %d days', days, 'cart-rebound'),
		days
	);

const customerOf = (cart: Cart): string => {
	const name = `${cart.first_name} ${cart.last_name}`.trim();

	if (name !== '') {
		return name;
	}

	return cart.email !== '' ? cart.email : __('Guest', 'cart-rebound');
};

const Skeleton = ({ width, height }: { width: string; height: number }) => (
	<div className="cr-skeleton" style={{ width, height }} />
);

const ViewAll = ({
	to,
	state,
	children,
}: {
	to: string;
	state?: unknown;
	children: ReactNode;
}) => (
	<AdminLink className="cr-viewall" to={to} state={state}>
		{children}
		<ArrowIcon />
	</AdminLink>
);

const PeriodPicker = ({
	days,
	onChange,
}: {
	days: number;
	onChange: (next: number) => void;
}) => (
	<div
		className="cr-period"
		role="group"
		aria-label={__('Period', 'cart-rebound')}
	>
		{RANGES.map((option) => (
			<button
				key={option}
				type="button"
				className={
					option === days
						? 'cr-period__btn is-active'
						: 'cr-period__btn'
				}
				aria-pressed={option === days}
				onClick={() => {
					onChange(option);
				}}
			>
				{sprintf(
					/* translators: %d: number of days. */
					_n('%d day', '%d days', option, 'cart-rebound'),
					option
				)}
			</button>
		))}
	</div>
);

const Comparison = ({
	current,
	previous,
	days,
	currency,
}: {
	current: Tally;
	previous: Tally;
	days: number;
	currency: string;
}) => {
	if (
		!hasActivity(previous) ||
		current.recovered + previous.recovered === 0
	) {
		return null;
	}

	const diff = current.recoveredValue - previous.recoveredValue;

	if (Math.abs(diff) < 0.005) {
		return (
			<span className="cr-delta">
				{sprintf(
					/* translators: %s: comparison period, e.g. "the previous 30 days". */
					__('That is the same as %s.', 'cart-rebound'),
					previousDays(days)
				)}{' '}
			</span>
		);
	}

	const up = diff > 0;

	return (
		<span className={up ? 'cr-delta is-up' : 'cr-delta is-down'}>
			<TrendIcon up={up} />
			{up
				? sprintf(
						/* translators: 1: amount of money, 2: comparison period, e.g. "the previous 30 days". */
						__('That is %1$s more than %2$s.', 'cart-rebound'),
						formatMoney(diff, currency),
						previousDays(days)
					)
				: sprintf(
						/* translators: 1: amount of money, 2: comparison period, e.g. "the previous 30 days". */
						__('That is %1$s less than %2$s.', 'cart-rebound'),
						formatMoney(-diff, currency),
						previousDays(days)
					)}{' '}
		</span>
	);
};

const OpenNow = ({ stats }: { stats: Stats }) => {
	const open = stats.counts.abandoned ?? 0;

	if (open === 0) {
		return (
			<>
				{__(
					'No abandoned carts are waiting right now.',
					'cart-rebound'
				)}
			</>
		);
	}

	return (
		<>
			{interpolate(
				/* translators: 1: number of carts, e.g. "3 carts", 2: their combined value. */
				__(
					'Right now, %1$s worth %2$s can still be recovered.',
					'cart-rebound'
				),
				[
					cartCount(open),
					<strong key="value">
						{formatMoney(stats.recoverable_revenue, stats.currency)}
					</strong>,
				]
			)}
		</>
	);
};

const Headline = ({
	stats,
	current,
	previous,
	days,
	trackingOff,
}: {
	stats: Stats;
	current: Tally;
	previous: Tally;
	days: number;
	trackingOff: boolean;
}) => {
	const currency = stats.currency;

	if (current.recovered > 0) {
		return (
			<>
				<p className="cr-summary__lead">
					{interpolate(
						/* translators: 1: money won back, 2: number of carts, e.g. "9 carts", 3: period, e.g. "the last 30 days". */
						__(
							'You won back %1$s from %2$s in %3$s.',
							'cart-rebound'
						),
						[
							<strong key="value">
								{formatMoney(current.recoveredValue, currency)}
							</strong>,
							<strong key="carts">
								{cartCount(current.recovered)}
							</strong>,
							lastDays(days),
						]
					)}
				</p>
				<p className="cr-summary__detail">
					<Comparison
						current={current}
						previous={previous}
						days={days}
						currency={currency}
					/>
					<OpenNow stats={stats} />
				</p>
			</>
		);
	}

	if (current.abandoned > 0) {
		return (
			<>
				<p className="cr-summary__lead">
					{sprintf(
						/* translators: %s: period, e.g. "the last 30 days". */
						__('Nothing won back in %s yet.', 'cart-rebound'),
						lastDays(days)
					)}
				</p>
				<p className="cr-summary__detail">
					<Comparison
						current={current}
						previous={previous}
						days={days}
						currency={currency}
					/>
					{interpolate(
						/* translators: 1: number of carts, e.g. "3 carts", 2: their combined value. */
						_n(
							'%1$s worth %2$s was abandoned in that time.',
							'%1$s worth %2$s were abandoned in that time.',
							current.abandoned,
							'cart-rebound'
						),
						[
							cartCount(current.abandoned),
							<strong key="value">
								{formatMoney(current.abandonedValue, currency)}
							</strong>,
						]
					)}{' '}
					<OpenNow stats={stats} />
				</p>
			</>
		);
	}

	return (
		<>
			<p className="cr-summary__lead">
				{sprintf(
					/* translators: %s: period, e.g. "the last 30 days". */
					__('No carts were abandoned in %s.', 'cart-rebound'),
					lastDays(days)
				)}
			</p>
			<p className="cr-summary__detail">
				{trackingOff
					? __(
							'Cart tracking is off, so new carts are not being recorded.',
							'cart-rebound'
						)
					: __(
							'When a shopper leaves without paying, the cart shows up here.',
							'cart-rebound'
						)}
			</p>
		</>
	);
};

const RecordFacts = ({ stats }: { stats: Stats }) => {
	const total = Object.values(stats.counts).reduce(
		(sum, count) => sum + count,
		0
	);

	return (
		<div className="cr-summary__record">
			<p className="cr-summary__recordtitle">
				{interpolate(
					/* translators: %s: number of carts. */
					_n(
						'%s cart on record',
						'%s carts on record',
						total,
						'cart-rebound'
					),
					[<strong key="total">{formatNumber(total, 0)}</strong>]
				)}
			</p>
			<p className="cr-summary__facts">
				<span className="cr-summary__fact">
					{interpolate(
						/* translators: %s: recovery rate, e.g. 12.5%. */
						__('All-time recovery rate %s', 'cart-rebound'),
						[
							<strong key="rate">
								{sprintf(
									/* translators: %s: recovery rate as a number, e.g. 12.5. */
									__('%1$s%%', 'cart-rebound'),
									formatNumber(stats.recovery_rate)
								)}
							</strong>,
						]
					)}
					<Hint
						text={__(
							'Share of all abandoned carts that were recovered, measured over the plugin’s lifetime so cleanup cannot inflate it.',
							'cart-rebound'
						)}
					/>
				</span>
				<span className="cr-summary__fact">
					{interpolate(
						/* translators: %s: amount of money. */
						__('%s won back from these carts', 'cart-rebound'),
						[
							<strong key="won">
								{formatMoney(
									stats.recovered_revenue,
									stats.currency
								)}
							</strong>,
						]
					)}
					<Hint
						text={__(
							'Paid order value from recovered carts still on record. Converted carts are removed after the retention period in Settings.',
							'cart-rebound'
						)}
					/>
				</span>
			</p>
		</div>
	);
};

const SummaryPanel = ({
	stats,
	series,
	days,
	onDays,
	trackingOff,
}: {
	stats: Stats | undefined;
	series: ReturnType<typeof useTimeseries>;
	days: number;
	onDays: (next: number) => void;
	trackingOff: boolean;
}) => {
	const shownDays = series.data
		? Math.max(1, Math.floor(series.data.length / 2))
		: days;
	const periods = series.data ? splitPeriods(series.data, shownDays) : null;

	let text: ReactNode;

	if (series.isError) {
		text = (
			<>
				<p className="cr-summary__lead">
					{__(
						'Could not load the numbers for this period.',
						'cart-rebound'
					)}
				</p>
				<p className="cr-summary__detail">{messageOf(series.error)}</p>
			</>
		);
	} else if (!stats || !periods) {
		text = (
			<div className="cr-summary__loading">
				<Skeleton width="78%" height={26} />
				<Skeleton width="56%" height={26} />
				<Skeleton width="64%" height={14} />
			</div>
		);
	} else {
		text = (
			<Headline
				stats={stats}
				current={tally(periods.current)}
				previous={tally(periods.previous)}
				days={shownDays}
				trackingOff={trackingOff}
			/>
		);
	}

	return (
		<section
			className="cr-card cr-summary"
			aria-labelledby="cr-summary-title"
		>
			<h2 id="cr-summary-title" className="cr-sr-only">
				{__('Recovery summary', 'cart-rebound')}
			</h2>

			<div className="cr-summary__top">
				<div
					className={
						series.isPlaceholderData
							? 'cr-summary__text is-stale'
							: 'cr-summary__text'
					}
					aria-busy={series.isPlaceholderData}
				>
					{text}
				</div>
				<PeriodPicker days={days} onChange={onDays} />
			</div>

			<div className="cr-summary__band">
				{stats ? (
					<>
						<RecordFacts stats={stats} />
						<StatusBand counts={stats.counts} />
					</>
				) : (
					<>
						<Skeleton width="40%" height={14} />
						<Skeleton width="100%" height={28} />
						<Skeleton width="70%" height={14} />
					</>
				)}
			</div>
		</section>
	);
};

const TrendPanel = ({
	series,
	currency,
}: {
	series: ReturnType<typeof useTimeseries>;
	currency: string;
}) => {
	const shownDays = series.data
		? Math.max(1, Math.floor(series.data.length / 2))
		: 0;
	const { ref, width } = useElementWidth();
	const narrow =
		width > 0 &&
		shownDays > 0 &&
		(width - PLOT_GUTTER) / shownDays < MIN_DAY_SLOT;
	const weekly = shownDays >= WEEKLY_FROM || narrow;
	const buckets = series.data
		? bucketize(
				splitPeriods(series.data, shownDays).current,
				weekly ? 7 : 1
			)
		: [];
	const charted = buckets.some(
		(bucket) => bucket.abandonedValue > 0 || bucket.recoveredValue > 0
	);
	let body: ReactNode;

	if (series.isError) {
		body = (
			<div className="cr-notice is-error" role="alert">
				{sprintf(
					/* translators: %s: error message. */
					__('Could not load the chart: %s', 'cart-rebound'),
					messageOf(series.error)
				)}
			</div>
		);
	} else if (!series.data) {
		body = <Skeleton width="100%" height={248} />;
	} else {
		body = (
			<RecoveryChart
				buckets={buckets}
				currency={currency}
				unit={weekly ? 'week' : 'day'}
			/>
		);
	}

	return (
		<section className="cr-card cr-panel" aria-labelledby="cr-trend-title">
			<header className="cr-panel__head">
				<div>
					<h2 id="cr-trend-title" className="cr-panel__title">
						{weekly
							? __(
									'Abandoned and won back, by week',
									'cart-rebound'
								)
							: __(
									'Abandoned and won back, by day',
									'cart-rebound'
								)}
					</h2>
					<p className="cr-panel__sub">
						{__(
							'Cart value on the day it was abandoned, and order value on the day it was won back.',
							'cart-rebound'
						)}
					</p>
				</div>
				{charted && <RecoveryLegend />}
			</header>
			<div
				className={
					series.isPlaceholderData
						? 'cr-panel__body is-stale'
						: 'cr-panel__body'
				}
				aria-busy={series.isPlaceholderData}
			>
				<div ref={ref}>{body}</div>
			</div>
		</section>
	);
};

const productName = (row: ProductReportRow): string =>
	row.name !== ''
		? row.name
		: sprintf(
				/* translators: %d: product id. */
				__('Product #%d', 'cart-rebound'),
				row.product_id
			);

const ProductsPanel = ({
	days,
	products,
}: {
	days: number;
	products: ReturnType<typeof useProductReport>;
}) => {
	const rows = products.data ?? [];
	const top = Math.max(1, ...rows.map((row) => row.abandoned));
	let body: ReactNode;

	if (products.isError) {
		body = (
			<p className="cr-panel__empty">
				{__('Could not load the product report.', 'cart-rebound')}
			</p>
		);
	} else if (products.isLoading) {
		body = (
			<div className="cr-products is-loading">
				{Array.from({ length: 4 }, (_unused, index) => (
					<Skeleton key={index} width="100%" height={30} />
				))}
			</div>
		);
	} else if (rows.length === 0) {
		body = (
			<p className="cr-panel__empty">
				{__(
					'No products were left in abandoned carts in this period.',
					'cart-rebound'
				)}
			</p>
		);
	} else {
		body = (
			<ol className="cr-products">
				{rows.map((row) => (
					<li key={row.product_id} className="cr-products__row">
						<span
							className="cr-products__name"
							title={productName(row)}
						>
							{productName(row)}
						</span>
						<p className="cr-products__nums">
							{interpolate(
								/* translators: 1: number of abandoned carts holding the product, 2: number of carts won back holding it. */
								__(
									'%1$s abandoned · %2$s won back',
									'cart-rebound'
								),
								[
									<strong key="lost">
										{formatNumber(row.abandoned, 0)}
									</strong>,
									formatNumber(row.recovered, 0),
								]
							)}
						</p>
						<span className="cr-products__track" aria-hidden="true">
							<span
								className="cr-products__fill"
								style={{
									width: `${(row.abandoned / top) * 100}%`,
								}}
							/>
						</span>
					</li>
				))}
			</ol>
		);
	}

	return (
		<section
			className="cr-card cr-panel"
			aria-labelledby="cr-products-title"
		>
			<header className="cr-panel__head">
				<div>
					<h2 id="cr-products-title" className="cr-panel__title">
						{__('Products left in abandoned carts', 'cart-rebound')}
					</h2>
					<p className="cr-panel__sub">
						{sprintf(
							/* translators: %d: number of days. */
							_n(
								'Carts holding each product, last %d day',
								'Carts holding each product, last %d days',
								days,
								'cart-rebound'
							),
							days
						)}
					</p>
				</div>
			</header>
			<div
				className={
					products.isPlaceholderData
						? 'cr-panel__body is-stale'
						: 'cr-panel__body'
				}
				aria-busy={products.isPlaceholderData}
			>
				{body}
			</div>
		</section>
	);
};

const OpenCartsPanel = ({ openCount }: { openCount: number | undefined }) => {
	const { data, isLoading, isError } = useCarts({
		status: 'abandoned',
		email: '',
		page: 1,
		per_page: OPEN_ROWS,
		orderby: 'cart_total',
		order: 'desc',
	});
	const items = data?.items ?? [];
	let body: ReactNode;

	if (isError) {
		body = (
			<p className="cr-panel__empty">
				{__('Could not load open carts.', 'cart-rebound')}
			</p>
		);
	} else if (isLoading) {
		body = (
			<div className="cr-open is-loading">
				{Array.from({ length: 4 }, (_unused, index) => (
					<Skeleton key={index} width="100%" height={36} />
				))}
			</div>
		);
	} else if (items.length === 0) {
		body = (
			<p className="cr-panel__empty">
				{__(
					'Nothing to chase right now. Abandoned carts appear here, biggest first, until they are won back or cleaned up.',
					'cart-rebound'
				)}
			</p>
		);
	} else {
		body = (
			<ul className="cr-open">
				{items.map((cart) => (
					<li key={cart.id}>
						<AdminLink
							to="/carts"
							state={{ status: 'abandoned', search: cart.email }}
							className="cr-open__row"
						>
							<span className="cr-open__who">
								{customerOf(cart)}
							</span>
							<span className="cr-open__value">
								{formatMoney(
									cart.cart_total,
									cart.currency || (data?.currency ?? '')
								)}
							</span>
							<span className="cr-open__meta">
								<span>
									{sprintf(
										/* translators: %d: number of items in the cart. */
										_n(
											'%d item',
											'%d items',
											cart.items_count,
											'cart-rebound'
										),
										cart.items_count
									)}
								</span>{' '}
								<span
									className="cr-open__dot"
									aria-hidden="true"
								>
									·
								</span>{' '}
								<span>
									{sprintf(
										/* translators: %s: how long ago, e.g. "2 days ago". */
										__('abandoned %s', 'cart-rebound'),
										formatAgo(
											cart.abandoned_at ||
												cart.last_activity
										)
									)}
								</span>
							</span>
						</AdminLink>
					</li>
				))}
			</ul>
		);
	}

	return (
		<section className="cr-card cr-panel" aria-labelledby="cr-open-title">
			<header className="cr-panel__head">
				<div>
					<h2 id="cr-open-title" className="cr-panel__title">
						{__('Worth chasing', 'cart-rebound')}
					</h2>
					<p className="cr-panel__sub">
						{__('Open carts, biggest first', 'cart-rebound')}
					</p>
				</div>
			</header>
			{body}
			{openCount !== undefined && openCount > 0 && (
				<footer className="cr-panel__foot">
					<ViewAll
						to="/carts"
						state={{ status: 'abandoned', orderby: 'cart_total' }}
					>
						{sprintf(
							/* translators: %s: number of open carts. */
							_n(
								'View %s open cart',
								'View all %s open carts',
								openCount,
								'cart-rebound'
							),
							formatNumber(openCount, 0)
						)}
					</ViewAll>
				</footer>
			)}
		</section>
	);
};

type SetupTone = 'ok' | 'warn' | 'off' | 'info';

interface SetupItem {
	key: string;
	tone: SetupTone;
	title: string;
	detail: string;
	action?: { label: string; to: string };
}

const setupItems = (settings: Settings): SetupItem[] => {
	const items: SetupItem[] = [];

	if (settings.tracking_enabled) {
		items.push({
			key: 'tracking',
			tone: 'ok',
			title: __('Cart tracking is on', 'cart-rebound'),
			detail: settings.guest_tracking
				? __(
						'Carts of logged-in shoppers and guests are recorded.',
						'cart-rebound'
					)
				: __(
						'Carts of logged-in shoppers are recorded.',
						'cart-rebound'
					),
		});
	} else {
		items.push({
			key: 'tracking',
			tone: 'warn',
			title: __('Cart tracking is off', 'cart-rebound'),
			detail: __(
				'New carts are not recorded, so none can be recovered.',
				'cart-rebound'
			),
			action: { label: __('Turn on', 'cart-rebound'), to: '/settings' },
		});
	}

	if (settings.tracking_enabled && !settings.guest_tracking) {
		items.push({
			key: 'guests',
			tone: 'off',
			title: __('Guest carts are not tracked', 'cart-rebound'),
			detail: __(
				'Off by default for privacy. Shoppers who are not logged in are left out.',
				'cart-rebound'
			),
			action: { label: __('Change', 'cart-rebound'), to: '/settings' },
		});
	}

	if (settings.recovery_email_enabled) {
		items.push({
			key: 'email',
			tone: 'ok',
			title: __('Recovery email is on', 'cart-rebound'),
			detail:
				settings.email_delay_minutes > 0
					? sprintf(
							/* translators: %s: a duration, e.g. "1 hour". */
							__(
								'Sent %s after a cart is abandoned.',
								'cart-rebound'
							),
							formatMinutes(settings.email_delay_minutes)
						)
					: __(
							'Sent as soon as a cart is abandoned.',
							'cart-rebound'
						),
			action: {
				label: __('Edit email', 'cart-rebound'),
				to: '/templates',
			},
		});
	} else {
		items.push({
			key: 'email',
			tone: 'warn',
			title: __('Recovery email is off', 'cart-rebound'),
			detail: __(
				'Shoppers are not emailed a link back to their cart.',
				'cart-rebound'
			),
			action: { label: __('Turn on', 'cart-rebound'), to: '/settings' },
		});
	}

	items.push({
		key: 'threshold',
		tone: 'info',
		title: sprintf(
			/* translators: %s: a duration, e.g. "15 minutes". */
			__('Carts count as abandoned after %s', 'cart-rebound'),
			formatMinutes(settings.abandonment_threshold)
		),
		detail: __(
			'How long a cart can sit untouched before recovery starts.',
			'cart-rebound'
		),
		action: { label: __('Change', 'cart-rebound'), to: '/settings' },
	});

	return items;
};

const SetupPanel = ({
	settings,
}: {
	settings: ReturnType<typeof useSettings>;
}) => {
	let body: ReactNode;

	if (settings.isError) {
		body = (
			<p className="cr-panel__empty">
				{__('Could not load your settings.', 'cart-rebound')}
			</p>
		);
	} else if (!settings.data) {
		body = (
			<div className="cr-setup is-loading">
				{Array.from({ length: 3 }, (_unused, index) => (
					<Skeleton key={index} width="100%" height={36} />
				))}
			</div>
		);
	} else {
		body = (
			<ul className="cr-setup">
				{setupItems(settings.data).map((item) => (
					<li
						key={item.key}
						className={`cr-setup__item is-${item.tone}`}
					>
						<span className="cr-setup__icon">
							<SetupIcon tone={item.tone} />
						</span>
						<div className="cr-setup__text">
							<p className="cr-setup__title">{item.title}</p>
							<p className="cr-setup__detail">{item.detail}</p>
						</div>
						{item.action && (
							<AdminLink
								to={item.action.to}
								className="cr-setup__action"
							>
								{item.action.label}
							</AdminLink>
						)}
					</li>
				))}
			</ul>
		);
	}

	return (
		<section className="cr-card cr-panel" aria-labelledby="cr-setup-title">
			<header className="cr-panel__head">
				<div>
					<h2 id="cr-setup-title" className="cr-panel__title">
						{__('Recovery setup', 'cart-rebound')}
					</h2>
					<p className="cr-panel__sub">
						{__(
							'What decides which carts you can win back',
							'cart-rebound'
						)}
					</p>
				</div>
			</header>
			{body}
		</section>
	);
};

export const Dashboard = () => {
	const [days, setDays] = useState(30);
	const stats = useStats();
	const series = useTimeseries(days * 2);
	const products = useProductReport(days, PRODUCT_ROWS);
	const settings = useSettings();

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
			<div className="cr-dash__grid">
				<div className="cr-dash__main">
					<SummaryPanel
						stats={stats.data}
						series={series}
						days={days}
						onDays={setDays}
						trackingOff={settings.data?.tracking_enabled === false}
					/>
					<TrendPanel
						series={series}
						currency={stats.data?.currency ?? ''}
					/>
					<ProductsPanel days={days} products={products} />
				</div>
				<div className="cr-dash__rail">
					<OpenCartsPanel openCount={stats.data?.counts.abandoned} />
					<SetupPanel settings={settings} />
				</div>
			</div>
		</div>
	);
};
