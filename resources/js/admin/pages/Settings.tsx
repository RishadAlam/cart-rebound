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
import { useSettings, useUpdateSettings } from '../hooks/useApi';
import type { Settings as SettingsData } from '../types/api';

type NumberKey =
	| 'abandonment_threshold'
	| 'scan_interval'
	| 'cleanup_days'
	| 'email_delay_minutes';

type ToggleKey = 'guest_tracking' | 'recovery_email_enabled';

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
				<h2 className="cr-section__title">Tracking</h2>
				<p className="cr-section__desc">
					Cart tracking runs automatically while the plugin is active.
					Choose whether logged-out (guest) carts are tracked too.
				</p>
				<div className="cr-field--row">
					<div>
						<label htmlFor="cr-guest" className="cr-field__label">
							Track guest carts
						</label>
						<p className="cr-field__hint">
							Capture carts and the email guests type at checkout.
						</p>
					</div>
					{toggle('guest_tracking', 'cr-guest')}
				</div>
			</div>

			<div className="cr-section">
				<h2 className="cr-section__title">Abandonment &amp; cleanup</h2>
				<p className="cr-section__desc">
					When an idle cart is marked abandoned, how often carts are
					scanned, and how long stale data is kept.
				</p>
				<div className="cr-field__grid">
					<Field
						id="cr-threshold"
						label="Abandonment threshold (minutes)"
						hint="Idle time before a cart is abandoned."
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
						label="Scan interval (minutes)"
						hint="How often abandoned carts are detected."
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
						label="Cleanup after (days)"
						hint="Unrecovered carts are purged after this."
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
				</div>
			</div>

			<div className="cr-section">
				<h2 className="cr-section__title">Recovery email</h2>
				<p className="cr-section__desc">
					Optionally email shoppers a one-click recovery link a set
					time after they abandon a cart.
				</p>
				<div className="cr-field--row">
					<div>
						<label
							htmlFor="cr-email-enabled"
							className="cr-field__label"
						>
							Send recovery email
						</label>
						<p className="cr-field__hint">
							Schedules a single follow-up email per abandoned
							cart.
						</p>
					</div>
					{toggle('recovery_email_enabled', 'cr-email-enabled')}
				</div>

				<div className="cr-field__grid">
					<Field
						id="cr-delay"
						label="Send delay (minutes)"
						hint="Wait time after abandonment before sending."
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
					Email content — subject, rich-text body, sender, and coupon
					— is managed per template on the{' '}
					<a href="#/templates">Templates</a> tab. Automatic recovery
					emails use the template marked default.
				</p>
			</div>

			<div className="cr-savebar">
				<button
					type="submit"
					className="cr-btn is-primary"
					disabled={update.isPending}
				>
					{update.isPending ? 'Saving…' : 'Save settings'}
				</button>
				{update.isSuccess && (
					<span className="cr-saved">Settings saved.</span>
				)}
			</div>
		</form>
	);
};
