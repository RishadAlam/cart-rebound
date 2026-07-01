/**
 * React Query hooks for the REST API.
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
	bulkCarts,
	deleteCart,
	fetchCarts,
	fetchCoupons,
	fetchOrders,
	fetchPing,
	fetchSettings,
	fetchStats,
	markCartRecovered,
	sendCartEmail,
	updateCartStatus,
	updateSettings,
} from '../api/endpoints';
import type {
	CartList,
	CartsQuery,
	Coupon,
	Order,
	PingResponse,
	Settings,
	Stats,
} from '../types/api';

export const usePing = () =>
	useQuery<PingResponse>({
		queryKey: ['ping'],
		queryFn: fetchPing,
	});

export const useStats = () =>
	useQuery<Stats>({
		queryKey: ['stats'],
		queryFn: fetchStats,
	});

export const useCarts = (query: CartsQuery) =>
	useQuery<CartList>({
		queryKey: ['carts', query],
		queryFn: () => fetchCarts(query),
	});

export const useOrders = () =>
	useQuery<Order[]>({
		queryKey: ['orders'],
		queryFn: fetchOrders,
		staleTime: 60_000,
	});

export const useCoupons = () =>
	useQuery<Coupon[]>({
		queryKey: ['coupons'],
		queryFn: fetchCoupons,
		staleTime: 60_000,
	});

export const useSettings = () =>
	useQuery<Settings>({
		queryKey: ['settings'],
		queryFn: fetchSettings,
	});

export const useUpdateSettings = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: updateSettings,
		onSuccess: (data) => {
			queryClient.setQueryData(['settings'], data);
		},
	});
};

const invalidateCarts = (queryClient: ReturnType<typeof useQueryClient>) => {
	void queryClient.invalidateQueries({ queryKey: ['carts'] });
	void queryClient.invalidateQueries({ queryKey: ['stats'] });
};

export const useDeleteCart = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: deleteCart,
		onSuccess: () => {
			invalidateCarts(queryClient);
		},
	});
};

export const useMarkRecovered = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: markCartRecovered,
		onSuccess: () => {
			invalidateCarts(queryClient);
		},
	});
};

export const useUpdateStatus = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: updateCartStatus,
		onSuccess: () => {
			invalidateCarts(queryClient);
		},
	});
};

export const useSendEmail = () => useMutation({ mutationFn: sendCartEmail });

export const useBulkCarts = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: bulkCarts,
		onSuccess: () => {
			invalidateCarts(queryClient);
		},
	});
};
