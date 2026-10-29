// Not Intl: it follows the browser locale, so an en-US admin would see `€1,234.50`
// for a store configured with a comma decimal. Mirror the store's own settings.
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
	// Non-breaking, as in WooCommerce: a price must never wrap away from its symbol.
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

// Plain numbers have no store setting to mirror, so follow the admin's locale
// (wp-admin sets <html lang> from the user's language).
export const formatNumber = (value: number, maximumFractionDigits = 1) => {
	try {
		return new Intl.NumberFormat(
			document.documentElement.lang || undefined,
			{
				maximumFractionDigits,
			}
		).format(value);
	} catch {
		return String(value);
	}
};
