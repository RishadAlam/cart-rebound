/**
 * Typed REST endpoint callers.
 */
import { apiClient } from './client';
import type {
	BulkAction,
	Cart,
	CartList,
	CartsQuery,
	Coupon,
	Order,
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

export const fetchOrders = async (): Promise<Order[]> => {
	const { data } = await apiClient.get<{ items: Order[] }>('orders');

	return data.items;
};

export const fetchCoupons = async (): Promise<Coupon[]> => {
	const { data } = await apiClient.get<{ items: Coupon[] }>('coupons');

	return data.items;
};

export const deleteCart = async (id: number): Promise<void> => {
	await apiClient.delete(`carts/${id}`);
};

export const markCartRecovered = async (input: {
	id: number;
	order_id: number;
}): Promise<void> => {
	const { data } = await apiClient.post<{ updated: boolean }>(
		`carts/${input.id}/mark-recovered`,
		{ order_id: input.order_id }
	);

	if (!data.updated) {
		throw new Error(
			'Could not mark recovered — the order ID may be invalid, or this cart is already linked to an order.'
		);
	}
};

export const updateCartStatus = async (input: {
	id: number;
	status: string;
}): Promise<void> => {
	const { data } = await apiClient.post<{ updated: boolean }>(
		`carts/${input.id}/status`,
		{ status: input.status }
	);

	if (!data.updated) {
		throw new Error('Could not change the cart status.');
	}
};

export const sendCartEmail = async (id: number): Promise<void> => {
	const { data } = await apiClient.post<{ sent: boolean }>(
		`carts/${id}/send-email`,
		{}
	);

	if (!data.sent) {
		throw new Error(
			'Email not sent — the cart needs a valid email and at least one item.'
		);
	}
};

export const bulkCarts = async (input: {
	action: BulkAction;
	ids: number[];
	status?: string;
}): Promise<number> => {
	const { data } = await apiClient.post<{ affected: number }>(
		'carts/bulk',
		input
	);

	return data.affected;
};

export const fetchSettings = async (): Promise<Settings> => {
	const { data } = await apiClient.get<Settings>('settings');

	return data;
};

export const updateSettings = async (payload: Settings): Promise<Settings> => {
	const { data } = await apiClient.post<Settings>('settings', payload);

	return data;
};
