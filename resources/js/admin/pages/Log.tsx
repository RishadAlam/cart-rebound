/**
 * Log page — a filterable, paginated view of the plugin's activity log.
 */
import { useState } from 'react';
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import { Combobox } from '../components/Combobox';
import { DEFAULT_PER_PAGE, Pagination } from '../components/Pagination';
import { useClearLog, useLogs } from '../hooks/useApi';
import { errorMessage } from '../lib/errors';
import { formatExact, formatWhen } from '../lib/format';
import type { LogEntry } from '../types/api';

const LEVELS = ['', 'info', 'success', 'warning', 'error'];
const KNOWN_LEVELS = new Set(LEVELS.filter((value) => value !== ''));

/*
 * The events the plugin itself records — see CartRebound\Events\LogSubscriber.
 * A failed send is the one a merchant comes here to find, so it has to be
 * filterable; leaving it out meant the only way to see it was to page through
 * everything.
 */
const EVENTS = [
	{ value: '', label: __('All events', 'cart-rebound') },
	{ value: 'email_sent', label: __('Emails sent', 'cart-rebound') },
	{ value: 'email_failed', label: __('Emails failed', 'cart-rebound') },
	{ value: 'abandoned', label: __('Abandoned', 'cart-rebound') },
	{ value: 'recovered', label: __('Recovered', 'cart-rebound') },
];
const COLUMN_COUNT = 5;

const levelLabel = (level: string): string => {
	switch (level) {
		case 'info':
			return _x('Info', 'log level', 'cart-rebound');
		case 'success':
			return _x('Success', 'log level', 'cart-rebound');
		case 'warning':
			return _x('Warning', 'log level', 'cart-rebound');
		case 'error':
			return _x('Error', 'log level', 'cart-rebound');
		default:
			return level;
	}
};

/**
 * Name an event the way a person would say it.
 *
 * An add-on can log anything it likes, so the list above cannot be exhaustive.
 * An unrecognised key used to be printed raw, which put `email_failed` and
 * `cleanup` on the same screen as "Emails sent" and made half the column look
 * like a database dump. An unknown key is not translatable — nobody declared it
 * — so the best that can honestly be done is make it readable.
 * @param event The stored event key.
 */
const eventLabel = (event: string): string => {
	const known = EVENTS.find((option) => option.value === event);

	if (known) {
		return known.label;
	}

	const words = event.replace(/[_-]+/g, ' ').trim();

	return words === ''
		? event
		: words.charAt(0).toUpperCase() + words.slice(1);
};

const LevelBadge = ({ level }: { level: string }) => (
	<span
		className={
			KNOWN_LEVELS.has(level) ? `cr-logbadge is-${level}` : 'cr-logbadge'
		}
	>
		{levelLabel(level)}
	</span>
);

const Dash = () => <span className="cr-muted">—</span>;

const LogRow = ({
	entry,
	onFilterCart,
}: {
	entry: LogEntry;
	/** Filters the log to one cart, so a row leads somewhere. */
	onFilterCart: (cartId: number) => void;
}) => (
	<tr>
		{/* `title` is hover-only, so the precise moment — the whole reason to
		    open a log — was unreachable from a keyboard or a phone. The exact
		    value is rendered for assistive tech alongside the relative one. */}
		<td className="cr-muted cr-nowrap">
			<span title={formatExact(entry.created_at)}>
				{formatWhen(entry.created_at)}
			</span>
			<span className="screen-reader-text">
				{formatExact(entry.created_at)}
			</span>
		</td>
		<td>
			<LevelBadge level={entry.level} />
		</td>
		{/* The name reads as prose; the stored key stays on hover, because that is
		    what a support thread or a filter needs. */}
		<td className="cr-nowrap cr-cell-event" title={entry.event}>
			{eventLabel(entry.event)}
		</td>
		<td>{entry.message}</td>
		{/* The identifier was inert text, so the obvious next question — "what
		    else happened to this cart?" — meant retyping the number into the filter
		    box by hand. */}
		<td className="cr-num">
			{entry.cart_id > 0 ? (
				<button
					type="button"
					className="cr-linkbtn"
					onClick={() => {
						onFilterCart(entry.cart_id);
					}}
					title={__('Show only this cart’s entries', 'cart-rebound')}
				>
					{`#${entry.cart_id}`}
				</button>
			) : (
				<Dash />
			)}
		</td>
	</tr>
);

const SkeletonRows = () => (
	<>
		{Array.from({ length: 8 }, (_unusedRowValue, row) => (
			<tr key={row}>
				{Array.from(
					{ length: COLUMN_COUNT },
					(_unusedColumnValue, col) => (
						<td key={col}>
							<div
								className="cr-skeleton"
								style={{
									height: 14,
									width: col === 3 ? '70%' : '50%',
								}}
							/>
						</td>
					)
				)}
			</tr>
		))}
	</>
);

export const Log = () => {
	const [level, setLevel] = useState('');
	const [event, setEvent] = useState('');
	const [cart, setCart] = useState('');
	const [page, setPage] = useState(1);
	const [perPage, setPerPage] = useState(DEFAULT_PER_PAGE);
	const cartId = Number.parseInt(cart, 10);
	const { data, isLoading, isFetching, isError } = useLogs({
		level,
		event,
		cart_id: cartId > 0 ? cartId : 0,
		page,
		per_page: perPage,
	});
	const clear = useClearLog();

	const items = data?.items ?? [];
	const isEmpty = !isLoading && !isError && items.length === 0;

	const filtered = level !== '' || event !== '' || cartId > 0;

	/*
	 * The count beside this button is the *filtered* total, so "3 entries" sat
	 * next to a button that deletes every row in the table. The question says
	 * what actually happens.
	 */
	const onClear = () => {
		// eslint-disable-next-line no-alert
		const confirmed = window.confirm(
			filtered
				? __(
						'Clear the entire activity log? This deletes every entry, not only the ones matching your filters.',
						'cart-rebound'
					)
				: __(
						'Clear the entire activity log? This cannot be undone.',
						'cart-rebound'
					)
		);

		if (confirmed) {
			clear.mutate(undefined, {
				onSuccess: () => {
					setPage(1);
				},
			});
		}
	};

	/*
	 * Clearing the log used to report neither outcome. A 403 from an expired
	 * nonce left every row on screen with no message, which reads as "the button
	 * does nothing"; a success left them there too until the refetch landed.
	 */
	const clearNotice = () => {
		if (clear.isError) {
			return (
				<div
					className="cr-notice is-error cr-notice--inset"
					role="alert"
				>
					{errorMessage(
						clear.error,
						__(
							'The log could not be cleared. Please try again.',
							'cart-rebound'
						)
					)}
				</div>
			);
		}

		if (clear.isSuccess) {
			return (
				<div
					className="cr-notice is-success cr-notice--inset"
					role="status"
				>
					{__('Activity log cleared.', 'cart-rebound')}
				</div>
			);
		}

		return null;
	};

	return (
		<div>
			{/* The routed content began at a toolbar: the only heading on the page
			    was the product name in the shell, so this screen had no name at
			    all in a list of landmarks. */}
			<h2 className="screen-reader-text">
				{__('Activity log', 'cart-rebound')}
			</h2>

			<div className="cr-toolbar">
				<span className="cr-toolbar__label">
					{__('Level', 'cart-rebound')}
				</span>
				<Combobox
					compact
					ariaLabel={__('Filter log by level', 'cart-rebound')}
					value={level}
					options={LEVELS.map((option) => ({
						value: option,
						label:
							option === ''
								? __('All levels', 'cart-rebound')
								: levelLabel(option),
					}))}
					onChange={(next) => {
						setLevel(next);
						setPage(1);
					}}
				/>
				<span className="cr-toolbar__label">
					{__('Event', 'cart-rebound')}
				</span>
				<Combobox
					compact
					ariaLabel={__('Filter log by event', 'cart-rebound')}
					value={event}
					options={EVENTS}
					onChange={(next) => {
						setEvent(next);
						setPage(1);
					}}
				/>
				<span className="cr-toolbar__label">
					{__('Cart', 'cart-rebound')}
				</span>
				{/* Anything that does not parse to a positive integer was silently
				    treated as "no filter", so a typo showed the whole log and
				    looked like the filter had matched everything. */}
				<input
					className="cr-input is-compact"
					style={{ width: 96 }}
					type="number"
					min={1}
					{...(cart.trim() !== '' && !(cartId > 0)
						? { 'aria-invalid': true }
						: {})}
					value={cart}
					placeholder={__('Cart ID', 'cart-rebound')}
					aria-label={__('Filter log by cart ID', 'cart-rebound')}
					onChange={(changeEvent) => {
						setCart(changeEvent.target.value);
						setPage(1);
					}}
				/>
				{cart.trim() !== '' && !(cartId > 0) && (
					<span className="cr-toolbar__label is-error" role="alert">
						{__('Cart filter needs a number', 'cart-rebound')}
					</span>
				)}

				<span className="cr-toolbar__spacer" />
				{isFetching && !isLoading && (
					<span className="cr-toolbar__label">
						{__('Updating…', 'cart-rebound')}
					</span>
				)}
				{data && (
					<span className="cr-toolbar__label">
						{sprintf(
							/* translators: %d: number of log entries. */
							_n(
								'%d entry',
								'%d entries',
								data.total,
								'cart-rebound'
							),
							data.total
						)}
					</span>
				)}
				<button
					type="button"
					className="cr-btn is-danger is-sm"
					onClick={onClear}
					disabled={
						clear.isPending ||
						(!!data && data.total === 0 && !filtered)
					}
				>
					{clear.isPending
						? __('Clearing…', 'cart-rebound')
						: __('Clear log', 'cart-rebound')}
				</button>
			</div>

			{clearNotice()}

			<div className="cr-card">
				{isError && (
					<div
						className="cr-notice is-error"
						role="alert"
						style={{ margin: 16 }}
					>
						{__('Could not load the log.', 'cart-rebound')}
					</div>
				)}

				{/* One empty state used to serve two situations: a log with nothing
				    in it, and a filter that matched nothing. The second is the
				    common one, and telling that merchant "nothing logged yet" sends
				    them looking for a bug in the plugin. */}
				{isEmpty && (
					<div className="cr-empty">
						<p className="cr-empty__title">
							{filtered
								? __(
										'No entries match these filters',
										'cart-rebound'
									)
								: __('Nothing logged yet', 'cart-rebound')}
						</p>
						<p>
							{filtered
								? __(
										'Try a different level, event, or cart — or clear the filters to see everything.',
										'cart-rebound'
									)
								: __(
										'Abandonments, recoveries, and sent emails will show up here as they happen.',
										'cart-rebound'
									)}
						</p>
					</div>
				)}

				{!isError && !isEmpty && (
					<>
						<div className="cr-table-wrap">
							<table className="cr-table cr-table--log">
								<thead>
									<tr>
										{/* Not "(UTC)": rows are stored in UTC but
										    rendered in the reader's own zone, and
										    most of them read "34 minutes ago",
										    where a zone means nothing at all. */}
										<th>{__('Time', 'cart-rebound')}</th>
										<th>{__('Level', 'cart-rebound')}</th>
										<th>{__('Event', 'cart-rebound')}</th>
										<th>{__('Message', 'cart-rebound')}</th>
										<th>{__('Cart', 'cart-rebound')}</th>
									</tr>
								</thead>
								<tbody>
									{isLoading ? (
										<SkeletonRows />
									) : (
										items.map((entry) => (
											<LogRow
												key={entry.id}
												entry={entry}
												onFilterCart={(id) => {
													setCart(String(id));
													setPage(1);
												}}
											/>
										))
									)}
								</tbody>
							</table>
						</div>

						<Pagination
							page={page}
							perPage={perPage}
							total={data?.total ?? 0}
							onPage={setPage}
							onPerPage={(next) => {
								setPerPage(next);
								setPage(1);
							}}
						/>
					</>
				)}
			</div>
		</div>
	);
};
