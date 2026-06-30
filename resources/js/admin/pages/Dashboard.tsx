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

interface Card {
	label: string;
	value: string | number;
	accent?: boolean;
}

const cardsFrom = (stats: Stats): Card[] => [
	{ label: 'Active carts', value: stats.counts.active ?? 0 },
	{ label: 'Abandoned', value: stats.counts.abandoned ?? 0 },
	{ label: 'Recovered', value: stats.counts.recovered ?? 0 },
	{ label: 'Completed', value: stats.counts.completed ?? 0 },
	{
		label: 'Recovered revenue',
		value: formatMoney(stats.recovered_revenue, stats.currency),
		accent: true,
	},
	{ label: 'Recovery rate', value: `${stats.recovery_rate}%` },
];

const StatCard = ({
	label,
	value,
	accent,
}: {
	label: string;
	value: string | number;
	accent: boolean;
}) => (
	<div className={accent ? 'cr-stat is-accent' : 'cr-stat'}>
		<p className="cr-stat__label">{label}</p>
		<p className="cr-stat__value">{value}</p>
	</div>
);

const SkeletonGrid = () => (
	<div className="cr-stats">
		{Array.from({ length: 6 }, (_, index) => (
			<div key={index} className="cr-stat">
				<div
					className="cr-skeleton"
					style={{ height: 12, width: '55%' }}
				/>
				<div
					className="cr-skeleton"
					style={{ height: 26, width: '72%', marginTop: 14 }}
				/>
			</div>
		))}
	</div>
);

export const Dashboard = () => {
	const { data, isLoading, isError, error } = useStats();

	if (isLoading) {
		return <SkeletonGrid />;
	}

	if (isError || !data) {
		const sessionExpired =
			isAxiosError(error) && error.response?.status === 401;

		return (
			<div className="cr-notice is-error" role="alert">
				{sessionExpired
					? 'Your session has expired. Please reload the page.'
					: 'Could not load statistics.'}
			</div>
		);
	}

	return (
		<div className="cr-stats">
			{cardsFrom(data).map((card) => (
				<StatCard
					key={card.label}
					label={card.label}
					value={card.value}
					accent={card.accent ?? false}
				/>
			))}
		</div>
	);
};
