/**
 * The store shown behind a locked Pro screen.
 *
 * A locked screen renders the real interface over this data rather than an
 * illustration of it, so what a merchant evaluates is the thing they would
 * actually use. That only works if the numbers hold up to being read: a flat
 * line, a round 50%, or three identical rows all announce "placeholder" and
 * undo the point of showing the screen at all.
 *
 * So the figures here are shaped like a real small store's month — a weekly
 * rhythm, a recovery rate in the teens, a sequence whose first step does most
 * of the work and whose coupon step converts best. Values are derived from the
 * day itself, never random, so the preview does not reshuffle as you look at it.
 */
import { __ } from '@wordpress/i18n';
import type {
	AnalyticsPoint,
	AnalyticsProductRow,
	AnalyticsResponse,
	LicenseState,
	ProOptions,
	ProSettings,
	SequenceOverview,
	StepPerformanceRow,
} from '../types/api';

const DAY_MS = 86_400_000;

/**
 * A stable 0–1 value for a given day and series.
 *
 * Deterministic on purpose: a preview that changed shape between renders would
 * read as broken, and a chart that reflowed under the lock panel would be worse
 * than no chart.
 * @param day    Position in the window.
 * @param series Which quantity is being generated, so two series on one day
 *               do not move in lockstep.
 */
const noise = (day: number, series: number): number => {
	const value = Math.sin(day * 12.9898 + series * 78.233) * 43_758.545;

	return value - Math.floor(value);
};

const isoDate = (offsetDays: number): string => {
	const date = new Date(Date.now() - offsetDays * DAY_MS);

	return date.toISOString().slice(0, 10);
};

const sampleTimeseries = (days: number): AnalyticsPoint[] => {
	const points: AnalyticsPoint[] = [];

	for (let offset = days - 1; offset >= 0; offset -= 1) {
		const date = new Date(Date.now() - offset * DAY_MS);
		const weekday = date.getDay();

		// Weekends run lighter, which is what makes the line read as a real
		// store rather than a generated ramp.
		const weekly = weekday === 0 || weekday === 6 ? 0.62 : 1;
		const day = days - offset;

		const abandoned = Math.round((7 + noise(day, 1) * 9) * weekly);
		const recovered = Math.max(
			0,
			Math.round(abandoned * (0.11 + noise(day, 2) * 0.14))
		);
		const averageCart = 74 + noise(day, 3) * 46;

		points.push({
			date: date.toISOString().slice(0, 10),
			abandoned,
			abandoned_value: Math.round(abandoned * averageCart * 100) / 100,
			recovered,
			recovered_revenue:
				Math.round(recovered * (averageCart + 12) * 100) / 100,
		});
	}

	return points;
};

const sampleSteps: StepPerformanceRow[] = [
	{
		step: 1,
		sent: 412,
		opened: 197,
		clicked: 64,
		recovered: 21,
		revenue: 1943.4,
		open_rate: 47.8,
		click_rate: 15.5,
		recovery_rate: 5.1,
	},
	{
		step: 2,
		sent: 351,
		opened: 141,
		clicked: 43,
		recovered: 14,
		revenue: 1312.75,
		open_rate: 40.2,
		click_rate: 12.3,
		recovery_rate: 4,
	},
	{
		step: 3,
		sent: 298,
		opened: 129,
		clicked: 58,
		recovered: 23,
		revenue: 2278.1,
		open_rate: 43.3,
		click_rate: 19.5,
		recovery_rate: 7.7,
	},
];

const sampleProducts: AnalyticsProductRow[] = [
	{
		product_id: 1,
		name: __('Merino Crew Sweater', 'cart-rebound'),
		abandoned: 63,
		recovered: 11,
		lost_value: 4108.0,
	},
	{
		product_id: 2,
		name: __('Canvas Weekender Bag', 'cart-rebound'),
		abandoned: 47,
		recovered: 9,
		lost_value: 3572.5,
	},
	{
		product_id: 3,
		name: __('Ceramic Pour-Over Set', 'cart-rebound'),
		abandoned: 41,
		recovered: 12,
		lost_value: 1804.75,
	},
	{
		product_id: 4,
		name: __('Leather Card Holder', 'cart-rebound'),
		abandoned: 38,
		recovered: 4,
		lost_value: 1102.0,
	},
	{
		product_id: 5,
		name: __('Linen Pillow Cover', 'cart-rebound'),
		abandoned: 29,
		recovered: 7,
		lost_value: 812.3,
	},
];

/**
 * Build the sample analytics response for a window of days.
 *
 * @param days     Length of the window.
 * @param currency Store currency code, so the preview reads in the merchant's
 *                 own money rather than a foreign one.
 */
export const sampleAnalytics = (
	days: number,
	currency: string
): AnalyticsResponse => {
	const timeseries = sampleTimeseries(days);

	const abandonedCarts = timeseries.reduce(
		(total, point) => total + point.abandoned,
		0
	);
	const abandonedValue = timeseries.reduce(
		(total, point) => total + point.abandoned_value,
		0
	);
	const recoveredCarts = timeseries.reduce(
		(total, point) => total + point.recovered,
		0
	);
	const recoveredRevenue = timeseries.reduce(
		(total, point) => total + point.recovered_revenue,
		0
	);

	const sent = sampleSteps.reduce((total, row) => total + row.sent, 0);
	const opened = sampleSteps.reduce((total, row) => total + row.opened, 0);
	const clicked = sampleSteps.reduce((total, row) => total + row.clicked, 0);

	return {
		summary: {
			from: isoDate(days - 1),
			to: isoDate(0),
			abandoned_carts: abandonedCarts,
			abandoned_value: Math.round(abandonedValue * 100) / 100,
			recovered_carts: recoveredCarts,
			recovered_revenue: Math.round(recoveredRevenue * 100) / 100,
			recovery_rate:
				abandonedCarts > 0
					? Math.round((recoveredCarts / abandonedCarts) * 1000) / 10
					: 0,
			average_order_value:
				recoveredCarts > 0
					? Math.round((recoveredRevenue / recoveredCarts) * 100) /
						100
					: 0,
			time_to_recovery: 6.4,
			emails_sent: sent,
			emails_opened: opened,
			emails_clicked: clicked,
			open_rate: Math.round((opened / sent) * 1000) / 10,
			click_rate: Math.round((clicked / sent) * 1000) / 10,
			currency,
			tracking_available: true,
		},
		timeseries,
		steps: sampleSteps,
		products: sampleProducts,
	};
};

/** The three-touch sequence most stores end up running. */
export const sampleSequence = (): SequenceOverview => ({
	running: true,
	steps: [
		{
			index: 0,
			enabled: true,
			delay_minutes: 60,
			delay_label: __('1 hour after abandonment', 'cart-rebound'),
			template_id: 'sample-reminder',
			template_name: __('Gentle reminder', 'cart-rebound'),
			template_ok: true,
			coupon: false,
			queued: 18,
			sent: 412,
		},
		{
			index: 1,
			enabled: true,
			delay_minutes: 1440,
			delay_label: __('1 day after abandonment', 'cart-rebound'),
			template_id: 'sample-social',
			template_name: __('Still interested?', 'cart-rebound'),
			template_ok: true,
			coupon: false,
			queued: 34,
			sent: 351,
		},
		{
			index: 2,
			enabled: true,
			delay_minutes: 4320,
			delay_label: __('3 days after abandonment', 'cart-rebound'),
			template_id: 'sample-coupon',
			template_name: __('Here is 10% off', 'cart-rebound'),
			template_ok: true,
			coupon: true,
			queued: 27,
			sent: 298,
		},
	],
	warnings: [],
});

export const sampleProSettings = (): ProSettings => ({
	sequence_steps: [
		{
			enabled: true,
			delay_minutes: 60,
			template_id: 'sample-reminder',
			coupon: false,
		},
		{
			enabled: true,
			delay_minutes: 1440,
			template_id: 'sample-social',
			coupon: false,
		},
		{
			enabled: true,
			delay_minutes: 4320,
			template_id: 'sample-coupon',
			coupon: true,
		},
	],

	coupon_auto: true,
	coupon_type: 'percent',
	coupon_amount: 10,
	coupon_expiry_hours: 48,
	coupon_min_amount: 25,
	coupon_restrict_email: true,
	coupon_free_shipping: false,
	coupon_prefix: 'REBOUND',

	tracking_opens: true,
	tracking_clicks: true,

	analytics_retention_days: 365,

	min_cart_total: 20,
	excluded_roles: ['administrator', 'shop_manager'],
	excluded_products: [],
	excluded_categories: [],
});

export const sampleProOptions = (): ProOptions => ({
	templates: [
		{
			id: 'sample-reminder',
			name: __('Gentle reminder', 'cart-rebound'),
			is_default: true,
		},
		{
			id: 'sample-social',
			name: __('Still interested?', 'cart-rebound'),
			is_default: false,
		},
		{
			id: 'sample-coupon',
			name: __('Here is 10% off', 'cart-rebound'),
			is_default: false,
		},
	],
	roles: [
		{ value: 'administrator', label: __('Administrator', 'cart-rebound') },
		{ value: 'shop_manager', label: __('Shop manager', 'cart-rebound') },
		{ value: 'customer', label: __('Customer', 'cart-rebound') },
		{ value: 'subscriber', label: __('Subscriber', 'cart-rebound') },
	],
	categories: [
		{ value: '12', label: __('Clothing', 'cart-rebound') },
		{ value: '18', label: __('Homeware', 'cart-rebound') },
		{ value: '24', label: __('Accessories', 'cart-rebound') },
		{ value: '31', label: __('Gift cards', 'cart-rebound') },
	],
});

export const sampleLicense = (): LicenseState => ({
	status: 'unlicensed',
	active: false,
	masked_key: '',
	message: '',
	expires_at: '',
	checked_at: '',
	site: '',
});
