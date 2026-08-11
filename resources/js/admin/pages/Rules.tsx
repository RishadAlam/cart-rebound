/**
 * Rules — which carts are worth chasing, and which coupon they are chased with.
 *
 * The two halves of this screen answer opposite questions and are kept apart
 * for that reason. Exclusions decide who never enters the funnel; the coupon
 * policy decides what the ones who do are offered. Merging them into one list
 * of "settings" would hide that a coupon is a cost and an exclusion is a saving.
 */
import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Combobox } from '../components/Combobox';
import { Field, ToggleField } from '../components/Field';
import { NumberField } from '../components/NumberField';
import { ProSurface } from '../components/ProSurface';
import { SaveBar } from '../components/SaveBar';
import { TokenPicker } from '../components/TokenPicker';
import { useProQuery } from '../hooks/useAddons';
import { useUnsavedGuard } from '../hooks/useUnsavedGuard';
import {
	fetchProOptions,
	fetchProSettings,
	updateProSettings,
} from '../api/pro';
import { sampleProOptions, sampleProSettings } from '../lib/sample';
import { formatMoney } from '../lib/format';
import type { ProSettings, ProSettingsResponse } from '../types/api';

/*
 * Built once, for the same reason as on the Sequence screen: a sample rebuilt on
 * every render is a new object every render, and the effect that seeds the form
 * from it then sets state forever.
 */
const SAMPLE_SETTINGS: ProSettingsResponse = {
	settings: sampleProSettings(),
	features: [],
};
const SAMPLE_OPTIONS = sampleProOptions();

export const Rules = () => {
	const queryClient = useQueryClient();
	/*
	 * Four fields on this screen are amounts and none of them said in what. A
	 * minimum cart total of "20" is a different rule in USD and in JPY, and the
	 * discount amount means percent or money depending on a select three fields
	 * away. The unit belongs in the label.
	 */
	const currency = window.CartRebound?.currency.code ?? '';

	const settings = useProQuery(
		'rules',
		['pro', 'settings'],
		fetchProSettings,
		SAMPLE_SETTINGS
	);
	const options = useProQuery(
		'rules',
		['pro', 'options'],
		fetchProOptions,
		SAMPLE_OPTIONS,
		{ staleTime: 60_000 }
	);

	const [form, setForm] = useState<ProSettings | null>(null);

	useEffect(() => {
		setForm(settings.data.settings);
	}, [settings.data.settings]);

	const save = useMutation({
		mutationFn: updateProSettings,
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: ['pro'] });
		},
	});

	const current = form ?? settings.data.settings;

	// Edits live in component state, so leaving the screen throws them away.
	useUnsavedGuard(
		JSON.stringify(current) !== JSON.stringify(settings.data.settings)
	);

	const setField = <K extends keyof ProSettings>(
		key: K,
		value: ProSettings[K]
	) => {
		setForm({ ...current, [key]: value });
	};

	/*
	 * Post only what this screen edits.
	 *
	 * It used to send the whole ProSettings object, `sequence_steps` included —
	 * a slice it never touches. The server merges a partial body over what is
	 * stored, so a merchant with both screens open (or one stale tab) could save
	 * Rules and roll the sequence back to whatever this page had loaded.
	 */
	const onSubmit = (event: FormEvent<HTMLFormElement>) => {
		event.preventDefault();
		save.mutate({
			min_cart_total: current.min_cart_total,
			excluded_roles: current.excluded_roles,
			excluded_categories: current.excluded_categories,
			coupon_auto: current.coupon_auto,
			coupon_type: current.coupon_type,
			coupon_amount: current.coupon_amount,
			coupon_expiry_hours: current.coupon_expiry_hours,
			coupon_min_amount: current.coupon_min_amount,
			coupon_restrict_email: current.coupon_restrict_email,
			coupon_free_shipping: current.coupon_free_shipping,
			coupon_prefix: current.coupon_prefix,
			tracking_opens: current.tracking_opens,
			tracking_clicks: current.tracking_clicks,
			analytics_retention_days: current.analytics_retention_days,
		});
	};

	/*
	 * Never seed this form from the sample store on a licensed site.
	 *
	 * `useProQuery` falls back to the sample whenever the real response is
	 * missing — which includes "the request failed". The form seeded itself from
	 * that, showed no error, and "Save rules" would then write a demo store's
	 * exclusions and coupon policy over the merchant's own.
	 */
	if (settings.isError) {
		return (
			<div className="cr-notice is-error" role="alert">
				{__(
					'Could not load your rules. Reload the page to try again — nothing has been changed.',
					'cart-rebound'
				)}
			</div>
		);
	}

	if (settings.isLoading) {
		return (
			<div className="cr-card cr-section">
				<div
					className="cr-skeleton"
					style={{ height: 16, width: '35%' }}
				/>
				<div
					className="cr-skeleton"
					style={{ height: 220, width: '100%', marginTop: 16 }}
				/>
			</div>
		);
	}

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
			<form onSubmit={onSubmit} className="cr-card">
				<div className="cr-section">
					<h2 className="cr-section__title">
						{__('Who enters recovery', 'cart-rebound')}
					</h2>
					<p className="cr-section__desc">
						{__(
							'A cart these rules exclude stays Active: it is never emailed, and it never appears in your abandoned or recoverable figures. Carts from excluded roles are not stored at all.',
							'cart-rebound'
						)}
					</p>

					<div className="cr-field__grid">
						<Field
							id="cr-min-total"
							label={
								currency === ''
									? __('Minimum cart total', 'cart-rebound')
									: sprintf(
											/* translators: %s: store currency code, e.g. USD. */
											__(
												'Minimum cart total (%s)',
												'cart-rebound'
											),
											currency
										)
							}
							hint={__(
								'Carts below this are never abandoned into the funnel. Set 0 to chase everything.',
								'cart-rebound'
							)}
						>
							<NumberField
								id="cr-min-total"
								min={0}
								step="0.01"
								decimal
								value={current.min_cart_total}
								onChange={(next) => {
									setField('min_cart_total', next);
								}}
							/>
						</Field>
					</div>

					{/*
					 * A failed options request used to fall back to the sample
					 * store, whose role keys and category ids are literals in
					 * lib/sample.ts. Picking one wrote a fictional id into the
					 * real exclusion list, where it would silently match nothing —
					 * or, worse, match whatever term later took that id.
					 */}
					{options.isError && (
						<div className="cr-notice is-error" role="alert">
							{__(
								'Could not load your roles and categories, so exclusions cannot be edited right now. Reload the page to try again — the rules already saved are still in force.',
								'cart-rebound'
							)}
						</div>
					)}

					<div className="cr-field">
						<span className="cr-field__label">
							{__('Excluded roles', 'cart-rebound')}
						</span>
						<TokenPicker
							id="cr-roles"
							disabled={options.isError}
							options={options.data.roles}
							selected={current.excluded_roles}
							searchLabel={__(
								'Filter roles to exclude',
								'cart-rebound'
							)}
							placeholder={__(
								'Type a role name…',
								'cart-rebound'
							)}
							emptyLabel={__(
								'No roles excluded — every signed-in shopper is tracked.',
								'cart-rebound'
							)}
							onChange={(next) => {
								setField('excluded_roles', next);
							}}
						/>
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
						<TokenPicker
							id="cr-categories"
							disabled={options.isError}
							options={options.data.categories}
							selected={current.excluded_categories.map(String)}
							searchLabel={__(
								'Filter categories to exclude',
								'cart-rebound'
							)}
							placeholder={__(
								'Type a category name…',
								'cart-rebound'
							)}
							emptyLabel={__(
								'No categories excluded — every product is chased.',
								'cart-rebound'
							)}
							onChange={(next) => {
								setField(
									'excluded_categories',
									next.map((value) =>
										Number.parseInt(value, 10)
									)
								);
							}}
						/>
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

					<ToggleField
						id="cr-coupon-auto"
						label={__('Generate unique coupons', 'cart-rebound')}
						hint={__(
							'Off means coupon steps fall back to the static code on the template.',
							'cart-rebound'
						)}
						checked={current.coupon_auto}
						onChange={(checked) => {
							setField('coupon_auto', checked);
						}}
					/>

					{/*
					 * With the switch off none of these fields reaches a shopper —
					 * the minter is not called at all. They used to sit fully
					 * enabled and undimmed, so the only way to learn that was to
					 * set a discount, send a sequence, and find the static code in
					 * the email instead.
					 */}
					{!current.coupon_auto && (
						<p className="cr-field__hint is-warning">
							{__(
								'Unique coupons are off, so these settings are not in use. Steps that include a coupon send the static code on their template instead.',
								'cart-rebound'
							)}
						</p>
					)}

					<fieldset
						className="cr-fieldset"
						disabled={!current.coupon_auto}
					>
						<div className="cr-field__grid">
							<Field
								id="cr-coupon-type"
								label={__('Discount type', 'cart-rebound')}
							>
								<Combobox
									id="cr-coupon-type"
									ariaLabel={__(
										'Discount type',
										'cart-rebound'
									)}
									value={current.coupon_type}
									options={[
										{
											value: 'percent',
											label: __(
												'Percentage',
												'cart-rebound'
											),
										},
										{
											value: 'fixed',
											label: __(
												'Fixed cart discount',
												'cart-rebound'
											),
										},
									]}
									onChange={(next) => {
										setField(
											'coupon_type',
											next === 'fixed'
												? 'fixed'
												: 'percent'
										);
									}}
								/>
							</Field>

							<Field
								id="cr-coupon-amount"
								label={
									'percent' === current.coupon_type
										? __('Amount (%)', 'cart-rebound')
										: sprintf(
												/* translators: %s: store currency code, e.g. USD. */
												__(
													'Amount (%s)',
													'cart-rebound'
												),
												currency
											)
								}
							>
								<NumberField
									id="cr-coupon-amount"
									min={0}
									step="0.01"
									decimal
									value={current.coupon_amount}
									onChange={(next) => {
										setField('coupon_amount', next);
									}}
								/>
							</Field>

							<Field
								id="cr-coupon-expiry"
								label={__(
									'Expires after (hours)',
									'cart-rebound'
								)}
								hint={__(
									'WooCommerce expires coupons by date, so a code lasts until the end of the following UTC day — anything under 24 hours behaves the same. Expired unused codes are cleaned up automatically.',
									'cart-rebound'
								)}
							>
								<NumberField
									id="cr-coupon-expiry"
									min={1}
									value={current.coupon_expiry_hours}
									onChange={(next) => {
										setField('coupon_expiry_hours', next);
									}}
								/>
							</Field>

							{/*
							 * A minimum spend above the minimum cart total mints codes
							 * the recipient's own cart can never redeem — the discount
							 * is offered, the shopper returns, and it refuses to apply.
							 * Both numbers are on this screen, so the contradiction is
							 * stated rather than left to be discovered in a support
							 * ticket.
							 */}
							<Field
								id="cr-coupon-min"
								{...(current.coupon_min_amount >
								current.min_cart_total
									? {
											error: sprintf(
												/* translators: 1: minimum cart total, 2: coupon minimum spend. */
												__(
													'Carts from %1$s enter recovery, but this code needs %2$s to apply — the smallest of them cannot use it.',
													'cart-rebound'
												),
												formatMoney(
													current.min_cart_total,
													currency
												),
												formatMoney(
													current.coupon_min_amount,
													currency
												)
											),
										}
									: {})}
								label={
									currency === ''
										? __('Minimum spend', 'cart-rebound')
										: sprintf(
												/* translators: %s: store currency code, e.g. USD. */
												__(
													'Minimum spend (%s)',
													'cart-rebound'
												),
												currency
											)
								}
								hint={__(
									'Leave at 0 for no minimum.',
									'cart-rebound'
								)}
							>
								<NumberField
									id="cr-coupon-min"
									min={0}
									step="0.01"
									decimal
									value={current.coupon_min_amount}
									onChange={(next) => {
										setField('coupon_min_amount', next);
									}}
								/>
							</Field>

							{/*
							 * The server strips punctuation, uppercases, truncates to
							 * 16 and falls back to REBOUND — none of which was stated,
							 * so a merchant typing "spring sale!" saved something and
							 * got SPRINGSALE. The control now accepts only what
							 * survives, and the hint shows the code being built.
							 */}
							<Field
								id="cr-coupon-prefix"
								label={__('Code prefix', 'cart-rebound')}
								hint={sprintf(
									/* translators: %s: an example coupon code, e.g. REBOUND-A1B2C3D4. */
									__(
										'Letters and numbers only, up to 16. Codes look like %s.',
										'cart-rebound'
									),
									`${current.coupon_prefix.trim() === '' ? 'REBOUND' : current.coupon_prefix}-A1B2C3D4`
								)}
							>
								<input
									id="cr-coupon-prefix"
									className="cr-input"
									type="text"
									maxLength={16}
									value={current.coupon_prefix}
									onChange={(
										event: ChangeEvent<HTMLInputElement>
									) => {
										setField(
											'coupon_prefix',
											event.target.value
												.replace(/[^A-Za-z0-9]/g, '')
												.toUpperCase()
										);
									}}
								/>
							</Field>
						</div>

						<ToggleField
							id="cr-coupon-restrict"
							label={__(
								'Lock each code to its recipient',
								'cart-rebound'
							)}
							hint={__(
								'The code only works for the address it was emailed to.',
								'cart-rebound'
							)}
							checked={current.coupon_restrict_email}
							onChange={(checked) => {
								setField('coupon_restrict_email', checked);
							}}
						/>

						<ToggleField
							id="cr-coupon-shipping"
							label={__(
								'Also grant free shipping',
								'cart-rebound'
							)}
							hint={__(
								'Often converts better than a larger discount.',
								'cart-rebound'
							)}
							checked={current.coupon_free_shipping}
							onChange={(checked) => {
								setField('coupon_free_shipping', checked);
							}}
						/>
					</fieldset>
				</div>

				<div className="cr-section">
					<h2 className="cr-section__title">
						{__('Measurement', 'cart-rebound')}
					</h2>

					<ToggleField
						id="cr-track-opens"
						label={__('Track opens', 'cart-rebound')}
						hint={__(
							'Adds a tracking pixel. Opens are always approximate — image proxies and blocked images undercount them.',
							'cart-rebound'
						)}
						checked={current.tracking_opens}
						onChange={(checked) => {
							setField('tracking_opens', checked);
						}}
					/>

					<ToggleField
						id="cr-track-clicks"
						label={__('Track clicks', 'cart-rebound')}
						hint={__(
							'Routes links through a redirect so clicks can be attributed to a step.',
							'cart-rebound'
						)}
						checked={current.tracking_clicks}
						onChange={(checked) => {
							setField('tracking_clicks', checked);
						}}
					/>

					<div className="cr-field__grid">
						<Field
							id="cr-retention"
							label={__(
								'Keep email events for (days)',
								'cart-rebound'
							)}
							hint={__(
								'Older open and click records are pruned nightly. Analytics can only report what is still inside this window.',
								'cart-rebound'
							)}
							{...(current.analytics_retention_days < 90
								? {
										error: sprintf(
											/* translators: %d: the configured retention in days. */
											__(
												'Analytics offers a 90-day range but only %d days of email events are kept, so its longest view will be incomplete.',
												'cart-rebound'
											),
											current.analytics_retention_days
										),
									}
								: {})}
						>
							<NumberField
								id="cr-retention"
								min={1}
								value={current.analytics_retention_days}
								onChange={(next) => {
									setField('analytics_retention_days', next);
								}}
							/>
						</Field>
					</div>
				</div>

				<SaveBar
					label={__('Save rules', 'cart-rebound')}
					savedLabel={__('Rules saved.', 'cart-rebound')}
					errorFallback={__(
						'The rules could not be saved. Please try again.',
						'cart-rebound'
					)}
					isPending={save.isPending}
					isSuccess={save.isSuccess}
					isError={save.isError}
					error={save.error}
					onReset={save.reset}
				/>
			</form>
		</ProSurface>
	);
};
