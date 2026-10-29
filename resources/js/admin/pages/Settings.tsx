import {
	useEffect,
	useState,
	type ChangeEvent,
	type FormEvent,
	type ReactNode,
} from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { AdminLink } from '../components/AdminLink';
import { Combobox } from '../components/Combobox';
import { NumberInput } from '../components/NumberInput';
import { useSettings, useUpdateSettings } from '../hooks/useApi';
import { useUnsavedChanges } from '../hooks/useUnsavedChanges';
import { messageOf } from '../lib/errors';
import type { Settings as SettingsData } from '../types/api';

type NumberKey =
	| 'abandonment_threshold'
	| 'scan_interval'
	| 'cleanup_days'
	| 'converted_cleanup_days'
	| 'email_delay_minutes';

type ToggleKey =
	| 'tracking_enabled'
	| 'guest_tracking'
	| 'recovery_email_enabled'
	| 'admin_recovery_email';

const LOG_RETENTION_OPTIONS = [7, 14, 30, 60, 90, 180, 365];

// Refunded/cancelled/failed and unpaid pending are omitted on purpose; custom
// statuses come in via the cart_rebound_paid_order_statuses filter.
const PAID_STATUS_OPTIONS: Array<{ key: string; label: string }> = [
	{ key: 'on-hold', label: __('On hold', 'cart-rebound') },
	{ key: 'processing', label: __('Processing', 'cart-rebound') },
	{ key: 'completed', label: __('Completed', 'cart-rebound') },
];

const Field = ({
	id,
	label,
	hint,
	children,
}: {
	id: string;
	label: string;
	hint?: string;
	children: ReactNode;
}) => (
	<div className="cr-field">
		<label htmlFor={id} className="cr-field__label">
			{label}
		</label>
		{children}
		{hint !== undefined && <p className="cr-field__hint">{hint}</p>}
	</div>
);

export const Settings = () => {
	const { data, isLoading, isError, error } = useSettings();
	const update = useUpdateSettings();
	const [form, setForm] = useState<SettingsData | null>(null);
	const dirty =
		!!form && !!data && JSON.stringify(form) !== JSON.stringify(data);

	useUnsavedChanges(
		dirty,
		__('Your settings have unsaved changes. Discard them?', 'cart-rebound')
	);

	useEffect(() => {
		if (data) {
			setForm(data);
		}
	}, [data]);

	if (isError && !form) {
		return (
			<div className="cr-notice is-error" role="alert">
				{sprintf(
					/* translators: %s: error message. */
					__('Could not load settings: %s', 'cart-rebound'),
					messageOf(error)
				)}
			</div>
		);
	}

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

	const clearSaveResult = () => {
		if (!update.isPending) {
			update.reset();
		}
	};

	const setField = <K extends keyof SettingsData>(
		key: K,
		value: SettingsData[K]
	) => {
		clearSaveResult();
		setForm((previous) =>
			previous ? { ...previous, [key]: value } : previous
		);
	};

	const onNumber = (key: NumberKey) => (value: number) => {
		setField(key, value);
	};

	const onToggle =
		(key: ToggleKey) => (event: ChangeEvent<HTMLInputElement>) => {
			setField(key, event.target.checked);
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

	const toggle = (key: ToggleKey, id: string, disabled = false) => (
		<span className="cr-switch">
			<input
				id={id}
				type="checkbox"
				checked={form[key]}
				disabled={disabled}
				onChange={onToggle(key)}
			/>
			<span className="cr-switch__track">
				<span className="cr-switch__thumb" />
			</span>
		</span>
	);

	return (
		<form onSubmit={onSubmit} className="cr-card" style={{ maxWidth: 720 }}>
			<div className="cr-section">
				<h2 className="cr-section__title">
					{__('Tracking', 'cart-rebound')}
				</h2>
				<p className="cr-section__desc">
					{__(
						'Choose which carts Cart Rebound records.',
						'cart-rebound'
					)}
				</p>
				<div className="cr-field--row">
					<div>
						<label
							htmlFor="cr-tracking"
							className="cr-field__label"
						>
							{__('Track carts', 'cart-rebound')}
						</label>
						<p className="cr-field__hint">
							{__(
								'Record carts and the contact details shoppers enter at checkout.',
								'cart-rebound'
							)}
						</p>
					</div>
					{toggle('tracking_enabled', 'cr-tracking')}
				</div>
				<div
					className={`cr-field--row${
						form.tracking_enabled ? '' : ' is-disabled'
					}`}
				>
					<div>
						<label htmlFor="cr-guest" className="cr-field__label">
							{__('Track guest carts', 'cart-rebound')}
						</label>
						<p className="cr-field__hint">
							{__(
								'Capture carts and the email guests type at checkout.',
								'cart-rebound'
							)}
						</p>
					</div>
					{toggle(
						'guest_tracking',
						'cr-guest',
						!form.tracking_enabled
					)}
				</div>
				{!form.tracking_enabled && (
					<div className="cr-notice is-warning" role="status">
						{__(
							'Tracking is paused. No new carts, checkout emails or tracking cookies are recorded. Carts already tracked keep their scheduled recovery emails.',
							'cart-rebound'
						)}
					</div>
				)}
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
				<div className="cr-field__grid is-columns">
					<Field
						id="cr-threshold"
						label={__(
							'Abandonment threshold (minutes)',
							'cart-rebound'
						)}
						hint={__(
							'Idle time before a cart is abandoned.',
							'cart-rebound'
						)}
					>
						<NumberInput
							id="cr-threshold"
							className="cr-input"
							value={form.abandonment_threshold}
							onChange={onNumber('abandonment_threshold')}
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
						<NumberInput
							id="cr-scan"
							className="cr-input"
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
						<NumberInput
							id="cr-cleanup"
							className="cr-input"
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
						<NumberInput
							id="cr-converted-cleanup"
							className="cr-input"
							value={form.converted_cleanup_days}
							onChange={onNumber('converted_cleanup_days')}
						/>
					</Field>
					<Field
						id="cr-log-retention"
						label={__('Delete log entries after', 'cart-rebound')}
						hint={__(
							'Older entries on the Log screen are removed by the daily cleanup.',
							'cart-rebound'
						)}
					>
						<Combobox
							id="cr-log-retention"
							value={String(form.log_retention_days)}
							options={[
								...[
									...new Set([
										...LOG_RETENTION_OPTIONS,
										form.log_retention_days,
									]),
								]
									.filter((days) => days > 0)
									.sort((a, b) => a - b)
									.map((days) => ({
										value: String(days),
										label: sprintf(
											/* translators: %d: number of days. */
											_n(
												'%d day',
												'%d days',
												days,
												'cart-rebound'
											),
											days
										),
									})),
								{
									value: '0',
									label: __('Never', 'cart-rebound'),
								},
							]}
							onChange={(next) => {
								setField(
									'log_retention_days',
									Number.parseInt(next, 10)
								);
							}}
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
								<span className="cr-check__label">
									{option.label}
								</span>
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
				<div className="cr-field--row">
					<div>
						<label
							htmlFor="cr-email-enabled"
							className="cr-field__label"
						>
							{__('Send recovery email', 'cart-rebound')}
						</label>
						<p className="cr-field__hint">
							{__(
								'Schedules a single follow-up email per abandoned cart.',
								'cart-rebound'
							)}
						</p>
					</div>
					{toggle('recovery_email_enabled', 'cr-email-enabled')}
				</div>

				<div className="cr-field--row">
					<div>
						<label
							htmlFor="cr-admin-notify"
							className="cr-field__label"
						>
							{__('Notify admin on recovery', 'cart-rebound')}
						</label>
						<p className="cr-field__hint">
							{__(
								'Send an email whenever a tracked cart is recovered into a paid order.',
								'cart-rebound'
							)}
						</p>
					</div>
					{toggle('admin_recovery_email', 'cr-admin-notify')}
				</div>

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

				<div className="cr-field__grid is-columns">
					<Field
						id="cr-delay"
						label={__('Send delay (minutes)', 'cart-rebound')}
						hint={__(
							'Wait time after abandonment before sending.',
							'cart-rebound'
						)}
					>
						<NumberInput
							id="cr-delay"
							className="cr-input"
							value={form.email_delay_minutes}
							onChange={onNumber('email_delay_minutes')}
						/>
					</Field>
				</div>

				<p className="cr-section__desc" style={{ marginTop: 4 }}>
					{__(
						'Email content — subject, rich-text body, sender, and coupon — is managed per template. Automatic recovery emails use the template marked default.',
						'cart-rebound'
					)}{' '}
					<AdminLink to="/templates">
						{__('Manage templates', 'cart-rebound')}
					</AdminLink>
				</p>
			</div>

			<div className="cr-savebar">
				{update.isError && (
					<div
						className="cr-notice is-error cr-savebar__notice"
						role="alert"
					>
						{sprintf(
							/* translators: %s: error message. */
							__('Settings were not saved: %s', 'cart-rebound'),
							messageOf(update.error)
						)}
					</div>
				)}
				<button
					type="submit"
					className="cr-btn is-primary"
					disabled={update.isPending}
				>
					{update.isPending
						? __('Saving…', 'cart-rebound')
						: __('Save settings', 'cart-rebound')}
				</button>
				<span
					className={
						update.isSuccess ? 'cr-saved' : 'cr-savebar__state'
					}
					role="status"
				>
					{update.isSuccess && __('Settings saved.', 'cart-rebound')}
					{!update.isSuccess &&
						dirty &&
						!update.isPending &&
						__('Unsaved changes', 'cart-rebound')}
				</span>
			</div>
		</form>
	);
};
