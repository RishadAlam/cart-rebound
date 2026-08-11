/**
 * Settings page — edit and persist plugin settings.
 *
 * Tracking is always on while the plugin is active; there is no master toggle.
 */
import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { DurationField } from '../components/DurationField';
import { Field, ToggleField } from '../components/Field';
import { NumberField } from '../components/NumberField';
import { SaveBar } from '../components/SaveBar';
import { useFeature } from '../hooks/useAddons';
import { useUnsavedGuard } from '../hooks/useUnsavedGuard';
import { useSettings, useUpdateSettings } from '../hooks/useApi';
import { errorMessage } from '../lib/errors';
import type { Settings as SettingsData } from '../types/api';

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
	const { data, isLoading, isError, error } = useSettings();
	const update = useUpdateSettings();
	// A live sequence replaces the whole follow-up plan, so one field on this
	// screen stops having any effect. Knowing that is the difference between a
	// setting and a lie.
	const sequenceLive = useFeature('sequence');
	const [form, setForm] = useState<SettingsData | null>(null);

	useEffect(() => {
		if (data) {
			setForm(data);
		}
	}, [data]);

	// The tab strip is a router link: without this, one click on another tab
	// discarded a half-finished form and said nothing.
	useUnsavedGuard(
		form !== null &&
			data !== undefined &&
			JSON.stringify(form) !== JSON.stringify(data)
	);

	/*
	 * A failed load used to leave the skeleton pulsing for ever: the guard asked
	 * only "is it loading, or is the form still empty", and both stay true after
	 * the request gives up. A merchant watched three grey bars and had no way to
	 * know the screen was never going to arrive.
	 */
	if (isError) {
		return (
			<div className="cr-notice is-error" role="alert">
				{errorMessage(
					error,
					__(
						'Could not load your settings. Reload the page to try again.',
						'cart-rebound'
					)
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

	const setField = <K extends keyof SettingsData>(
		key: K,
		value: SettingsData[K]
	) => {
		setForm((previous) =>
			previous ? { ...previous, [key]: value } : previous
		);
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
						{...(form.abandonment_threshold >= 1440
							? {
									error: __(
										'A threshold of a day or more means most carts are purged before they are ever counted as abandoned. Minutes or hours is the usual range.',
										'cart-rebound'
									),
								}
							: {})}
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
						<NumberField
							id="cr-scan"
							min={1}
							value={form.scan_interval}
							onChange={(next) => {
								setField('scan_interval', next);
							}}
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
						<NumberField
							id="cr-cleanup"
							min={1}
							value={form.cleanup_days}
							onChange={(next) => {
								setField('cleanup_days', next);
							}}
						/>
					</Field>
					<Field
						id="cr-converted-cleanup"
						label={__(
							'Converted cart retention (days)',
							'cart-rebound'
						)}
						hint={__(
							'Recovered, completed, and order-placed-but-unpaid carts are purged after this.',
							'cart-rebound'
						)}
					>
						<NumberField
							id="cr-converted-cleanup"
							min={1}
							value={form.converted_cleanup_days}
							onChange={(next) => {
								setField('converted_cleanup_days', next);
							}}
						/>
					</Field>
				</div>

				{/*
				 * A real group with a real name. The question was a <span>, so
				 * assistive tech announced three unrelated checkboxes and never
				 * the thing they answer.
				 */}
				<fieldset className="cr-field cr-fieldset">
					<legend className="cr-field__label">
						{__(
							'Count a cart as recovered when its order is',
							'cart-rebound'
						)}
					</legend>
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

					{/*
					 * Nothing stops all three being unticked, and nothing about the
					 * screen would look wrong afterwards — the carts would simply
					 * never be credited, and the revenue figures would stay at zero
					 * while the store kept taking orders. Said here, in place, while
					 * the choice is still in front of the merchant.
					 */}
					{form.paid_order_statuses.length === 0 ? (
						<p className="cr-field__hint is-error" role="alert">
							{__(
								'No status is selected, so no order will ever count as a recovery and your recovered revenue will stay at zero. Select at least one.',
								'cart-rebound'
							)}
						</p>
					) : (
						<p className="cr-field__hint">
							{__(
								'Order statuses that mark a tracked cart as paid and attributed. This applies to orders from now on — carts already counted are not revisited.',
								'cart-rebound'
							)}
						</p>
					)}
				</fieldset>
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
						{...(form.admin_notification_email.trim() !== '' &&
						!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
							form.admin_notification_email.trim()
						)
							? {
									error: __(
										'That is not a valid address. WordPress will silently discard it and notify the site admin instead.',
										'cart-rebound'
									),
								}
							: {})}
						hint={__(
							'Where recovery notifications are sent. Leave blank to use the site admin address.',
							'cart-rebound'
						)}
					>
						<input
							id="cr-admin-email"
							className="cr-input"
							type="email"
							{...(form.admin_notification_email.trim() !== '' &&
							!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
								form.admin_notification_email.trim()
							)
								? { 'aria-invalid': true }
								: {})}
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
					{/* With a sequence running the hint below the field would be
					    wrong, and the explanation that replaces it belongs to the
					    whole section rather than to this one control. */}
					<Field
						id="cr-delay"
						label={__('Send delay', 'cart-rebound')}
						{...(sequenceLive
							? {}
							: {
									hint: __(
										'Wait time after abandonment before sending.',
										'cart-rebound'
									),
								})}
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

				{/*
				 * With the sequence add-on delivering, the runner takes its whole
				 * plan from the add-on and this delay is never read. It stays
				 * editable — the merchant keeps their value for the day the add-on
				 * is switched off — but a field that changes nothing must not sit
				 * there implying otherwise.
				 */}
				{!form.recovery_email_enabled && (
					<p className="cr-field__hint is-warning">
						{__(
							'Recovery email is switched off, so nothing above is being sent. Turn it on to use these settings.',
							'cart-rebound'
						)}
					</p>
				)}

				{sequenceLive && (
					<p className="cr-field__hint">
						{__(
							'Your follow-up sequence sets its own timing for each step, so this delay is not in use. It applies again if the sequence stops running.',
							'cart-rebound'
						)}{' '}
						<Link to="/sequence">
							{__('Edit the sequence timing', 'cart-rebound')}
						</Link>
					</p>
				)}

				{/*
				 * One sentence, not three fragments with a link welded into the
				 * middle: a translator handed "…managed per template on the" has
				 * no way to move the link where their grammar needs it.
				 */}
				<p className="cr-section__desc" style={{ marginTop: 4 }}>
					{__(
						'Email content — subject, rich-text body, sender, and coupon — is managed per template, and automatic recovery emails use the one marked default.',
						'cart-rebound'
					)}{' '}
					<Link to="/templates">
						{__('Edit templates', 'cart-rebound')}
					</Link>
				</p>
			</div>

			<SaveBar
				label={__('Save settings', 'cart-rebound')}
				savedLabel={__('Settings saved.', 'cart-rebound')}
				errorFallback={__(
					'Settings could not be saved. Please try again.',
					'cart-rebound'
				)}
				isPending={update.isPending}
				isSuccess={update.isSuccess}
				isError={update.isError}
				error={update.error}
				onReset={update.reset}
			/>
		</form>
	);
};
