import { __, _n, sprintf } from '@wordpress/i18n';

const locale = (): string | undefined =>
	document.documentElement.lang || undefined;

const utcDay = (iso: string): Date => {
	const [year = 1970, month = 1, day = 1] = iso.split('-').map(Number);

	return new Date(Date.UTC(year, month - 1, day));
};

export const formatDay = (iso: string, withWeekday = false): string => {
	try {
		return new Intl.DateTimeFormat(locale(), {
			...(withWeekday ? { weekday: 'short' as const } : {}),
			month: 'short',
			day: 'numeric',
			timeZone: 'UTC',
		}).format(utcDay(iso));
	} catch {
		return iso;
	}
};

export const formatDayRange = (start: string, end: string): string =>
	start === end
		? formatDay(start, true)
		: sprintf(
				/* translators: 1: first day of a date range, 2: last day of the range. */
				__('%1$s – %2$s', 'cart-rebound'),
				formatDay(start),
				formatDay(end)
			);

const HOUR = 3600;
const DAY = 86400;

const UNITS: Array<[Intl.RelativeTimeFormatUnit, number, number]> = [
	['minute', 60, HOUR],
	['hour', HOUR, DAY],
	['day', DAY, DAY * 45],
	['month', DAY * 30, Infinity],
];

export const formatAgo = (utc: string, now = Date.now()): string => {
	const then = Date.parse(`${utc.replace(' ', 'T')}Z`);

	if (Number.isNaN(then)) {
		return '';
	}

	const seconds = Math.min(0, Math.round((then - now) / 1000));
	const elapsed = Math.abs(seconds);

	if (elapsed < 60) {
		return __('just now', 'cart-rebound');
	}

	const [unit, size] = UNITS.find(([, , below]) => elapsed < below) ?? [
		'month',
		DAY * 30,
	];
	const value = Math.round(seconds / size);

	try {
		return new Intl.RelativeTimeFormat(locale(), {
			numeric: 'auto',
		}).format(value, unit);
	} catch {
		return utc;
	}
};

export const formatMinutes = (minutes: number): string => {
	if (minutes > 0 && minutes % 1440 === 0) {
		const days = minutes / 1440;

		return sprintf(
			/* translators: %d: number of days. */
			_n('%d day', '%d days', days, 'cart-rebound'),
			days
		);
	}

	if (minutes > 0 && minutes % 60 === 0) {
		const hours = minutes / 60;

		return sprintf(
			/* translators: %d: number of hours. */
			_n('%d hour', '%d hours', hours, 'cart-rebound'),
			hours
		);
	}

	return sprintf(
		/* translators: %d: number of minutes. */
		_n('%d minute', '%d minutes', minutes, 'cart-rebound'),
		minutes
	);
};
