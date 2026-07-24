/**
 * Shared formatting helpers for the admin UI.
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
	const space = '\u00a0';
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
