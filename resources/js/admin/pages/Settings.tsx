/**
 * Settings page — edit and persist plugin settings.
 */
import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react';
import { useSettings, useUpdateSettings } from '../hooks/useApi';
import type { Settings as SettingsData } from '../types/api';

type NumberKey =
	| 'abandonment_threshold'
	| 'scan_interval'
	| 'cleanup_days'
	| 'email_delay_minutes';

type TextKey =
	| 'email_subject'
	| 'email_body'
	| 'email_from_name'
	| 'email_from_email';

type ToggleKey = 'enabled' | 'guest_tracking' | 'recovery_email_enabled';

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
		return <p className="text-gray-500">Loading…</p>;
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

	const onText =
		(key: TextKey) =>
		(event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
			setField(key, event.target.value);
		};

	const onToggle =
		(key: ToggleKey) => (event: ChangeEvent<HTMLInputElement>) => {
			setField(key, event.target.checked);
		};

	const onSubmit = (event: FormEvent<HTMLFormElement>) => {
		event.preventDefault();
		update.mutate(form);
	};

	const inputClass =
		'mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm';

	return (
		<form onSubmit={onSubmit} className="max-w-xl space-y-5">
			<div className="flex items-center gap-2">
				<input
					id="cr-enabled"
					type="checkbox"
					checked={form.enabled}
					onChange={onToggle('enabled')}
				/>
				<label htmlFor="cr-enabled" className="text-sm font-medium">
					Enable tracking
				</label>
			</div>

			<div className="flex items-center gap-2">
				<input
					id="cr-guest"
					type="checkbox"
					checked={form.guest_tracking}
					onChange={onToggle('guest_tracking')}
				/>
				<label htmlFor="cr-guest" className="text-sm font-medium">
					Track guest carts
				</label>
			</div>

			<div>
				<label
					className="block text-sm font-medium"
					htmlFor="cr-threshold"
				>
					Abandonment threshold (minutes)
				</label>
				<input
					id="cr-threshold"
					type="number"
					min={1}
					value={form.abandonment_threshold}
					onChange={onNumber('abandonment_threshold')}
					className={inputClass}
				/>
			</div>

			<div>
				<label
					className="block text-sm font-medium"
					htmlFor="cr-cleanup"
				>
					Cleanup after (days)
				</label>
				<input
					id="cr-cleanup"
					type="number"
					min={1}
					value={form.cleanup_days}
					onChange={onNumber('cleanup_days')}
					className={inputClass}
				/>
			</div>

			<div className="flex items-center gap-2">
				<input
					id="cr-email-enabled"
					type="checkbox"
					checked={form.recovery_email_enabled}
					onChange={onToggle('recovery_email_enabled')}
				/>
				<label
					htmlFor="cr-email-enabled"
					className="text-sm font-medium"
				>
					Send recovery email
				</label>
			</div>

			<div>
				<label className="block text-sm font-medium" htmlFor="cr-delay">
					Email delay (minutes)
				</label>
				<input
					id="cr-delay"
					type="number"
					min={1}
					value={form.email_delay_minutes}
					onChange={onNumber('email_delay_minutes')}
					className={inputClass}
				/>
			</div>

			<div>
				<label
					className="block text-sm font-medium"
					htmlFor="cr-subject"
				>
					Email subject
				</label>
				<input
					id="cr-subject"
					type="text"
					value={form.email_subject}
					onChange={onText('email_subject')}
					className={inputClass}
				/>
			</div>

			<div>
				<label className="block text-sm font-medium" htmlFor="cr-body">
					Email body
				</label>
				<textarea
					id="cr-body"
					rows={4}
					value={form.email_body}
					onChange={onText('email_body')}
					className={inputClass}
				/>
				<p className="mt-1 text-xs text-gray-500">
					Tokens: {'{first_name}'}, {'{products}'}, {'{recovery_url}'}
				</p>
			</div>

			<div>
				<label
					className="block text-sm font-medium"
					htmlFor="cr-from-name"
				>
					From name
				</label>
				<input
					id="cr-from-name"
					type="text"
					value={form.email_from_name}
					onChange={onText('email_from_name')}
					className={inputClass}
				/>
			</div>

			<div>
				<label
					className="block text-sm font-medium"
					htmlFor="cr-from-email"
				>
					From email
				</label>
				<input
					id="cr-from-email"
					type="email"
					value={form.email_from_email}
					onChange={onText('email_from_email')}
					className={inputClass}
				/>
			</div>

			<div className="flex items-center gap-3">
				<button
					type="submit"
					disabled={update.isPending}
					className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
				>
					{update.isPending ? 'Saving…' : 'Save settings'}
				</button>
				{update.isSuccess && (
					<span className="text-sm text-green-600">Saved.</span>
				)}
			</div>
		</form>
	);
};
