/**
 * Log page — a filterable, paginated view of the plugin's activity log.
 */
import { useState } from 'react';
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import { Combobox } from '../components/Combobox';
import { TablePagination } from '../components/TablePagination';
import { useClearLog, useLogs } from '../hooks/useApi';
import type { LogEntry } from '../types/api';

const LEVELS = ['', 'info', 'success', 'warning', 'error'];
const KNOWN_LEVELS = new Set(LEVELS.filter((value) => value !== ''));
const EVENTS = [
	{ value: '', label: __('All events', 'cart-rebound') },
	{ value: 'email_sent', label: __('Emails sent', 'cart-rebound') },
	{ value: 'abandoned', label: __('Abandoned', 'cart-rebound') },
	{ value: 'recovered', label: __('Recovered', 'cart-rebound') },
];
const DEFAULT_PER_PAGE = 30;
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

const eventLabel = (event: string): string =>
	EVENTS.find((option) => option.value === event)?.label ?? event;

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

const LogRow = ({ entry }: { entry: LogEntry }) => (
	<tr>
		<td className="cr-muted cr-nowrap">{entry.created_at}</td>
		<td>
			<LevelBadge level={entry.level} />
		</td>
		<td className="cr-nowrap">
			<code className="cr-code">{eventLabel(entry.event)}</code>
		</td>
		<td>{entry.message}</td>
		<td>{entry.cart_id > 0 ? `#${entry.cart_id}` : <Dash />}</td>
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
	const { data, isLoading, isError } = useLogs({
		level,
		event,
		cart_id: cartId > 0 ? cartId : 0,
		page,
		per_page: perPage,
	});
	const clear = useClearLog();

	const items = data?.items ?? [];
	const totalPages = data
		? Math.max(1, Math.ceil(data.total / data.per_page))
		: 1;
	const isEmpty = !isLoading && !isError && items.length === 0;

	const onClear = () => {
		// eslint-disable-next-line no-alert
		const confirmed = window.confirm(
			__('Clear the entire activity log?', 'cart-rebound')
		);

		if (confirmed) {
			clear.mutate(undefined, {
				onSuccess: () => {
					setPage(1);
				},
			});
		}
	};

	return (
		<div>
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
				<input
					className="cr-input is-compact"
					style={{ width: 96 }}
					type="number"
					min={1}
					value={cart}
					placeholder={__('Cart ID', 'cart-rebound')}
					aria-label={__('Filter log by cart ID', 'cart-rebound')}
					onChange={(changeEvent) => {
						setCart(changeEvent.target.value);
						setPage(1);
					}}
				/>
				<span className="cr-toolbar__spacer" />
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
					disabled={clear.isPending || (!!data && data.total === 0)}
				>
					{clear.isPending
						? __('Clearing…', 'cart-rebound')
						: __('Clear log', 'cart-rebound')}
				</button>
			</div>

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

				{isEmpty && (
					<div className="cr-empty">
						<p className="cr-empty__title">
							{__('Nothing logged yet', 'cart-rebound')}
						</p>
						<p>
							{__(
								'Abandonments, recoveries, and sent emails will show up here as they happen.',
								'cart-rebound'
							)}
						</p>
					</div>
				)}

				{!isError && !isEmpty && (
					<>
						<div className="cr-table-wrap">
							<table className="cr-table">
								<thead>
									<tr>
										<th>
											{__('Time (UTC)', 'cart-rebound')}
										</th>
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
											/>
										))
									)}
								</tbody>
							</table>
						</div>

						<TablePagination
							page={page}
							totalPages={totalPages}
							perPage={perPage}
							onPageChange={setPage}
							onPerPageChange={(nextPerPage) => {
								setPerPage(nextPerPage);
								setPage(1);
							}}
						/>
					</>
				)}
			</div>
		</div>
	);
};
