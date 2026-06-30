/**
 * Dashboard page — abandonment/recovery statistics.
 */
import { isAxiosError } from 'axios';
import { useStats } from '../hooks/useApi';
import type { Stats } from '../types/api';

const formatMoney = (amount: number, currency: string): string => {
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

const StatCard = ({
	label,
	value,
}: {
	label: string;
	value: string | number;
}) => (
	<div className="rounded-lg border border-gray-200 bg-white p-4">
		<div className="text-sm text-gray-500">{label}</div>
		<div className="mt-1 text-2xl font-semibold">{value}</div>
	</div>
);

const cardsFrom = (
	stats: Stats
): { label: string; value: string | number }[] => [
	{ label: 'Active', value: stats.counts.active ?? 0 },
	{ label: 'Abandoned', value: stats.counts.abandoned ?? 0 },
	{ label: 'Recovered', value: stats.counts.recovered ?? 0 },
	{ label: 'Completed', value: stats.counts.completed ?? 0 },
	{
		label: 'Recovered revenue',
		value: formatMoney(stats.recovered_revenue, stats.currency),
	},
	{ label: 'Recovery rate', value: `${stats.recovery_rate}%` },
];

export const Dashboard = () => {
	const { data, isLoading, isError, error } = useStats();

	if (isLoading) {
		return <p className="text-gray-500">Loading…</p>;
	}

	if (isError || !data) {
		const sessionExpired =
			isAxiosError(error) && error.response?.status === 401;

		return (
			<p className="text-red-600">
				{sessionExpired
					? 'Your session has expired. Please reload the page.'
					: 'Could not load statistics.'}
			</p>
		);
	}

	return (
		<div className="grid grid-cols-2 gap-4 md:grid-cols-3">
			{cardsFrom(data).map((card) => (
				<StatCard
					key={card.label}
					label={card.label}
					value={card.value}
				/>
			))}
		</div>
	);
};
