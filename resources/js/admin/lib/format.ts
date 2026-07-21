/**
 * Shared formatting helpers for the admin UI.
 */

/**
 * Format a monetary amount in the given ISO currency, falling back gracefully
 * when the currency is unknown or unsupported by Intl.
 * @param amount
 * @param currency
 */
export const formatMoney = (amount: number, currency: string): string => {
	if (currency === '') {
		return amount.toFixed(2);
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
