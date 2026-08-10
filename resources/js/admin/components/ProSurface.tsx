/**
 * The lock that sits over a Pro screen.
 *
 * A paywall usually replaces the thing it is selling with a description of it,
 * which asks a merchant to buy something they have never seen. This does the
 * opposite: the real screen renders, populated with a sample store, and the
 * lock sits on top of it. What you evaluate is the interface you would get.
 *
 * The preview is genuinely inert — removed from the accessibility tree and the
 * tab order, and unable to receive a pointer — so it can be looked at and
 * nothing more. Only the panel is reachable, which also means keyboard users
 * land on the one control that does anything.
 *
 * Blur is kept light. Enough to say "not yours yet", not so much that the
 * screen becomes an abstract shape; the top of the surface stays entirely
 * legible and the scrim only deepens where the panel needs contrast.
 */
import { useEffect, useRef, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { useAddons } from '../hooks/useAddons';
import type { ProFeature } from '../types/api';

interface Props {
	/** The feature that unlocks this surface. */
	feature: ProFeature;
	/** What the screen does, in the merchant's terms. */
	title: string;
	/** One sentence on why it is worth having. */
	summary: string;
	/** Three or four specifics. Concrete beats adjectival here. */
	points: string[];
	children: ReactNode;
}

/**
 * `inert` removes a subtree from focus, pointer events, and assistive tech in
 * one attribute, but React 18 does not know it as a prop. Setting it on the
 * element directly gets the real behaviour where the browser supports it; the
 * CSS below covers the rest either way.
 * @param active Whether the subtree should be unreachable.
 */
const useInert = (active: boolean) => {
	const ref = useRef<HTMLDivElement>(null);

	useEffect(() => {
		const node = ref.current;

		if (node) {
			node.inert = active;
		}
	}, [active]);

	return ref;
};

export const ProSurface = ({
	feature,
	title,
	summary,
	points,
	children,
}: Props) => {
	const {
		installed,
		features,
		settings_url: settingsUrl,
		upgrade_url: upgradeUrl,
	} = useAddons();

	const unlocked = features.includes(feature);
	const previewRef = useInert(!unlocked);

	if (unlocked) {
		return <>{children}</>;
	}

	// Installed but delivering nothing is a different problem from not installed
	// at all, and only one of them is solved by buying something. Which it is
	// decides where the button goes. Why an installed add-on is delivering
	// nothing is its own business to explain, on its own screen.
	const dormant = installed && features.length === 0;

	return (
		<div className="cr-lock">
			<div
				className="cr-lock__preview"
				ref={previewRef}
				aria-hidden="true"
			>
				{children}
			</div>

			<div className="cr-lock__scrim" aria-hidden="true" />

			<div className="cr-lock__panel" role="region" aria-label={title}>
				<span className="cr-tag">{__('Pro', 'cart-rebound')}</span>

				<h2 className="cr-lock__title">
					{dormant
						? __('Finish setting up your add-on', 'cart-rebound')
						: title}
				</h2>

				<p className="cr-lock__summary">
					{dormant
						? __(
								'An add-on is installed but is not running any of its features yet. Open its own screen to finish setting it up.',
								'cart-rebound'
							)
						: summary}
				</p>

				<ul className="cr-lock__points">
					{points.map((point) => (
						<li key={point}>{point}</li>
					))}
				</ul>

				<div className="cr-lock__actions">
					{dormant
						? settingsUrl !== '' && (
								<a
									className="cr-btn is-primary"
									href={settingsUrl}
								>
									{__('Open add-on settings', 'cart-rebound')}
								</a>
							)
						: upgradeUrl !== '' && (
								<a
									className="cr-btn is-primary"
									href={upgradeUrl}
									target="_blank"
									rel="noreferrer noopener"
								>
									{__('Get Cart Rebound Pro', 'cart-rebound')}
								</a>
							)}
				</div>

				<p className="cr-lock__note">
					{__(
						'Everything you use today keeps working exactly as it does now — Pro only adds to it. The figures above are a sample store.',
						'cart-rebound'
					)}
				</p>
			</div>
		</div>
	);
};
