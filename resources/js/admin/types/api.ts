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
	/** wp-admin edit URL for the linked order; empty when there is none. */
	order_edit_url: string;
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
	/** Store currency the listed totals are denominated in. */
	currency: string;
}

export interface Stats {
	counts: Record<string, number>;
	recovered_revenue: number;
	recoverable_revenue: number;
	recovery_rate: number;
	currency: string;
}

/** One day of the dashboard revenue chart. */
export interface TimeseriesPoint {
	date: string;
	recoverable_revenue: number;
	recovered_revenue: number;
	abandoned: number;
	recovered: number;
}

/** One row of the per-product abandonment/recovery report. */
export interface ProductReportRow {
	product_id: number;
	name: string;
	abandoned: number;
	recovered: number;
	/** Value in carts that were abandoned and never recovered. */
	lost_value: number;
	/** wp-admin edit URL; empty when the viewer cannot edit the product. */
	edit_url: string;
}

export interface Settings {
	guest_tracking: boolean;
	abandonment_threshold: number;
	scan_interval: number;
	cleanup_days: number;
	converted_cleanup_days: number;
	recovery_email_enabled: boolean;
	admin_recovery_email: boolean;
	admin_notification_email: string;
	paid_order_statuses: string[];
	email_delay_minutes: number;
	email_subject: string;
	email_body: string;
	email_from_name: string;
	email_from_email: string;
	email_coupon: string;
	onboarding_complete: boolean;
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

/* -------------------------------------------------------------------------- */
/* Add-on surface.                                                            */
/*                                                                            */
/* The feature screens ship here because an add-on that only supplies a REST   */
/* API cannot render anything, and a second bundle would be a copy of this     */
/* design system that drifts a little further with every release. So they live */
/* here and ask the add-on registry one question: which of these features is   */
/* something actually delivering?                                              */
/*                                                                            */
/* Note what is not here: licensing. An add-on owns its own licence screen,    */
/* its own key, and its own explanation for why it is dormant. This plugin     */
/* neither stores nor validates anything of the sort.                          */
/* -------------------------------------------------------------------------- */

/** Feature keys an add-on can claim. Mirrors `CartRebound\Extend\Feature`. */
export type ProFeature =
	'sequence' | 'coupons' | 'tracking' | 'analytics' | 'rules';

export interface AddonSummary {
	slug: string;
	name: string;
	version: string;
	url: string;
	/** What this add-on is delivering right now. */
	features: ProFeature[];
	/** The add-on's own admin screen. */
	settings_url: string;
}

export interface AddonState {
	/** At least one add-on is installed and running. */
	installed: boolean;
	/**
	 * Everything currently being delivered. Empty when an installed add-on is
	 * dormant — this plugin is not told why, and does not need to be.
	 */
	features: ProFeature[];
	addons: AddonSummary[];
	/** Where to send someone whose add-on is installed but dormant. */
	settings_url: string;
	upgrade_url: string;
}

export interface SequenceStep {
	enabled: boolean;
	/** Minutes after abandonment, not after the previous step. */
	delay_minutes: number;
	template_id: string;
	coupon: boolean;
}

export interface ProSettings {
	sequence_steps: SequenceStep[];

	coupon_auto: boolean;
	coupon_type: 'percent' | 'fixed';
	coupon_amount: number;
	coupon_expiry_hours: number;
	coupon_min_amount: number;
	coupon_restrict_email: boolean;
	coupon_free_shipping: boolean;
	coupon_prefix: string;

	tracking_opens: boolean;
	tracking_clicks: boolean;

	analytics_retention_days: number;

	min_cart_total: number;
	excluded_roles: string[];
	excluded_products: number[];
	excluded_categories: number[];
}

export interface ProSettingsResponse {
	settings: ProSettings;
	features: ProFeature[];
}

export interface ProOption {
	value: string;
	label: string;
}

export interface ProOptions {
	templates: Array<{ id: string; name: string; is_default: boolean }>;
	roles: ProOption[];
	categories: ProOption[];
}

/** One step, as the Sequence screen shows it: config plus live state. */
export interface SequenceStepStatus {
	index: number;
	enabled: boolean;
	delay_minutes: number;
	delay_label: string;
	template_id: string;
	template_name: string;
	/** False when the step points at a template that no longer exists. */
	template_ok: boolean;
	coupon: boolean;
	/** Carts currently waiting on this step. Zero while the step is disabled. */
	queued: number;
	sent: number;
	/** False when the step is switched off, so the runner never planned it. */
	in_plan?: boolean;
}

export interface SequenceOverview {
	/** False while the free recovery-email master switch is off. */
	running: boolean;
	steps: SequenceStepStatus[];
	warnings: string[];
}

export interface AnalyticsSummary {
	from: string;
	to: string;
	abandoned_carts: number;
	abandoned_value: number;
	recovered_carts: number;
	recovered_revenue: number;
	recovery_rate: number;
	average_order_value: number;
	/** Median hours between abandonment and recovery. */
	time_to_recovery: number;
	emails_sent: number;
	emails_opened: number;
	emails_clicked: number;
	open_rate: number;
	click_rate: number;
	currency: string;
	/** False when neither open nor click tracking is on. */
	tracking_available: boolean;
	/** Whether the open pixel is running; opens read as n/a when it is not. */
	tracking_opens?: boolean;
	/** Whether click redirects are running; clicks read as n/a when they are not. */
	tracking_clicks?: boolean;
}

export interface AnalyticsPoint {
	date: string;
	abandoned: number;
	abandoned_value: number;
	recovered: number;
	recovered_revenue: number;
}

export interface StepPerformanceRow {
	step: number;
	sent: number;
	opened: number;
	clicked: number;
	recovered: number;
	revenue: number;
	open_rate: number;
	click_rate: number;
	recovery_rate: number;
}

export interface AnalyticsProductRow {
	product_id: number;
	name: string;
	abandoned: number;
	recovered: number;
	lost_value: number;
}

export interface AnalyticsResponse {
	summary: AnalyticsSummary;
	timeseries: AnalyticsPoint[];
	steps: StepPerformanceRow[];
	products: AnalyticsProductRow[];
}
