/**
 * Rules — which carts are worth chasing, and which coupon they are chased with.
 *
 * The two halves of this screen answer opposite questions and are kept apart
 * for that reason. Exclusions decide who never enters the funnel; the coupon
 * policy decides what the ones who do are offered. Merging them into one list
 * of "settings" would hide that a coupon is a cost and an exclusion is a saving.
 */
import {
	useEffect,
	useState,
	type ChangeEvent,
	type FormEvent,
	type ReactNode,
} from 'react';
import { __ } from '@wordpress/i18n';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ProSurface } from '../components/ProSurface';
import { useProQuery } from '../hooks/useAddons';
import {
	fetchProOptions,
	fetchProSettings,
	updateProSettings,
} from '../api/pro';
import { sampleProOptions, sampleProSettings } from '../lib/sample';
import type { ProSettings } from '../types/api';

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

export const Rules = () => {
	const queryClient = useQueryClient();

	const settings = useProQuery(
		'rules',
		['pro', 'settings'],
		fetchProSettings,
		{ settings: sampleProSettings(), features: [] }
	);
	const options = useProQuery(
		'rules',
		['pro', 'options'],
		fetchProOptions,
		sampleProOptions(),
		{ staleTime: 60_000 }
	);

	const [form, setForm] = useState<ProSettings | null>(null);

	useEffect(() => {
		setForm(settings.data.settings);
	}, [settings.data]);

	const save = useMutation({
		mutationFn: updateProSettings,
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: ['pro'] });
		},
	});

	const current = form ?? settings.data.settings;

	const setField = <K extends keyof ProSettings>(
		key: K,
		value: ProSettings[K]
	) => {
		setForm({ ...current, [key]: value });
	};

	const onNumber =
		(
			key:
				| 'min_cart_total'
				| 'coupon_amount'
				| 'coupon_expiry_hours'
				| 'coupon_min_amount'
		) =>
		(event: ChangeEvent<HTMLInputElement>) => {
			const parsed = Number.parseFloat(event.target.value);

			setField(key, Number.isNaN(parsed) ? 0 : Math.max(0, parsed));
		};

	const toggleRole =
		(role: string) => (event: ChangeEvent<HTMLInputElement>) => {
			const next = new Set(current.excluded_roles);

			if (event.target.checked) {
				next.add(role);
			} else {
				next.delete(role);
			}

			setField('excluded_roles', [...next]);
		};

	const toggleCategory =
		(category: string) => (event: ChangeEvent<HTMLInputElement>) => {
			const id = Number.parseInt(category, 10);
			const next = new Set(current.excluded_categories);

			if (event.target.checked) {
				next.add(id);
			} else {
				next.delete(id);
			}

			setField('excluded_categories', [...next]);
		};

	const onSubmit = (event: FormEvent<HTMLFormElement>) => {
		event.preventDefault();
		save.mutate(current);
	};

	const toggle = (key: keyof ProSettings, id: string) => (
		<span className="cr-switch">
			<input
				id={id}
				type="checkbox"
				checked={Boolean(current[key])}
				onChange={(event: ChangeEvent<HTMLInputElement>) => {
					setField(key, event.target.checked as never);
				}}
			/>
			<span className="cr-switch__track">
				<span className="cr-switch__thumb" />
			</span>
		</span>
	);

	return (
		<ProSurface
			feature="rules"
			title={__('Chase the carts worth chasing', 'cart-rebound')}
			summary={__(
				'Keep staff carts, tiny baskets, and products you would rather not discount out of the funnel — and give the ones that stay a coupon that cannot be shared.',
				'cart-rebound'
			)}
			points={[
				__(
					'Skip carts under a minimum value, or from roles like administrator and wholesale',
					'cart-rebound'
				),
				__(
					'Exclude specific categories from recovery entirely',
					'cart-rebound'
				),
				__(
					'Mint a unique, single-use, expiring coupon per cart instead of one shared code',
					'cart-rebound'
				),
				__(
					'Optionally lock each code to the address it was sent to',
					'cart-rebound'
				),
			]}
		>
			<form
				onSubmit={onSubmit}
				className="cr-card"
				style={{ maxWidth: 760 }}
			>
				<div className="cr-section">
					<h2 className="cr-section__title">
						{__('Who enters recovery', 'cart-rebound')}
					</h2>
					<p className="cr-section__desc">
						{__(
							'An excluded cart is still tracked and still counts towards your revenue figures — it simply never enters the funnel and is never emailed.',
							'cart-rebound'
						)}
					</p>

					<div className="cr-field__grid">
						<Field
							id="cr-min-total"
							label={__('Minimum cart total', 'cart-rebound')}
							hint={__(
								'Carts below this are never abandoned into the funnel. Set 0 to chase everything.',
								'cart-rebound'
							)}
						>
							<input
								id="cr-min-total"
								className="cr-input"
								type="number"
								min={0}
								step="0.01"
								value={current.min_cart_total}
								onChange={onNumber('min_cart_total')}
							/>
						</Field>
					</div>

					<div className="cr-field">
						<span className="cr-field__label">
							{__('Excluded roles', 'cart-rebound')}
						</span>
						<div className="cr-checks">
							{options.data.roles.map((role) => (
								<label
									key={role.value}
									htmlFor={`cr-role-${role.value}`}
									className="cr-check"
								>
									<input
										id={`cr-role-${role.value}`}
										type="checkbox"
										checked={current.excluded_roles.includes(
											role.value
										)}
										onChange={toggleRole(role.value)}
									/>
									<span>{role.label}</span>
								</label>
							))}
						</div>
						<p className="cr-field__hint">
							{__(
								'Carts from these roles are never tracked at all, so there is nothing stored to export or erase later.',
								'cart-rebound'
							)}
						</p>
					</div>

					<div className="cr-field">
						<span className="cr-field__label">
							{__('Excluded categories', 'cart-rebound')}
						</span>
						<div className="cr-checks">
							{options.data.categories.map((category) => (
								<label
									key={category.value}
									htmlFor={`cr-cat-${category.value}`}
									className="cr-check"
								>
									<input
										id={`cr-cat-${category.value}`}
										type="checkbox"
										checked={current.excluded_categories.includes(
											Number.parseInt(category.value, 10)
										)}
										onChange={toggleCategory(
											category.value
										)}
									/>
									<span>{category.label}</span>
								</label>
							))}
						</div>
						<p className="cr-field__hint">
							{__(
								'A cart containing any of these is left out of recovery.',
								'cart-rebound'
							)}
						</p>
					</div>
				</div>

				<div className="cr-section">
					<h2 className="cr-section__title">
						{__('Coupon policy', 'cart-rebound')}
					</h2>
					<p className="cr-section__desc">
						{__(
							'Steps marked "include a coupon" mint a fresh code per cart. Unlike a static code it cannot be shared, reused, or posted to a deals site.',
							'cart-rebound'
						)}
					</p>

					<div className="cr-field--row">
						<div>
							<label
								htmlFor="cr-coupon-auto"
								className="cr-field__label"
							>
								{__('Generate unique coupons', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'Off means coupon steps fall back to the static code on the template.',
									'cart-rebound'
								)}
							</p>
						</div>
						{toggle('coupon_auto', 'cr-coupon-auto')}
					</div>

					<div className="cr-field__grid">
						<Field
							id="cr-coupon-type"
							label={__('Discount type', 'cart-rebound')}
						>
							<select
								id="cr-coupon-type"
								className="cr-input"
								value={current.coupon_type}
								onChange={(
									event: ChangeEvent<HTMLSelectElement>
								) => {
									setField(
										'coupon_type',
										event.target.value === 'fixed'
											? 'fixed'
											: 'percent'
									);
								}}
							>
								<option value="percent">
									{__('Percentage', 'cart-rebound')}
								</option>
								<option value="fixed">
									{__('Fixed cart discount', 'cart-rebound')}
								</option>
							</select>
						</Field>

						<Field
							id="cr-coupon-amount"
							label={__('Amount', 'cart-rebound')}
						>
							<input
								id="cr-coupon-amount"
								className="cr-input"
								type="number"
								min={0}
								step="0.01"
								value={current.coupon_amount}
								onChange={onNumber('coupon_amount')}
							/>
						</Field>

						<Field
							id="cr-coupon-expiry"
							label={__('Expires after (hours)', 'cart-rebound')}
							hint={__(
								'Short windows create urgency; expired unused codes are cleaned up automatically.',
								'cart-rebound'
							)}
						>
							<input
								id="cr-coupon-expiry"
								className="cr-input"
								type="number"
								min={1}
								value={current.coupon_expiry_hours}
								onChange={onNumber('coupon_expiry_hours')}
							/>
						</Field>

						<Field
							id="cr-coupon-min"
							label={__('Minimum spend', 'cart-rebound')}
							hint={__(
								'Leave at 0 for no minimum.',
								'cart-rebound'
							)}
						>
							<input
								id="cr-coupon-min"
								className="cr-input"
								type="number"
								min={0}
								step="0.01"
								value={current.coupon_min_amount}
								onChange={onNumber('coupon_min_amount')}
							/>
						</Field>

						<Field
							id="cr-coupon-prefix"
							label={__('Code prefix', 'cart-rebound')}
							hint={__(
								'Codes look like PREFIX-A1B2C3D4.',
								'cart-rebound'
							)}
						>
							<input
								id="cr-coupon-prefix"
								className="cr-input"
								type="text"
								value={current.coupon_prefix}
								onChange={(
									event: ChangeEvent<HTMLInputElement>
								) => {
									setField(
										'coupon_prefix',
										event.target.value
									);
								}}
							/>
						</Field>
					</div>

					<div className="cr-field--row">
						<div>
							<label
								htmlFor="cr-coupon-restrict"
								className="cr-field__label"
							>
								{__(
									'Lock each code to its recipient',
									'cart-rebound'
								)}
							</label>
							<p className="cr-field__hint">
								{__(
									'The code only works for the address it was emailed to.',
									'cart-rebound'
								)}
							</p>
						</div>
						{toggle('coupon_restrict_email', 'cr-coupon-restrict')}
					</div>

					<div className="cr-field--row">
						<div>
							<label
								htmlFor="cr-coupon-shipping"
								className="cr-field__label"
							>
								{__('Also grant free shipping', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'Often converts better than a larger discount.',
									'cart-rebound'
								)}
							</p>
						</div>
						{toggle('coupon_free_shipping', 'cr-coupon-shipping')}
					</div>
				</div>

				<div className="cr-section">
					<h2 className="cr-section__title">
						{__('Measurement', 'cart-rebound')}
					</h2>

					<div className="cr-field--row">
						<div>
							<label
								htmlFor="cr-track-opens"
								className="cr-field__label"
							>
								{__('Track opens', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'Adds a tracking pixel. Opens are always approximate — image proxies and blocked images undercount them.',
									'cart-rebound'
								)}
							</p>
						</div>
						{toggle('tracking_opens', 'cr-track-opens')}
					</div>

					<div className="cr-field--row">
						<div>
							<label
								htmlFor="cr-track-clicks"
								className="cr-field__label"
							>
								{__('Track clicks', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'Routes links through a redirect so clicks can be attributed to a step.',
									'cart-rebound'
								)}
							</p>
						</div>
						{toggle('tracking_clicks', 'cr-track-clicks')}
					</div>

					<div className="cr-field__grid">
						<Field
							id="cr-retention"
							label={__(
								'Keep email events for (days)',
								'cart-rebound'
							)}
							hint={__(
								'Older open and click records are pruned nightly.',
								'cart-rebound'
							)}
						>
							<input
								id="cr-retention"
								className="cr-input"
								type="number"
								min={1}
								value={current.analytics_retention_days}
								onChange={(
									event: ChangeEvent<HTMLInputElement>
								) => {
									const parsed = Number.parseInt(
										event.target.value,
										10
									);

									setField(
										'analytics_retention_days',
										Number.isNaN(parsed)
											? 1
											: Math.max(1, parsed)
									);
								}}
							/>
						</Field>
					</div>
				</div>

				<div className="cr-savebar">
					<button
						type="submit"
						className="cr-btn is-primary"
						disabled={save.isPending}
					>
						{save.isPending
							? __('Saving…', 'cart-rebound')
							: __('Save rules', 'cart-rebound')}
					</button>
					{save.isSuccess && (
						<span className="cr-saved">
							{__('Rules saved.', 'cart-rebound')}
						</span>
					)}
				</div>
			</form>
		</ProSurface>
	);
};
