import { useState } from 'react';
import type { CSSProperties } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { AdminLink } from './AdminLink';
import { formatNumber } from '../lib/format';
import { statusLabel, statusMeaning } from '../lib/status';

const ORDER = [
	'active',
	'abandoned',
	'pending-payment',
	'recovered',
	'completed',
	'lost',
];

interface Part {
	status: string;
	count: number;
	share: number;
	start: number;
}

const toParts = (counts: Record<string, number>): Part[] => {
	const total = ORDER.reduce(
		(sum, status) => sum + Math.max(0, counts[status] ?? 0),
		0
	);
	let start = 0;

	return ORDER.map((status) => {
		const count = Math.max(0, counts[status] ?? 0);
		const share = total > 0 ? (count / total) * 100 : 0;
		const part = { status, count, share, start };

		start += share;

		return part;
	});
};

const cartCount = (count: number): string =>
	sprintf(
		/* translators: %s: number of carts. */
		_n('%s cart', '%s carts', count, 'cart-rebound'),
		formatNumber(count, 0)
	);

const tipStyle = (part: Part): CSSProperties => {
	const center = part.start + part.share / 2;

	if (center < 15) {
		return { left: `${part.start}%` };
	}

	if (center > 85) {
		return { left: `${part.start + part.share}%`, translate: '-100% 0' };
	}

	return { left: `${center}%`, translate: '-50% 0' };
};

export const StatusBand = ({ counts }: { counts: Record<string, number> }) => {
	const [focus, setFocus] = useState<string | null>(null);
	const parts = toParts(counts);
	const visible = parts.filter((part) => part.count > 0);
	const focused = visible.find((part) => part.status === focus);

	if (visible.length === 0) {
		return (
			<div className="cr-band is-empty">
				<div className="cr-band__bar" aria-hidden="true" />
				<p className="cr-band__note">
					{__(
						'No carts on record yet. They appear here as soon as shoppers add items.',
						'cart-rebound'
					)}
				</p>
			</div>
		);
	}

	const clear = () => {
		setFocus(null);
	};

	return (
		<div className={focused ? 'cr-band has-focus' : 'cr-band'}>
			<div className="cr-band__bar" aria-hidden="true">
				{visible.map((part) => (
					<span
						key={part.status}
						className={`cr-band__part is-${part.status}${
							part.status === focus ? ' is-focus' : ''
						}`}
						style={{ flexGrow: part.count }}
						onPointerEnter={(event) => {
							if (event.pointerType === 'mouse') {
								setFocus(part.status);
							}
						}}
						onPointerLeave={clear}
					/>
				))}
			</div>

			<ul className="cr-band__legend">
				{parts.map((part) => (
					<li key={part.status}>
						<AdminLink
							to="/carts"
							state={{ status: part.status }}
							className={`cr-band__key${
								part.status === focus ? ' is-focus' : ''
							}${part.count === 0 ? ' is-zero' : ''}`}
							aria-label={sprintf(
								/* translators: 1: cart status, 2: number of carts with that status, e.g. "3 carts". */
								__(
									'%1$s: %2$s. Show them in Carts.',
									'cart-rebound'
								),
								statusLabel(part.status),
								cartCount(part.count)
							)}
							onMouseEnter={() => {
								setFocus(part.status);
							}}
							onMouseLeave={clear}
							onFocus={() => {
								setFocus(part.status);
							}}
							onBlur={clear}
						>
							<span
								className={`cr-band__swatch is-${part.status}`}
								aria-hidden="true"
							/>
							<span className="cr-band__name">
								{statusLabel(part.status)}
							</span>
							<span className="cr-band__count">
								{formatNumber(part.count, 0)}
							</span>
						</AdminLink>
					</li>
				))}
			</ul>

			{focused && (
				<div
					className="cr-band__tip"
					style={tipStyle(focused)}
					aria-hidden="true"
				>
					<p className="cr-band__tiphead">
						<strong>{cartCount(focused.count)}</strong>
						<span>
							{sprintf(
								/* translators: %s: share of all carts on record, e.g. 12.5. */
								__('%1$s%% of all', 'cart-rebound'),
								formatNumber(focused.share)
							)}
						</span>
					</p>
					<p className="cr-band__tipmeaning">
						{statusMeaning(focused.status)}
					</p>
				</div>
			)}
		</div>
	);
};
