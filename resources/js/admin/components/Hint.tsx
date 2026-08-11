/**
 * The ⓘ beside a metric label, and the sentence behind it.
 *
 * It lived inside Dashboard.tsx, which is why the Analytics tiles — two of which
 * carry word for word the Dashboard's labels while meaning something else
 * (that screen's figures are lifetime, this screen's are range-scoped) — had no
 * way to say which was which. A merchant comparing the two screens could only
 * conclude that one of them was wrong.
 *
 * The button carries the explanation as its accessible name, so assistive tech
 * reads it without needing the hover the bubble depends on.
 */

const InfoIcon = () => (
	<svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<circle cx="8" cy="8" r="6" stroke="currentColor" strokeWidth="1.3" />
		<path
			d="M8 7.3v3.3"
			stroke="currentColor"
			strokeWidth="1.4"
			strokeLinecap="round"
		/>
		<circle cx="8" cy="5.2" r="0.85" fill="currentColor" />
	</svg>
);

/**
 * @param root0      Component props.
 * @param root0.text What the metric measures, in one sentence.
 */
export const Hint = ({ text }: { text: string }) => (
	<span className="cr-hint">
		<button type="button" className="cr-hint__btn" aria-label={text}>
			<InfoIcon />
		</button>
		<span className="cr-hint__bubble" aria-hidden="true">
			{text}
		</span>
	</span>
);
