/**
 * Typed callers for the endpoints an add-on registers.
 *
 * Every one of these 404s on a site with no add-on installed, which is exactly
 * why nothing calls them directly. The `useProQuery` hook only enables a query
 * once the registry says the matching feature is being delivered; until then
 * the screen renders sample data behind its lock.
 */
import { apiClient } from './client';
import type {
	AddonState,
	AnalyticsResponse,
	LicenseState,
	ProOptions,
	ProSettings,
	ProSettingsResponse,
	SequenceOverview,
} from '../types/api';

export interface DateRange {
	from: string;
	to: string;
}

export const fetchAddons = async (): Promise<AddonState> => {
	const { data } = await apiClient.get<AddonState>('addons');

	return data;
};

export const fetchProSettings = async (): Promise<ProSettingsResponse> => {
	const { data } = await apiClient.get<ProSettingsResponse>('pro/settings');

	return data;
};

export const updateProSettings = async (
	payload: Partial<ProSettings>
): Promise<ProSettings> => {
	const { data } = await apiClient.post<ProSettings>('pro/settings', payload);

	return data;
};

export const fetchProOptions = async (): Promise<ProOptions> => {
	const { data } = await apiClient.get<ProOptions>('pro/options');

	return data;
};

export const fetchSequence = async (): Promise<SequenceOverview> => {
	const { data } = await apiClient.get<SequenceOverview>('pro/sequence');

	return data;
};

export const fetchAnalytics = async (
	range: DateRange
): Promise<AnalyticsResponse> => {
	const { data } = await apiClient.get<AnalyticsResponse>('pro/analytics', {
		params: range,
	});

	return data;
};

export const fetchAnalyticsCsv = async (
	input: DateRange & { report: string }
): Promise<string> => {
	const { data } = await apiClient.get<string>('pro/analytics/export', {
		params: input,
		// The endpoint answers with a CSV document, not JSON.
		responseType: 'text',
		transformResponse: [(value: string) => value],
	});

	return data;
};

export const fetchLicense = async (): Promise<LicenseState> => {
	const { data } = await apiClient.get<LicenseState>('pro/license');

	return data;
};

export const activateLicense = async (key: string): Promise<LicenseState> => {
	const { data } = await apiClient.post<LicenseState>('pro/license', {
		license_key: key,
	});

	return data;
};

export const refreshLicense = async (): Promise<LicenseState> => {
	const { data } = await apiClient.post<LicenseState>(
		'pro/license/refresh',
		{}
	);

	return data;
};

export const deactivateLicense = async (): Promise<LicenseState> => {
	const { data } = await apiClient.delete<LicenseState>('pro/license');

	return data;
};
