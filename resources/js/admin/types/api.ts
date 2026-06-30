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
}

export interface CartsQuery {
	status: string;
	email: string;
	page: number;
	per_page: number;
}
