/**
 * Shared formatting helpers for the admin UI.
 */
import { __, sprintf, _n } from '@wordpress/i18n';

/**
 * Say a delay the way a merchant would say it.
 *
 * Every follow-up delay is stored in minutes because that is what the scheduler
 * needs, but "4320 minutes" is not a schedule anyone can picture. Rounding to
 * the largest whole unit — and only when it divides cleanly, so 90 minutes does
 * not become a misleading "1 hour" — keeps the number honest and readable.
 * @param minutes Delay in minutes after abandonment.
 */
export const formatDelay = (minutes: number): string => {
	const safe = Math.max(1, Math.round(minutes));

	if (safe % 1440 === 0) {
		const days = safe / 1440;

		return sprintf(
			/* translators: %d: number of days. */
			_n(
				'%d day after abandonment',
				'%d days after abandonment',
				days,
				'cart-rebound'
			),
			days
		);
	}

	if (safe % 60 === 0) {
		const hours = safe / 60;

		return sprintf(
			/* translators: %d: number of hours. */
			_n(
				'%d hour after abandonment',
				'%d hours after abandonment',
				hours,
				'cart-rebound'
			),
			hours
		);
	}

	return sprintf(
		/* translators: %d: number of minutes. */
		_n(
			'%d minute after abandonment',
			'%d minutes after abandonment',
			safe,
			'cart-rebound'
		),
		safe
	);
};

/**
 * Render a percentage with one decimal, dropping a pointless trailing zero.
 * @param value Percentage value, already scaled to 0–100.
 */
export const formatPercent = (value: number): string => {
	const rounded = Math.round(value * 10) / 10;

	return `${rounded % 1 === 0 ? rounded.toFixed(0) : rounded.toFixed(1)}%`;
};

/**
 * Render a whole number with locale-aware grouping.
 * @param value The number.
 */
export const formatCount = (value: number): string =>
	new Intl.NumberFormat().format(Math.round(value));

/**
 * Render a short date for an axis or a table cell.
 * @param iso An ISO `YYYY-MM-DD` date.
 */
export const formatShortDate = (iso: string): string => {
	const date = new Date(`${iso}T00:00:00`);

	if (Number.isNaN(date.getTime())) {
		return iso;
	}

	return new Intl.DateTimeFormat(undefined, {
		month: 'short',
		day: 'numeric',
	}).format(date);
};

/**
 * Render hours as a duration a person would say out loud.
 * @param hours Duration in hours.
 */
export const formatHours = (hours: number): string => {
	if (hours < 1) {
		const minutes = Math.max(1, Math.round(hours * 60));

		return sprintf(
			/* translators: %d: number of minutes. */
			_n('%d min', '%d min', minutes, 'cart-rebound'),
			minutes
		);
	}

	if (hours < 48) {
		return sprintf(
			/* translators: %s: number of hours, possibly fractional. */
			__('%s h', 'cart-rebound'),
			(Math.round(hours * 10) / 10).toString()
		);
	}

	const days = Math.round((hours / 24) * 10) / 10;

	return sprintf(
		/* translators: %s: number of days, possibly fractional. */
		__('%s d', 'cart-rebound'),
		days.toString()
	);
};

/**
 * Render an amount the way WooCommerce renders prices on the storefront.
 *
 * Intl picks separators and symbol placement from the browser locale, which
 * drifts from what the shop owner configured — a store selling in EUR with a
 * comma decimal would still read `€1,234.50` to an en-US admin. The boot data
 * carries the store's own settings so both surfaces agree.
 * @param amount
 * @param format
 */
const formatWooCommerceMoney = (
	amount: number,
	format: CartReboundBootData['currency']
): string => {
	const decimals = Math.max(0, Math.min(20, format.decimals));
	const [integer = '0', fraction = ''] = Math.abs(amount)
		.toFixed(decimals)
		.split('.');
	const grouped = integer.replace(
		/\B(?=(\d{3})+(?!\d))/g,
		format.thousandSeparator
	);
	const number =
		decimals > 0
			? `${grouped}${format.decimalSeparator}${fraction}`
			: grouped;
	const symbol = format.symbol !== '' ? format.symbol : format.code;
	// Non-breaking, matching WooCommerce: a price must never wrap away from its
	// symbol at the end of a table cell.
	const space = ' ';
	let price: string;

	switch (format.position) {
		case 'right':
			price = `${number}${symbol}`;
			break;
		case 'left_space':
			price = `${symbol}${space}${number}`;
			break;
		case 'right_space':
			price = `${number}${space}${symbol}`;
			break;
		case 'left':
		default:
			price = `${symbol}${number}`;
			break;
	}

	return amount < 0 ? `-${price}` : price;
};

/**
 * Format a monetary amount using the store's WooCommerce price settings when
 * possible, falling back gracefully when the currency is unknown.
 * @param amount
 * @param currency
 */
export const formatMoney = (amount: number, currency: string): string => {
	if (currency === '') {
		return amount.toFixed(2);
	}

	const wooCurrency = window.CartRebound?.currency;

	// Only trust the store settings for the store's own currency; a historical
	// row in another currency must not borrow this symbol.
	if (wooCurrency?.code === currency) {
		return formatWooCommerceMoney(amount, wooCurrency);
	}

	try {
		return new Intl.NumberFormat(undefined, {
			style: 'currency',
			currency,
		}).format(amount);
	} catch {
		return `${amount.toFixed(2)} ${currency}`;
	}
};
