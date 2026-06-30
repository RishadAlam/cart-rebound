/**
 * Typed REST endpoint callers.
 */
import { apiClient } from './client';
import type {
	Cart,
	CartList,
	CartsQuery,
	PingResponse,
	Settings,
	Stats,
} from '../types/api';

export const fetchPing = async (): Promise<PingResponse> => {
	const { data } = await apiClient.get<PingResponse>('ping');

	return data;
};

export const fetchStats = async (): Promise<Stats> => {
	const { data } = await apiClient.get<Stats>('stats');

	return data;
};

export const fetchCarts = async (query: CartsQuery): Promise<CartList> => {
	const { data } = await apiClient.get<CartList>('carts', { params: query });

	return data;
};

export const fetchCart = async (id: number): Promise<Cart> => {
	const { data } = await apiClient.get<Cart>(`carts/${id}`);

	return data;
};

export const deleteCart = async (id: number): Promise<void> => {
	await apiClient.delete(`carts/${id}`);
};

export const markCartRecovered = async (input: {
	id: number;
	order_id: number;
}): Promise<void> => {
	await apiClient.post(`carts/${input.id}/mark-recovered`, {
		order_id: input.order_id,
	});
};

export const fetchSettings = async (): Promise<Settings> => {
	const { data } = await apiClient.get<Settings>('settings');

	return data;
};

export const updateSettings = async (payload: Settings): Promise<Settings> => {
	const { data } = await apiClient.post<Settings>('settings', payload);

	return data;
};
