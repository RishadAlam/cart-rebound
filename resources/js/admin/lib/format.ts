const withSymbol = (
	number: string,
	format: CartReboundBootData['currency']
): string => {
	const symbol = format.symbol !== '' ? format.symbol : format.code;
	// Non-breaking, as in WooCommerce: a price must never wrap away from its symbol.
	const space = ' ';

	switch (format.position) {
		case 'right':
			return `${number}${symbol}`;
		case 'left_space':
			return `${symbol}${space}${number}`;
		case 'right_space':
			return `${number}${space}${symbol}`;
		case 'left':
		default:
			return `${symbol}${number}`;
	}
};

// Not Intl: it follows the browser locale, so an en-US admin would see `€1,234.50`
// for a store configured with a comma decimal. Mirror the store's own settings.
const formatStoreNumber = (
	amount: number,
	decimals: number,
	format: CartReboundBootData['currency']
): string => {
	const [integer = '0', fraction = ''] = Math.abs(amount)
		.toFixed(decimals)
		.split('.');
	const grouped = integer.replace(
		/\B(?=(\d{3})+(?!\d))/g,
		format.thousandSeparator
	);

	return decimals > 0
		? `${grouped}${format.decimalSeparator}${fraction}`
		: grouped;
};

const formatWooCommerceMoney = (
	amount: number,
	format: CartReboundBootData['currency']
): string => {
	const decimals = Math.max(0, Math.min(20, format.decimals));
	const price = withSymbol(
		formatStoreNumber(amount, decimals, format),
		format
	);

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

const COMPACT_FROM = 10000;

const stepDecimals = (step: number): number => {
	for (let decimals = 0; decimals < 2; decimals++) {
		const scaled = step * 10 ** decimals;

		if (Math.abs(scaled - Math.round(scaled)) < 1e-9) {
			return decimals;
		}
	}

	return 2;
};

const formatCompact = (value: number): string => {
	try {
		return new Intl.NumberFormat(
			document.documentElement.lang || undefined,
			{ notation: 'compact', maximumFractionDigits: 2 }
		).format(value);
	} catch {
		return formatNumber(value);
	}
};

// One format for every tick on an axis, so a compact axis never mixes `5,000` with `10K`.
export const formatMoneyTick = (
	amount: number,
	currency: string,
	step: number,
	axisMax: number
): string => {
	const wooCurrency = window.CartRebound?.currency;
	const store = wooCurrency?.code === currency ? wooCurrency : undefined;
	const decimals = stepDecimals(step);
	let number: string;

	if (axisMax >= COMPACT_FROM && amount !== 0) {
		number = formatCompact(amount);
	} else if (store) {
		number = formatStoreNumber(amount, decimals, store);
	} else {
		number = formatNumber(amount, decimals);
	}

	return store ? withSymbol(number, store) : number;
};
