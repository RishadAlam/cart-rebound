/**
 * First-run setup wizard.
 *
 * A short, skippable guided setup shown until the merchant completes or skips
 * it. It writes the handful of settings that get recovery working, then flips
 * the `onboarding_complete` flag so it never shows again.
 */
import {
	useEffect,
	useRef,
	useState,
	type ChangeEvent,
	type ReactNode,
} from 'react';
import { __ } from '@wordpress/i18n';
import { useSettings, useUpdateSettings } from '../hooks/useApi';

interface WizardForm {
	guest_tracking: boolean;
	abandonment_threshold: number;
	recovery_email_enabled: boolean;
	email_delay_minutes: number;
}

const STEP_COUNT = 4;

const SparkIcon = () => (
	<svg
		viewBox="0 0 24 24"
		fill="none"
		aria-hidden="true"
		width="22"
		height="22"
	>
		<path
			d="M12 3v4M12 17v4M3 12h4M17 12h4M6.3 6.3l2.4 2.4M15.3 15.3l2.4 2.4M17.7 6.3l-2.4 2.4M8.7 15.3l-2.4 2.4"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
		/>
	</svg>
);

const UsersIcon = () => (
	<svg
		viewBox="0 0 24 24"
		fill="none"
		aria-hidden="true"
		width="22"
		height="22"
	>
		<circle cx="9" cy="8" r="3.2" stroke="currentColor" strokeWidth="1.6" />
		<path
			d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3.2 3.2 0 0 1 0 6.2M17 19a5.5 5.5 0 0 0-2.6-4.7"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
		/>
	</svg>
);

const ClockIcon = () => (
	<svg
		viewBox="0 0 24 24"
		fill="none"
		aria-hidden="true"
		width="22"
		height="22"
	>
		<circle
			cx="12"
			cy="12"
			r="8.2"
			stroke="currentColor"
			strokeWidth="1.6"
		/>
		<path
			d="M12 7.5V12l3 2"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const MailIcon = () => (
	<svg
		viewBox="0 0 24 24"
		fill="none"
		aria-hidden="true"
		width="22"
		height="22"
	>
		<rect
			x="3.5"
			y="5.5"
			width="17"
			height="13"
			rx="2"
			stroke="currentColor"
			strokeWidth="1.6"
		/>
		<path
			d="m4.5 7 7.5 5.5L19.5 7"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const CheckIcon = () => (
	<svg
		viewBox="0 0 16 16"
		fill="none"
		aria-hidden="true"
		width="12"
		height="12"
	>
		<path
			d="m3.5 8.5 3 3 6-7"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

// The plugin mark — the shopping cart from .wordpress-org/icon.svg (Heroicons,
// MIT), drawn in the current text colour so the accent tile shows through.
const BrandMark = () => (
	<svg
		viewBox="0 0 24 24"
		fill="currentColor"
		aria-hidden="true"
		width="15"
		height="15"
	>
		<path d="M2.25 2.25a.75.75 0 0 0 0 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 0 0-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 0 0 0-1.5H5.378A2.25 2.25 0 0 1 7.5 15h11.218a.75.75 0 0 0 .674-.421 60.358 60.358 0 0 0 2.96-7.228.75.75 0 0 0-.525-.965A60.864 60.864 0 0 0 5.68 4.509l-.232-.867A1.875 1.875 0 0 0 3.636 2.25H2.25ZM3.75 20.25a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM16.5 20.25a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Z" />
	</svg>
);

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

	const stepLabels = [
		__('Welcome', 'cart-rebound'),
		__('Guest carts', 'cart-rebound'),
		__('Timing', 'cart-rebound'),
		__('Email', 'cart-rebound'),
	];

	const steps: Array<{
		icon: ReactNode;
		title: string;
		subtitle: string;
		body: ReactNode;
	}> = [
		{
			icon: <SparkIcon />,
			title: __('Welcome to Cart Rebound', 'cart-rebound'),
			subtitle: __(
				'A few quick choices and you’ll be recovering abandoned carts. Everything here can be changed later in Settings.',
				'cart-rebound'
			),
			body: (
				<ul className="cr-onb__list">
					<li>
						{__(
							'Tracks every cart and the email shoppers enter at checkout.',
							'cart-rebound'
						)}
					</li>
					<li>
						{__(
							'Emails shoppers a one-click link back to their cart.',
							'cart-rebound'
						)}
					</li>
					<li>
						{__(
							'Attributes the paid orders you win back to revenue.',
							'cart-rebound'
						)}
					</li>
				</ul>
			),
		},
		{
			icon: <UsersIcon />,
			title: __('Track guest carts', 'cart-rebound'),
			subtitle: __(
				'Capture carts and the email guests enter at checkout, not just logged-in customers — it’s where most abandonment happens.',
				'cart-rebound'
			),
			body: (
				<div className="cr-onb__row">
					<div>
						<label htmlFor="cr-wiz-guest" className="cr-onb__label">
							{__('Track logged-out shoppers', 'cart-rebound')}
						</label>
						<p className="cr-onb__hint">
							{__('Recommended for most stores.', 'cart-rebound')}
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
			icon: <ClockIcon />,
			title: __('When is a cart abandoned?', 'cart-rebound'),
			subtitle: __(
				'How long a cart can sit idle before Cart Rebound counts it as abandoned and starts recovery.',
				'cart-rebound'
			),
			body: (
				<div>
					<label htmlFor="cr-wiz-threshold" className="cr-onb__label">
						{__('Abandonment threshold', 'cart-rebound')}
					</label>
					<div className="cr-onb__inputrow">
						<input
							id="cr-wiz-threshold"
							className="cr-input"
							type="number"
							min={1}
							value={form.abandonment_threshold}
							onChange={onNumber('abandonment_threshold')}
						/>
						<span className="cr-onb__unit">
							{__('minutes', 'cart-rebound')}
						</span>
					</div>
					<p className="cr-onb__hint">
						{__(
							'30–60 minutes works well for most stores.',
							'cart-rebound'
						)}
					</p>
				</div>
			),
		},
		{
			icon: <MailIcon />,
			title: __('Recovery email', 'cart-rebound'),
			subtitle: __(
				'Send one follow-up with a one-click link back to the cart. You can fine-tune the wording on the Templates tab.',
				'cart-rebound'
			),
			body: (
				<>
					<div className="cr-onb__row">
						<div>
							<label
								htmlFor="cr-wiz-email"
								className="cr-onb__label"
							>
								{__('Send a recovery email', 'cart-rebound')}
							</label>
							<p className="cr-onb__hint">
								{__(
									'Automatically, after the delay below.',
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
						<div className="cr-onb__delay">
							<label
								htmlFor="cr-wiz-delay"
								className="cr-onb__label"
							>
								{__('Send delay', 'cart-rebound')}
							</label>
							<div className="cr-onb__inputrow">
								<input
									id="cr-wiz-delay"
									className="cr-input"
									type="number"
									min={1}
									value={form.email_delay_minutes}
									onChange={onNumber('email_delay_minutes')}
								/>
								<span className="cr-onb__unit">
									{__(
										'minutes after abandonment',
										'cart-rebound'
									)}
								</span>
							</div>
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
			className="cr-onb"
			aria-label={__('Set up Cart Rebound', 'cart-rebound')}
			onClose={() => {
				setDismissed(true);
			}}
		>
			<div className="cr-onb__grid">
				<aside className="cr-onb__rail">
					<div className="cr-onb__brand">
						<span className="cr-onb__mark" aria-hidden="true">
							<BrandMark />
						</span>
						{__('Cart Rebound', 'cart-rebound')}
					</div>

					<ol className="cr-onb__steps">
						{stepLabels.map((label, index) => (
							<li
								key={label}
								className={`cr-onb__step${
									index === step ? ' is-active' : ''
								}${index < step ? ' is-done' : ''}`}
								aria-current={
									index === step ? 'step' : undefined
								}
							>
								<span className="cr-onb__badge">
									{index < step ? <CheckIcon /> : index + 1}
								</span>
								<span className="cr-onb__steplabel">
									{label}
								</span>
							</li>
						))}
					</ol>

					<p className="cr-onb__railnote">
						{__('Takes about a minute.', 'cart-rebound')}
					</p>
				</aside>

				<section className="cr-onb__main">
					<div key={step} className="cr-onb__content">
						<span className="cr-onb__icon" aria-hidden="true">
							{current.icon}
						</span>
						<h2 className="cr-onb__title">{current.title}</h2>
						<p className="cr-onb__subtitle">{current.subtitle}</p>
						<div className="cr-onb__fields">{current.body}</div>
					</div>

					<div className="cr-onb__footer">
						<button
							type="button"
							className="cr-btn is-ghost is-sm"
							onClick={() => finish(false)}
						>
							{__('Skip', 'cart-rebound')}
						</button>
						<span className="cr-onb__spacer" />
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
								{__('Finish setup', 'cart-rebound')}
							</button>
						) : (
							<button
								type="button"
								className="cr-btn is-primary"
								onClick={() => setStep((value) => value + 1)}
							>
								{__('Continue', 'cart-rebound')}
							</button>
						)}
					</div>
				</section>
			</div>
		</dialog>
	);
};
