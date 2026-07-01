/**
 * React Query hooks for the REST API.
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
	bulkCarts,
	clearLogs,
	createTemplate,
	deleteCart,
	deleteTemplate,
	fetchCarts,
	fetchCoupons,
	fetchLogs,
	fetchOrders,
	fetchPing,
	fetchSettings,
	fetchStats,
	fetchTemplates,
	markCartRecovered,
	sendCartEmail,
	setDefaultTemplate,
	updateCartStatus,
	updateSettings,
	updateTemplate,
} from '../api/endpoints';
import type {
	CartList,
	CartsQuery,
	Coupon,
	EmailTemplate,
	LogList,
	LogsQuery,
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

export const useTemplates = () =>
	useQuery<EmailTemplate[]>({
		queryKey: ['templates'],
		queryFn: fetchTemplates,
	});

const invalidateTemplates = (
	queryClient: ReturnType<typeof useQueryClient>
) => {
	void queryClient.invalidateQueries({ queryKey: ['templates'] });
};

export const useCreateTemplate = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: createTemplate,
		onSuccess: () => {
			invalidateTemplates(queryClient);
		},
	});
};

export const useUpdateTemplate = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: updateTemplate,
		onSuccess: () => {
			invalidateTemplates(queryClient);
		},
	});
};

export const useDeleteTemplate = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: deleteTemplate,
		onSuccess: () => {
			invalidateTemplates(queryClient);
		},
	});
};

export const useSetDefaultTemplate = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: setDefaultTemplate,
		onSuccess: () => {
			invalidateTemplates(queryClient);
		},
	});
};

export const useLogs = (query: LogsQuery) =>
	useQuery<LogList>({
		queryKey: ['logs', query],
		queryFn: () => fetchLogs(query),
	});

export const useClearLog = () => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: clearLogs,
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: ['logs'] });
		},
	});
};
