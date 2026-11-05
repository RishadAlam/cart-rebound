import type { TimeseriesPoint } from '../types/api';

export interface Tally {
	abandoned: number;
	abandonedValue: number;
	recovered: number;
	recoveredValue: number;
}

export interface Bucket extends Tally {
	start: string;
	end: string;
}

const EMPTY: Tally = {
	abandoned: 0,
	abandonedValue: 0,
	recovered: 0,
	recoveredValue: 0,
};

export const tally = (points: TimeseriesPoint[]): Tally =>
	points.reduce<Tally>(
		(sum, point) => ({
			abandoned: sum.abandoned + point.abandoned,
			abandonedValue: sum.abandonedValue + point.recoverable_revenue,
			recovered: sum.recovered + point.recovered,
			recoveredValue: sum.recoveredValue + point.recovered_revenue,
		}),
		EMPTY
	);

export const hasActivity = (sum: Tally): boolean =>
	sum.abandoned > 0 || sum.recovered > 0;

export const splitPeriods = (points: TimeseriesPoint[], days: number) => ({
	current: points.slice(-days),
	previous: points.slice(0, Math.max(0, points.length - days)),
});

export const bucketize = (
	points: TimeseriesPoint[],
	size: number
): Bucket[] => {
	const buckets: Bucket[] = [];

	for (let end = points.length; end > 0; end -= size) {
		const slice = points.slice(Math.max(0, end - size), end);
		const first = slice[0];
		const last = slice[slice.length - 1];

		if (first && last) {
			buckets.unshift({
				start: first.date,
				end: last.date,
				...tally(slice),
			});
		}
	}

	return buckets;
};
