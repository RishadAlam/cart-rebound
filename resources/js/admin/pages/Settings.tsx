/**
 * Settings page — edit and persist plugin settings.
 *
 * Tracking is always on while the plugin is active; there is no master toggle.
 */
import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react';
import { __ } from '@wordpress/i18n';
import { DurationField } from '../components/DurationField';
import { Field, ToggleField } from '../components/Field';
import { useSettings, useUpdateSettings } from '../hooks/useApi';
import type { Settings as SettingsData } from '../types/api';

type NumberKey =
	| 'abandonment_threshold'
	| 'scan_interval'
	| 'cleanup_days'
	| 'converted_cleanup_days'
	| 'email_delay_minutes';

// WooCommerce order statuses selectable as "counts as recovered". Reversed
// states (refunded/cancelled/failed) and unpaid pending are intentionally
// omitted; custom statuses can be added via the cart_rebound_paid_order_statuses
// filter.
const PAID_STATUS_OPTIONS: Array<{ key: string; label: string }> = [
	{ key: 'on-hold', label: __('On hold', 'cart-rebound') },
	{ key: 'processing', label: __('Processing', 'cart-rebound') },
	{ key: 'completed', label: __('Completed', 'cart-rebound') },
];

export const Settings = () => {
	const { data, isLoading } = useSettings();
	const update = useUpdateSettings();
	const [form, setForm] = useState<SettingsData | null>(null);

	useEffect(() => {
		if (data) {
			setForm(data);
		}
	}, [data]);

	if (isLoading || !form) {
		return (
			<div className="cr-card cr-section">
				<div
					className="cr-skeleton"
					style={{ height: 16, width: '40%' }}
				/>
				<div
					className="cr-skeleton"
					style={{ height: 40, width: '100%', marginTop: 16 }}
				/>
				<div
					className="cr-skeleton"
					style={{ height: 40, width: '100%', marginTop: 12 }}
				/>
			</div>
		);
	}

	const setField = <K extends keyof SettingsData>(
		key: K,
		value: SettingsData[K]
	) => {
		setForm((previous) =>
			previous ? { ...previous, [key]: value } : previous
		);
	};

	const onNumber =
		(key: NumberKey) => (event: ChangeEvent<HTMLInputElement>) => {
			const parsed = Number.parseInt(event.target.value, 10);

			setField(key, Number.isNaN(parsed) ? 1 : Math.max(1, parsed));
		};

	const onStatusToggle =
		(status: string) => (event: ChangeEvent<HTMLInputElement>) => {
			setForm((previous) => {
				if (!previous) {
					return previous;
				}

				const next = new Set(previous.paid_order_statuses);

				if (event.target.checked) {
					next.add(status);
				} else {
					next.delete(status);
				}

				return { ...previous, paid_order_statuses: [...next] };
			});
		};

	const onSubmit = (event: FormEvent<HTMLFormElement>) => {
		event.preventDefault();
		update.mutate(form);
	};

	return (
		<form onSubmit={onSubmit} className="cr-card">
			<div className="cr-section">
				<h2 className="cr-section__title">
					{__('Tracking', 'cart-rebound')}
				</h2>
				<p className="cr-section__desc">
					{__(
						'Cart tracking runs automatically while the plugin is active. Choose whether logged-out (guest) carts are tracked too.',
						'cart-rebound'
					)}
				</p>
				<ToggleField
					id="cr-guest"
					label={__('Track guest carts', 'cart-rebound')}
					hint={__(
						'Capture carts and the email guests type at checkout.',
						'cart-rebound'
					)}
					checked={form.guest_tracking}
					onChange={(checked) => {
						setField('guest_tracking', checked);
					}}
				/>
			</div>

			<div className="cr-section">
				<h2 className="cr-section__title">
					{__('Abandonment & cleanup', 'cart-rebound')}
				</h2>
				<p className="cr-section__desc">
					{__(
						'When an idle cart is marked abandoned, how often carts are scanned, and how long stale data is kept.',
						'cart-rebound'
					)}
				</p>
				<div className="cr-field__grid">
					<Field
						id="cr-threshold"
						label={__('Abandonment threshold', 'cart-rebound')}
						hint={__(
							'Idle time before a cart is abandoned.',
							'cart-rebound'
						)}
					>
						<DurationField
							id="cr-threshold"
							minutes={form.abandonment_threshold}
							unitLabel={__(
								'Abandonment threshold unit',
								'cart-rebound'
							)}
							onChange={(minutes) => {
								setField('abandonment_threshold', minutes);
							}}
						/>
					</Field>
					<Field
						id="cr-scan"
						label={__('Scan interval (minutes)', 'cart-rebound')}
						hint={__(
							'How often abandoned carts are detected.',
							'cart-rebound'
						)}
					>
						<input
							id="cr-scan"
							className="cr-input"
							type="number"
							min={1}
							value={form.scan_interval}
							onChange={onNumber('scan_interval')}
						/>
					</Field>
					<Field
						id="cr-cleanup"
						label={__('Cleanup after (days)', 'cart-rebound')}
						hint={__(
							'Unrecovered carts are purged after this.',
							'cart-rebound'
						)}
					>
						<input
							id="cr-cleanup"
							className="cr-input"
							type="number"
							min={1}
							value={form.cleanup_days}
							onChange={onNumber('cleanup_days')}
						/>
					</Field>
					<Field
						id="cr-converted-cleanup"
						label={__(
							'Converted cart retention (days)',
							'cart-rebound'
						)}
						hint={__(
							'Recovered and completed carts are purged after this.',
							'cart-rebound'
						)}
					>
						<input
							id="cr-converted-cleanup"
							className="cr-input"
							type="number"
							min={1}
							value={form.converted_cleanup_days}
							onChange={onNumber('converted_cleanup_days')}
						/>
					</Field>
				</div>

				<div className="cr-field">
					<span className="cr-field__label">
						{__(
							'Count a cart as recovered when its order is',
							'cart-rebound'
						)}
					</span>
					<div className="cr-checks">
						{PAID_STATUS_OPTIONS.map((option) => (
							<label
								key={option.key}
								htmlFor={`cr-paid-${option.key}`}
								className="cr-check"
							>
								<input
									id={`cr-paid-${option.key}`}
									type="checkbox"
									checked={form.paid_order_statuses.includes(
										option.key
									)}
									onChange={onStatusToggle(option.key)}
								/>
								<span>{option.label}</span>
							</label>
						))}
					</div>
					<p className="cr-field__hint">
						{__(
							'Order statuses that mark a tracked cart as paid and attributed.',
							'cart-rebound'
						)}
					</p>
				</div>
			</div>

			<div className="cr-section">
				<h2 className="cr-section__title">
					{__('Recovery email', 'cart-rebound')}
				</h2>
				<p className="cr-section__desc">
					{__(
						'Optionally email shoppers a one-click recovery link a set time after they abandon a cart.',
						'cart-rebound'
					)}
				</p>
				<ToggleField
					id="cr-email-enabled"
					label={__('Send recovery email', 'cart-rebound')}
					hint={__(
						'Schedules a single follow-up email per abandoned cart.',
						'cart-rebound'
					)}
					checked={form.recovery_email_enabled}
					onChange={(checked) => {
						setField('recovery_email_enabled', checked);
					}}
				/>

				<ToggleField
					id="cr-admin-notify"
					label={__('Notify admin on recovery', 'cart-rebound')}
					hint={__(
						'Send an email whenever a tracked cart is recovered into a paid order.',
						'cart-rebound'
					)}
					checked={form.admin_recovery_email}
					onChange={(checked) => {
						setField('admin_recovery_email', checked);
					}}
				/>

				{form.admin_recovery_email && (
					<Field
						id="cr-admin-email"
						label={__('Notification email', 'cart-rebound')}
						hint={__(
							'Where recovery notifications are sent. Leave blank to use the site admin address.',
							'cart-rebound'
						)}
					>
						<input
							id="cr-admin-email"
							className="cr-input"
							type="email"
							value={form.admin_notification_email}
							placeholder={__(
								'Defaults to the site admin email',
								'cart-rebound'
							)}
							onChange={(event) => {
								setField(
									'admin_notification_email',
									event.target.value
								);
							}}
						/>
					</Field>
				)}

				<div className="cr-field__grid">
					<Field
						id="cr-delay"
						label={__('Send delay', 'cart-rebound')}
						hint={__(
							'Wait time after abandonment before sending.',
							'cart-rebound'
						)}
					>
						<DurationField
							id="cr-delay"
							minutes={form.email_delay_minutes}
							unitLabel={__('Send delay unit', 'cart-rebound')}
							onChange={(minutes) => {
								setField('email_delay_minutes', minutes);
							}}
						/>
					</Field>
				</div>

				<p className="cr-section__desc" style={{ marginTop: 4 }}>
					{__(
						'Email content — subject, rich-text body, sender, and coupon — is managed per template on the',
						'cart-rebound'
					)}{' '}
					<a href="#/templates">{__('Templates', 'cart-rebound')}</a>{' '}
					{__(
						'tab. Automatic recovery emails use the template marked default.',
						'cart-rebound'
					)}
				</p>
			</div>

			<div className="cr-savebar">
				<button
					type="submit"
					className="cr-btn is-primary"
					disabled={update.isPending}
				>
					{update.isPending
						? __('Saving…', 'cart-rebound')
						: __('Save settings', 'cart-rebound')}
				</button>
				{update.isSuccess && (
					<span className="cr-saved">
						{__('Settings saved.', 'cart-rebound')}
					</span>
				)}
			</div>
		</form>
	);
};
