/**
 * The one place the app asks whether a Pro feature is live.
 *
 * Two rules hold everything else together:
 *
 * 1. The answer arrives with the page, localised into `window.CartRebound`, so
 *    a screen never renders unlocked and then snaps shut (or the reverse) once
 *    a request lands. The REST call exists only to pick up a change an add-on
 *    made on its own screen, without a page reload.
 *
 * 2. A locked screen fetches nothing. Its endpoints do not exist on a site with
 *    no add-on, so `useProQuery` stays disabled and hands the screen sample data
 *    instead. That is what lets one component render both the real editor and
 *    the preview under the lock — there is no second, fake version to maintain.
 */
import { useQuery, type UseQueryOptions } from '@tanstack/react-query';
import { fetchAddons } from '../api/pro';
import type { AddonState, ProFeature } from '../types/api';

const EMPTY_STATE: AddonState = {
	installed: false,
	features: [],
	addons: [],
	settings_url: '',
	upgrade_url: '',
};

const bootState = (): AddonState => window.CartRebound?.addons ?? EMPTY_STATE;

/**
 * The current add-on state.
 *
 * Seeded from the page so there is no loading state to design around; the query
 * only ever replaces it with a fresher copy.
 */
export const useAddons = (): AddonState => {
	const { data } = useQuery<AddonState>({
		queryKey: ['addons'],
		queryFn: fetchAddons,
		initialData: bootState,
		// The page already carried the answer. Re-asking on every mount would
		// spend a request to learn what is already on screen.
		staleTime: 5 * 60_000,
		refetchOnWindowFocus: false,
	});

	return data;
};

/**
 * Whether a named feature is being delivered right now.
 * @param feature The feature key to check.
 */
export const useFeature = (feature: ProFeature): boolean =>
	useAddons().features.includes(feature);

export interface ProQueryResult<T> {
	data: T;
	isLoading: boolean;
	isError: boolean;
	/** True when this is the sample store, not the merchant's own data. */
	isPreview: boolean;
}

/**
 * Fetch a Pro endpoint, or stand in the sample store when it is locked.
 *
 * The `sample` argument is the whole trick: a screen destructures one result
 * and renders it, and never learns which of the two it got. Nothing downstream
 * needs a `locked` branch, so the preview cannot drift away from the real
 * screen — they are the same code path.
 * @param feature The feature that must be live for the request to be made.
 * @param key     React Query key.
 * @param queryFn The caller that hits the add-on's endpoint.
 * @param sample  The sample store shown while the feature is locked.
 * @param options Extra React Query options.
 */
export const useProQuery = <T>(
	feature: ProFeature,
	key: unknown[],
	queryFn: () => Promise<T>,
	sample: T,
	options: Partial<UseQueryOptions<T>> = {}
): ProQueryResult<T> => {
	const unlocked = useFeature(feature);

	const query = useQuery<T>({
		queryKey: key,
		queryFn,
		enabled: unlocked,
		...options,
	});

	if (!unlocked) {
		return {
			data: sample,
			isLoading: false,
			isError: false,
			isPreview: true,
		};
	}

	return {
		data: query.data ?? sample,
		isLoading: query.isLoading,
		isError: query.isError,
		isPreview: false,
	};
};
