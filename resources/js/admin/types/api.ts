/**
 * Shared API response types.
 */

export interface PingResponse {
	pong: boolean;
	version: string;
}

export interface CartProduct {
	product_id: number;
	name: string;
	qty: number;
	total: number;
}

export interface Cart {
	id: number;
	session_key: string;
	user_id: number;
	email: string;
	first_name: string;
	last_name: string;
	phone: string;
	cart_total: number;
	currency: string;
	items_count: number;
	status: string;
	order_id: number;
	recovered_amount: number;
	created_at: string;
	last_activity: string;
	abandoned_at: string;
	recovered_at: string;
	completed_at: string;
	products: CartProduct[];
	coupons: string[];
}

export interface CartList {
	items: Cart[];
	total: number;
	page: number;
	per_page: number;
}

export interface Stats {
	counts: Record<string, number>;
	recovered_revenue: number;
	recovery_rate: number;
	currency: string;
}

export interface Settings {
	guest_tracking: boolean;
	abandonment_threshold: number;
	scan_interval: number;
	cleanup_days: number;
	recovery_email_enabled: boolean;
	email_delay_minutes: number;
	email_subject: string;
	email_body: string;
	email_from_name: string;
	email_from_email: string;
	email_coupon: string;
}

export interface Order {
	id: number;
	number: string;
	email: string;
	total: number;
	currency: string;
	status: string;
	date: string;
}

export interface Coupon {
	code: string;
	description: string;
	amount: number;
	type: string;
}

export interface EmailTemplate {
	id: string;
	name: string;
	subject: string;
	body: string;
	from_name: string;
	from_email: string;
	coupon: string;
	is_default: boolean;
}

export interface LogEntry {
	id: number;
	created_at: string;
	level: string;
	event: string;
	message: string;
	cart_id: number;
}

export interface LogList {
	items: LogEntry[];
	total: number;
	page: number;
	per_page: number;
}

export interface LogsQuery {
	level: string;
	event: string;
	cart_id: number;
	page: number;
	per_page: number;
}

export type SortOrder = 'asc' | 'desc';

export type BulkAction = 'delete' | 'status';

export interface CartsQuery {
	status: string;
	email: string;
	page: number;
	per_page: number;
	orderby: string;
	order: SortOrder;
}
