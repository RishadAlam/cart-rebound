/**
 * First-run setup wizard.
 *
 * A short, skippable guided setup shown until the merchant completes or skips
 * it. It writes the handful of settings that get recovery working, then flips
 * the `onboarding_complete` flag so it never shows again.
 */
import { useEffect, useRef, useState, type ChangeEvent } from 'react';
import { __ } from '@wordpress/i18n';
import { useSettings, useUpdateSettings } from '../hooks/useApi';

interface WizardForm {
	guest_tracking: boolean;
	abandonment_threshold: number;
	recovery_email_enabled: boolean;
	email_delay_minutes: number;
}

const STEP_COUNT = 4;

const Switch = ({
	checked,
	onChange,
	id,
}: {
	checked: boolean;
	onChange: (value: boolean) => void;
	id: string;
}) => (
	<span className="cr-switch">
		<input
			id={id}
			type="checkbox"
			checked={checked}
			onChange={(event) => {
				onChange(event.target.checked);
			}}
		/>
		<span className="cr-switch__track">
			<span className="cr-switch__thumb" />
		</span>
	</span>
);

export const OnboardingWizard = () => {
	const { data: settings } = useSettings();
	const update = useUpdateSettings();
	const [step, setStep] = useState(0);
	const [dismissed, setDismissed] = useState(false);
	const [form, setForm] = useState<WizardForm | null>(null);
	const ref = useRef<HTMLDialogElement>(null);

	useEffect(() => {
		if (settings && form === null) {
			setForm({
				guest_tracking: settings.guest_tracking,
				abandonment_threshold: settings.abandonment_threshold,
				recovery_email_enabled: settings.recovery_email_enabled,
				email_delay_minutes: settings.email_delay_minutes,
			});
		}
	}, [settings, form]);

	// Open as a native modal (focus trap + Esc handled for free) once the dialog
	// is actually rendered — i.e. settings loaded and setup not yet complete.
	useEffect(() => {
		const el = ref.current;

		if (el && !el.open) {
			el.showModal();
		}
	}, [form, settings, dismissed]);

	if (!settings || settings.onboarding_complete || dismissed || !form) {
		return null;
	}

	const setField = <K extends keyof WizardForm>(
		key: K,
		value: WizardForm[K]
	) => {
		setForm((previous) =>
			previous ? { ...previous, [key]: value } : previous
		);
	};

	const onNumber =
		(key: 'abandonment_threshold' | 'email_delay_minutes') =>
		(event: ChangeEvent<HTMLInputElement>) => {
			const parsed = Number.parseInt(event.target.value, 10);
			setField(key, Number.isNaN(parsed) ? 1 : Math.max(1, parsed));
		};

	const finish = (apply: boolean) => {
		setDismissed(true);
		update.mutate({
			...settings,
			...(apply ? form : {}),
			onboarding_complete: true,
		});
	};

	const steps = [
		{
			title: __('Welcome to Cart Rebound', 'cart-rebound'),
			body: (
				<p className="cr-wizard__lead">
					{__(
						'A couple of quick choices and you’ll be recovering abandoned carts. You can change any of this later in Settings.',
						'cart-rebound'
					)}
				</p>
			),
		},
		{
			title: __('Track guest carts', 'cart-rebound'),
			body: (
				<div className="cr-field--row">
					<div>
						<label
							htmlFor="cr-wiz-guest"
							className="cr-field__label"
						>
							{__('Track logged-out shoppers', 'cart-rebound')}
						</label>
						<p className="cr-field__hint">
							{__(
								'Capture carts and the email guests enter at checkout, not just logged-in customers.',
								'cart-rebound'
							)}
						</p>
					</div>
					<Switch
						id="cr-wiz-guest"
						checked={form.guest_tracking}
						onChange={(value) => setField('guest_tracking', value)}
					/>
				</div>
			),
		},
		{
			title: __('When is a cart abandoned?', 'cart-rebound'),
			body: (
				<div className="cr-field">
					<label
						htmlFor="cr-wiz-threshold"
						className="cr-field__label"
					>
						{__('Abandonment threshold (minutes)', 'cart-rebound')}
					</label>
					<input
						id="cr-wiz-threshold"
						className="cr-input"
						type="number"
						min={1}
						style={{ maxWidth: 160 }}
						value={form.abandonment_threshold}
						onChange={onNumber('abandonment_threshold')}
					/>
					<p className="cr-field__hint">
						{__(
							'How long a cart sits idle before it counts as abandoned.',
							'cart-rebound'
						)}
					</p>
				</div>
			),
		},
		{
			title: __('Recovery email', 'cart-rebound'),
			body: (
				<>
					<div className="cr-field--row">
						<div>
							<label
								htmlFor="cr-wiz-email"
								className="cr-field__label"
							>
								{__('Send a recovery email', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'One follow-up with a one-click link back to the cart. Edit the wording on the Templates tab.',
									'cart-rebound'
								)}
							</p>
						</div>
						<Switch
							id="cr-wiz-email"
							checked={form.recovery_email_enabled}
							onChange={(value) =>
								setField('recovery_email_enabled', value)
							}
						/>
					</div>
					{form.recovery_email_enabled && (
						<div className="cr-field" style={{ marginTop: 14 }}>
							<label
								htmlFor="cr-wiz-delay"
								className="cr-field__label"
							>
								{__('Send delay (minutes)', 'cart-rebound')}
							</label>
							<input
								id="cr-wiz-delay"
								className="cr-input"
								type="number"
								min={1}
								style={{ maxWidth: 160 }}
								value={form.email_delay_minutes}
								onChange={onNumber('email_delay_minutes')}
							/>
							<p className="cr-field__hint">
								{__(
									'Wait time after a cart is abandoned before the email goes out.',
									'cart-rebound'
								)}
							</p>
						</div>
					)}
				</>
			),
		},
	];

	const current = steps[step];
	const isLast = step === STEP_COUNT - 1;

	if (!current) {
		return null;
	}

	return (
		<dialog
			ref={ref}
			className="cr-dialog"
			onClose={() => {
				setDismissed(true);
			}}
		>
			<div className="cr-wizard__dots" aria-hidden="true">
				{Array.from({ length: STEP_COUNT }, (_unused, index) => (
					<span
						key={index}
						className={`cr-wizard__dot${
							index === step ? ' is-active' : ''
						}${index < step ? ' is-done' : ''}`}
					/>
				))}
			</div>

			<h2 className="cr-wizard__title">{current.title}</h2>
			<div className="cr-wizard__body">{current.body}</div>

			<div className="cr-wizard__footer">
				<button
					type="button"
					className="cr-btn is-ghost is-sm"
					onClick={() => finish(false)}
				>
					{__('Skip setup', 'cart-rebound')}
				</button>
				<span className="cr-wizard__spacer" />
				{step > 0 && (
					<button
						type="button"
						className="cr-btn is-ghost"
						onClick={() => setStep((value) => value - 1)}
					>
						{__('Back', 'cart-rebound')}
					</button>
				)}
				{isLast ? (
					<button
						type="button"
						className="cr-btn is-primary"
						onClick={() => finish(true)}
					>
						{__('Finish', 'cart-rebound')}
					</button>
				) : (
					<button
						type="button"
						className="cr-btn is-primary"
						onClick={() => setStep((value) => value + 1)}
					>
						{__('Next', 'cart-rebound')}
					</button>
				)}
			</div>
		</dialog>
	);
};
