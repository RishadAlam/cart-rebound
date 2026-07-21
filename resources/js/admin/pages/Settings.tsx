/**
 * Settings page — edit and persist plugin settings.
 *
 * Tracking is always on while the plugin is active; there is no master toggle.
 */
import {
	useEffect,
	useState,
	type ChangeEvent,
	type FormEvent,
	type ReactNode,
} from 'react';
import { __ } from '@wordpress/i18n';
import { useSettings, useUpdateSettings } from '../hooks/useApi';
import type { Settings as SettingsData } from '../types/api';

type NumberKey =
	| 'abandonment_threshold'
	| 'scan_interval'
	| 'cleanup_days'
	| 'converted_cleanup_days'
	| 'email_delay_minutes';

type ToggleKey =
	'guest_tracking' | 'recovery_email_enabled' | 'admin_recovery_email';

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

	const onToggle =
		(key: ToggleKey) => (event: ChangeEvent<HTMLInputElement>) => {
			setField(key, event.target.checked);
		};

	const onSubmit = (event: FormEvent<HTMLFormElement>) => {
		event.preventDefault();
		update.mutate(form);
	};

	const toggle = (key: ToggleKey, id: string) => (
		<span className="cr-switch">
			<input
				id={id}
				type="checkbox"
				checked={form[key]}
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
						'Cart tracking runs automatically while the plugin is active. Choose whether logged-out (guest) carts are tracked too.',
						'cart-rebound'
					)}
				</p>
				<div className="cr-field--row">
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
					{toggle('guest_tracking', 'cr-guest')}
				</div>
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
						label={__(
							'Abandonment threshold (minutes)',
							'cart-rebound'
						)}
						hint={__(
							'Idle time before a cart is abandoned.',
							'cart-rebound'
						)}
					>
						<input
							id="cr-threshold"
							className="cr-input"
							type="number"
							min={1}
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
								'Email the site admin whenever a tracked cart is recovered into a paid order.',
								'cart-rebound'
							)}
						</p>
					</div>
					{toggle('admin_recovery_email', 'cr-admin-notify')}
				</div>

				<div className="cr-field__grid">
					<Field
						id="cr-delay"
						label={__('Send delay (minutes)', 'cart-rebound')}
						hint={__(
							'Wait time after abandonment before sending.',
							'cart-rebound'
						)}
					>
						<input
							id="cr-delay"
							className="cr-input"
							type="number"
							min={1}
							value={form.email_delay_minutes}
							onChange={onNumber('email_delay_minutes')}
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
