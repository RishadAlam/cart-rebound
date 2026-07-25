/**
 * Shared formatting helpers for the admin UI.
 */

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
