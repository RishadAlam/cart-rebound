/**
 * The label / control / hint unit every form on every screen is built from.
 *
 * It existed twice — once in Settings, once in Rules — which is how two screens
 * that look the same drift apart: a hint gains a colour in one copy, a label a
 * weight in the other, and nobody notices until they are side by side. One
 * definition means a change to how a field reads happens once.
 */
import { type ReactNode } from 'react';

interface Props {
	/** Must match the control's own id, or the label clicks nothing. */
	id: string;
	label: string;
	/** Says what the setting does, not what the widget is. */
	hint?: string;
	/** Shown in place of the hint; announced to assistive tech. */
	error?: string;
	children: ReactNode;
}

export const Field = ({ id, label, hint, error, children }: Props) => (
	<div className="cr-field">
		<label htmlFor={id} className="cr-field__label">
			{label}
		</label>

		{children}

		{error !== undefined && error !== '' && (
			<p
				className="cr-field__hint is-error"
				id={`${id}-error`}
				role="alert"
			>
				{error}
			</p>
		)}

		{error === undefined && hint !== undefined && (
			<p className="cr-field__hint" id={`${id}-hint`}>
				{hint}
			</p>
		)}
	</div>
);

/**
 * The switch itself, without a layout around it.
 *
 * Separate from ToggleField because not every switch sits in a label/hint row —
 * a sequence step puts one beside a heading. Both reach for the same markup, so
 * both get it from here rather than each writing the three nested spans out.
 * @param root0          Component props.
 * @param root0.id       Control id.
 * @param root0.checked  Current value.
 * @param root0.onChange Receives the new value.
 * @param root0.label    Accessible name, when no visible label points at it.
 */
export const Switch = ({
	id,
	checked,
	onChange,
	label,
}: {
	id: string;
	checked: boolean;
	onChange: (checked: boolean) => void;
	label?: string;
}) => (
	<span className="cr-switch">
		<input
			id={id}
			type="checkbox"
			checked={checked}
			{...(label === undefined ? {} : { 'aria-label': label })}
			onChange={(event) => {
				onChange(event.target.checked);
			}}
		/>
		<span className="cr-switch__track">
			<span className="cr-switch__thumb" />
		</span>
	</span>
);

/**
 * A field whose control is a switch, laid out label-left / switch-right.
 *
 * A toggle answers a yes/no question, so the question belongs beside the answer
 * rather than above it — and putting every toggle in one component stops each
 * screen inventing its own row.
 * @param root0          Component props.
 * @param root0.id       Control id, matched by the label.
 * @param root0.label    The question being answered.
 * @param root0.hint     What turning it on actually does.
 * @param root0.checked  Current value.
 * @param root0.onChange Receives the new value.
 */
export const ToggleField = ({
	id,
	label,
	hint,
	checked,
	onChange,
}: {
	id: string;
	label: string;
	hint?: string;
	checked: boolean;
	onChange: (checked: boolean) => void;
}) => (
	<div className="cr-field--row">
		<div>
			<label htmlFor={id} className="cr-field__label">
				{label}
			</label>
			{hint !== undefined && <p className="cr-field__hint">{hint}</p>}
		</div>

		<Switch id={id} checked={checked} onChange={onChange} />
	</div>
);
