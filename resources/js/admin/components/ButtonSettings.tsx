import type { CSSProperties } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Combobox } from './Combobox';
import { NumberInput } from './NumberInput';
import type { RecoveryButtonConfig } from '../types/api';

const THEME = window.CartRebound?.theme ?? {
	accent: '#2563eb',
	ink: '#ffffff',
};

const SIZE_METRICS: Record<
	RecoveryButtonConfig['size'],
	{ padding: string; fontSize: number }
> = {
	small: { padding: '8px 16px', fontSize: 14 },
	medium: { padding: '12px 24px', fontSize: 16 },
	large: { padding: '16px 32px', fontSize: 18 },
};

const SIZE_OPTIONS = [
	{ value: 'small', label: __('Small', 'cart-rebound') },
	{ value: 'medium', label: __('Medium', 'cart-rebound') },
	{ value: 'large', label: __('Large', 'cart-rebound') },
];

const ALIGN_OPTIONS = [
	{ value: 'left', label: __('Left', 'cart-rebound') },
	{ value: 'center', label: __('Centre', 'cart-rebound') },
	{ value: 'right', label: __('Right', 'cart-rebound') },
];

const luminance = (hex: string): number => {
	const full =
		hex.length === 4
			? `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}`
			: hex;
	const [r = 0, g = 0, b = 0] = [1, 3, 5].map((start) => {
		const value = parseInt(full.slice(start, start + 2), 16) / 255;

		return value <= 0.03928
			? value / 12.92
			: ((value + 0.055) / 1.055) ** 2.4;
	});

	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

// Mirrors Branding::readable_on() so the preview matches the sent email.
const readableOn = (background: string): string => {
	const bg = luminance(background);
	const onWhite = 1.05 / (bg + 0.05);
	const onDark = (bg + 0.05) / (luminance('#111111') + 0.05);

	return onWhite >= onDark ? '#ffffff' : '#111111';
};

const ColorField = ({
	id,
	label,
	hint,
	value,
	fallback,
	fallbackLabel,
	onChange,
}: {
	id: string;
	label: string;
	hint: string;
	value: string;
	fallback: string;
	fallbackLabel: string;
	onChange: (next: string) => void;
}) => (
	<div className="cr-field">
		<label htmlFor={id} className="cr-field__label">
			{label}
		</label>
		<p className="cr-field__hint">
			{value === ''
				? sprintf(
						/* translators: 1: where the colour comes from, e.g. "theme colour", 2: hex colour. */
						__('Using the %1$s (%2$s).', 'cart-rebound'),
						fallbackLabel,
						fallback
					)
				: hint}
		</p>
		<div className="cr-colorfield">
			<input
				id={id}
				type="color"
				className="cr-colorfield__swatch"
				value={value === '' ? fallback : value}
				onChange={(event) => {
					onChange(event.target.value);
				}}
			/>
			<code className="cr-colorfield__value">
				{(value === '' ? fallback : value).toUpperCase()}
			</code>
			<button
				type="button"
				className="cr-btn is-ghost is-sm"
				disabled={value === ''}
				onClick={() => {
					onChange('');
				}}
			>
				{sprintf(
					/* translators: %s: where the colour comes from, e.g. "theme colour". */
					__('Use %s', 'cart-rebound'),
					fallbackLabel
				)}
			</button>
		</div>
	</div>
);

export const ButtonSettings = ({
	value,
	onChange,
	toggle,
}: {
	value: RecoveryButtonConfig;
	onChange: <K extends keyof RecoveryButtonConfig>(
		key: K,
		next: RecoveryButtonConfig[K]
	) => void;
	toggle: (key: 'enabled' | 'full_width', id: string) => JSX.Element;
}) => {
	const background =
		value.background !== '' ? value.background : THEME.accent;
	let ink = THEME.ink;

	if (value.text_color !== '') {
		ink = value.text_color;
	} else if (value.background !== '') {
		ink = readableOn(value.background);
	}

	const metrics = SIZE_METRICS[value.size];
	const buttonStyle: CSSProperties = {
		display: value.full_width ? 'block' : 'inline-block',
		padding: metrics.padding,
		borderRadius: value.radius,
		background,
		color: ink,
		fontSize: metrics.fontSize,
		fontWeight: 600,
		lineHeight: 1.25,
		textAlign: 'center',
	};

	return (
		<>
			<div className="cr-field--row">
				<div>
					<label htmlFor="cr-btn-enabled" className="cr-field__label">
						{__('Show the button', 'cart-rebound')}
					</label>
					<p className="cr-field__hint">
						{__(
							'Turn off to link with your own {recovery_url} in the message instead.',
							'cart-rebound'
						)}
					</p>
				</div>
				{toggle('enabled', 'cr-btn-enabled')}
			</div>

			{value.enabled && (
				<>
					<div
						className="cr-btnpreview"
						style={{ textAlign: value.align }}
						aria-hidden="true"
					>
						<span style={buttonStyle}>
							{value.text !== ''
								? value.text
								: __('Complete your order', 'cart-rebound')}
						</span>
					</div>

					<h3 className="cr-subhead">
						{__('Label', 'cart-rebound')}
					</h3>
					<div className="cr-field__grid">
						<div className="cr-field">
							<label
								htmlFor="cr-btn-text"
								className="cr-field__label"
							>
								{__('Button text', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'Leave blank for “Complete your order”.',
									'cart-rebound'
								)}
							</p>
							<input
								id="cr-btn-text"
								className="cr-input"
								type="text"
								maxLength={80}
								value={value.text}
								placeholder={__(
									'Complete your order',
									'cart-rebound'
								)}
								onChange={(event) => {
									onChange('text', event.target.value);
								}}
							/>
						</div>
						<div className="cr-field">
							<span className="cr-field__label">
								{__('Size', 'cart-rebound')}
							</span>
							<p className="cr-field__hint">
								{__(
									'Padding and type size together.',
									'cart-rebound'
								)}
							</p>
							<Combobox
								ariaLabel={__('Button size', 'cart-rebound')}
								options={SIZE_OPTIONS}
								value={value.size}
								onChange={(next) => {
									onChange(
										'size',
										next as RecoveryButtonConfig['size']
									);
								}}
							/>
						</div>
					</div>

					<h3 className="cr-subhead">
						{__('Colours', 'cart-rebound')}
					</h3>
					<div className="cr-field__grid">
						<ColorField
							id="cr-btn-bg"
							label={__('Background', 'cart-rebound')}
							hint={__(
								'A custom colour for this template only.',
								'cart-rebound'
							)}
							value={value.background}
							fallback={THEME.accent}
							fallbackLabel={__('theme colour', 'cart-rebound')}
							onChange={(next) => {
								onChange('background', next);
							}}
						/>
						<ColorField
							id="cr-btn-ink"
							label={__('Text', 'cart-rebound')}
							hint={__(
								'Check it stays readable on the background.',
								'cart-rebound'
							)}
							value={value.text_color}
							fallback={ink}
							fallbackLabel={__(
								'automatic contrast',
								'cart-rebound'
							)}
							onChange={(next) => {
								onChange('text_color', next);
							}}
						/>
					</div>

					<h3 className="cr-subhead">
						{__('Shape & position', 'cart-rebound')}
					</h3>
					<div className="cr-field__grid">
						<div className="cr-field">
							<label
								htmlFor="cr-btn-radius"
								className="cr-field__label"
							>
								{__('Corner radius (px)', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'0 for square corners, 24 or more for a pill.',
									'cart-rebound'
								)}
							</p>
							<NumberInput
								id="cr-btn-radius"
								className="cr-input"
								min={0}
								max={40}
								value={value.radius}
								onChange={(next) => {
									onChange('radius', Math.min(40, next));
								}}
							/>
						</div>
						<div className="cr-field">
							<span className="cr-field__label">
								{__('Alignment', 'cart-rebound')}
							</span>
							<p className="cr-field__hint">
								{__(
									'Ignored when the button spans the full width.',
									'cart-rebound'
								)}
							</p>
							<Combobox
								ariaLabel={__(
									'Button alignment',
									'cart-rebound'
								)}
								disabled={value.full_width}
								options={ALIGN_OPTIONS}
								value={value.align}
								onChange={(next) => {
									onChange(
										'align',
										next as RecoveryButtonConfig['align']
									);
								}}
							/>
						</div>
					</div>

					<div className="cr-field--row">
						<div>
							<label
								htmlFor="cr-btn-full"
								className="cr-field__label"
							>
								{__('Full width', 'cart-rebound')}
							</label>
							<p className="cr-field__hint">
								{__(
									'Stretch the button across the email, which reads well on phones.',
									'cart-rebound'
								)}
							</p>
						</div>
						{toggle('full_width', 'cr-btn-full')}
					</div>
				</>
			)}
		</>
	);
};
