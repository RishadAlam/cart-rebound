/**
 * License — the one Pro screen that is never locked.
 *
 * Gating this on the licence would put the key behind the key. So it gates on
 * installation only: with an add-on present it is the real form, and without
 * one it says so plainly rather than pretending there is something to activate.
 *
 * Activating invalidates the add-on state, which is what unlocks every other
 * screen without a reload — the registry is re-read, the features arrive, and
 * the locks lift where the merchant is already standing.
 */
import { useState, type FormEvent } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
	activateLicense,
	deactivateLicense,
	fetchLicense,
	refreshLicense,
} from '../api/pro';
import { useAddons } from '../hooks/useAddons';
import { sampleLicense } from '../lib/sample';
import type { LicenseState, LicenseStatus } from '../types/api';

const STATUS_LABEL: Record<LicenseStatus, string> = {
	active: __('Active', 'cart-rebound'),
	expired: __('Expired', 'cart-rebound'),
	invalid: __('Invalid', 'cart-rebound'),
	unlicensed: __('Not activated', 'cart-rebound'),
};

const STATUS_CLASS: Record<LicenseStatus, string> = {
	active: 'cr-logbadge is-success',
	expired: 'cr-logbadge is-warning',
	invalid: 'cr-logbadge is-error',
	unlicensed: 'cr-logbadge is-info',
};

const formatDate = (iso: string): string => {
	if (iso === '') {
		return '';
	}

	const date = new Date(iso);

	if (Number.isNaN(date.getTime())) {
		return iso;
	}

	return new Intl.DateTimeFormat(undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
	}).format(date);
};

const NoAddon = ({ upgradeUrl }: { upgradeUrl: string }) => (
	<div className="cr-card cr-section" style={{ maxWidth: 640 }}>
		<span className="cr-tag">{__('Pro', 'cart-rebound')}</span>
		<h2 className="cr-section__title">
			{__('No add-on is installed', 'cart-rebound')}
		</h2>
		<p className="cr-section__desc">
			{__(
				'A license belongs to an add-on. Install Cart Rebound Pro first, then activate your key here.',
				'cart-rebound'
			)}
		</p>
		{upgradeUrl !== '' && (
			<p>
				<a
					className="cr-btn is-primary"
					href={upgradeUrl}
					target="_blank"
					rel="noreferrer noopener"
				>
					{__('Get Cart Rebound Pro', 'cart-rebound')}
				</a>
			</p>
		)}
	</div>
);

export const License = () => {
	const queryClient = useQueryClient();
	const { installed, addons, upgrade_url: upgradeUrl } = useAddons();
	const [key, setKey] = useState('');

	const license = useQuery<LicenseState>({
		queryKey: ['pro', 'license'],
		queryFn: fetchLicense,
		enabled: installed,
	});

	// Every mutation re-reads the add-on registry, because the answer to "which
	// features are live" changed the moment this one did.
	const refreshAddons = () => {
		void queryClient.invalidateQueries({ queryKey: ['addons'] });
		void queryClient.invalidateQueries({ queryKey: ['pro'] });
	};

	const activate = useMutation({
		mutationFn: activateLicense,
		onSuccess: (data) => {
			queryClient.setQueryData(['pro', 'license'], data);
			setKey('');
			refreshAddons();
		},
	});

	const recheck = useMutation({
		mutationFn: refreshLicense,
		onSuccess: (data) => {
			queryClient.setQueryData(['pro', 'license'], data);
			refreshAddons();
		},
	});

	const deactivate = useMutation({
		mutationFn: deactivateLicense,
		onSuccess: (data) => {
			queryClient.setQueryData(['pro', 'license'], data);
			refreshAddons();
		},
	});

	if (!installed) {
		return <NoAddon upgradeUrl={upgradeUrl} />;
	}

	const state = license.data ?? sampleLicense();
	const addon = addons[0];
	const busy =
		activate.isPending || recheck.isPending || deactivate.isPending;

	const onSubmit = (event: FormEvent<HTMLFormElement>) => {
		event.preventDefault();

		if (key.trim() !== '') {
			activate.mutate(key.trim());
		}
	};

	return (
		<div className="cr-card" style={{ maxWidth: 640 }}>
			<div className="cr-section">
				<h2 className="cr-section__title">
					{addon
						? sprintf(
								/* translators: %s: add-on name. */
								__('%s license', 'cart-rebound'),
								addon.name
							)
						: __('License', 'cart-rebound')}
				</h2>
				<p className="cr-section__desc">
					{__(
						'An active license unlocks the Pro screens on this site and keeps the add-on receiving updates.',
						'cart-rebound'
					)}
				</p>

				<div className="cr-field--row">
					<div>
						<span className="cr-field__label">
							{__('Status', 'cart-rebound')}
						</span>
						{state.masked_key !== '' && (
							<p className="cr-field__hint">
								<code className="cr-code">
									{state.masked_key}
								</code>
							</p>
						)}
					</div>
					<span className={STATUS_CLASS[state.status]}>
						{STATUS_LABEL[state.status]}
					</span>
				</div>

				{state.message !== '' && (
					<div
						className={
							state.active
								? 'cr-notice is-success'
								: 'cr-notice is-error'
						}
					>
						{state.message}
					</div>
				)}

				{state.expires_at !== '' && (
					<p className="cr-field__hint">
						{sprintf(
							/* translators: %s: formatted expiry date. */
							__('Renews or expires on %s.', 'cart-rebound'),
							formatDate(state.expires_at)
						)}
					</p>
				)}

				{state.checked_at !== '' && (
					<p className="cr-field__hint">
						{sprintf(
							/* translators: %s: formatted date and time. */
							__('Last checked %s.', 'cart-rebound'),
							formatDate(state.checked_at)
						)}
					</p>
				)}
			</div>

			{state.active ? (
				<div className="cr-section">
					<h2 className="cr-section__title">
						{__('Manage this site', 'cart-rebound')}
					</h2>
					<p className="cr-section__desc">
						{__(
							'Deactivating releases this site from the license so you can activate it somewhere else. The Pro screens lock again immediately.',
							'cart-rebound'
						)}
					</p>
					<div className="cr-row-actions">
						<button
							type="button"
							className="cr-btn is-ghost"
							disabled={busy}
							onClick={() => {
								recheck.mutate();
							}}
						>
							{recheck.isPending
								? __('Checking…', 'cart-rebound')
								: __('Re-check now', 'cart-rebound')}
						</button>
						<button
							type="button"
							className="cr-btn is-danger"
							disabled={busy}
							onClick={() => {
								deactivate.mutate();
							}}
						>
							{deactivate.isPending
								? __('Deactivating…', 'cart-rebound')
								: __('Deactivate on this site', 'cart-rebound')}
						</button>
					</div>
				</div>
			) : (
				<form onSubmit={onSubmit} className="cr-section">
					<div className="cr-field">
						<label htmlFor="cr-license" className="cr-field__label">
							{__('License key', 'cart-rebound')}
						</label>
						<input
							id="cr-license"
							className="cr-input"
							type="text"
							autoComplete="off"
							spellCheck={false}
							value={key}
							placeholder={__('Paste your key', 'cart-rebound')}
							onChange={(event) => {
								setKey(event.target.value);
							}}
						/>
						<p className="cr-field__hint">
							{__(
								'Find it in your purchase receipt or your account area.',
								'cart-rebound'
							)}
						</p>
					</div>

					<div className="cr-savebar">
						<button
							type="submit"
							className="cr-btn is-primary"
							disabled={busy || key.trim() === ''}
						>
							{activate.isPending
								? __('Activating…', 'cart-rebound')
								: __('Activate license', 'cart-rebound')}
						</button>
					</div>
				</form>
			)}
		</div>
	);
};
