/**
 * React Query hooks for the REST API.
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
	deleteCart,
	fetchCarts,
	fetchPing,
	fetchSettings,
	fetchStats,
	markCartRecovered,
	updateSettings,
} from '../api/endpoints';
import type {
	CartList,
	CartsQuery,
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
