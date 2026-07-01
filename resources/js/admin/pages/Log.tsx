/**
 * Log page — a filterable, paginated view of the plugin's activity log.
 */
import { useState } from 'react';
import { useClearLog, useLogs } from '../hooks/useApi';
import type { LogEntry } from '../types/api';

const LEVELS = ['', 'info', 'success', 'warning', 'error'];
const KNOWN_LEVELS = new Set(LEVELS.filter((value) => value !== ''));
const PER_PAGE = 30;
const COLUMN_COUNT = 5;

const LevelBadge = ({ level }: { level: string }) => (
	<span
		className={
			KNOWN_LEVELS.has(level) ? `cr-logbadge is-${level}` : 'cr-logbadge'
		}
	>
		{level}
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
			<code className="cr-code">{entry.event}</code>
		</td>
		<td>{entry.message}</td>
		<td>{entry.cart_id > 0 ? `#${entry.cart_id}` : <Dash />}</td>
	</tr>
);

const SkeletonRows = () => (
	<>
		{Array.from({ length: 8 }, (_, row) => (
			<tr key={row}>
				{Array.from({ length: COLUMN_COUNT }, (__, col) => (
					<td key={col}>
						<div
							className="cr-skeleton"
							style={{
								height: 14,
								width: col === 3 ? '70%' : '50%',
							}}
						/>
					</td>
				))}
			</tr>
		))}
	</>
);

export const Log = () => {
	const [level, setLevel] = useState('');
	const [page, setPage] = useState(1);
	const { data, isLoading, isError } = useLogs({
		level,
		page,
		per_page: PER_PAGE,
	});
	const clear = useClearLog();

	const items = data?.items ?? [];
	const totalPages = data
		? Math.max(1, Math.ceil(data.total / data.per_page))
		: 1;
	const isEmpty = !isLoading && !isError && items.length === 0;

	const onClear = () => {
		// eslint-disable-next-line no-alert
		if (window.confirm('Clear the entire activity log?')) {
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
				<span className="cr-toolbar__label">Level</span>
				<select
					className="cr-select is-compact"
					aria-label="Filter log by level"
					value={level}
					onChange={(event) => {
						setLevel(event.target.value);
						setPage(1);
					}}
				>
					{LEVELS.map((value) => (
						<option
							key={value === '' ? 'all' : value}
							value={value}
						>
							{value === '' ? 'All levels' : value}
						</option>
					))}
				</select>
				<span className="cr-toolbar__spacer" />
				{data && (
					<span className="cr-toolbar__label">
						{data.total} entr{data.total === 1 ? 'y' : 'ies'}
					</span>
				)}
				<button
					type="button"
					className="cr-btn is-danger is-sm"
					onClick={onClear}
					disabled={clear.isPending || (!!data && data.total === 0)}
				>
					{clear.isPending ? 'Clearing…' : 'Clear log'}
				</button>
			</div>

			<div className="cr-card">
				{isError && (
					<div
						className="cr-notice is-error"
						role="alert"
						style={{ margin: 16 }}
					>
						Could not load the log.
					</div>
				)}

				{isEmpty && (
					<div className="cr-empty">
						<p className="cr-empty__title">Nothing logged yet</p>
						<p>
							Abandonments, recoveries, and sent emails will show
							up here as they happen.
						</p>
					</div>
				)}

				{!isError && !isEmpty && (
					<>
						<div className="cr-table-wrap">
							<table className="cr-table">
								<thead>
									<tr>
										<th>Time (UTC)</th>
										<th>Level</th>
										<th>Event</th>
										<th>Message</th>
										<th>Cart</th>
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

						<div className="cr-pagination">
							<button
								type="button"
								className="cr-btn is-ghost is-sm"
								disabled={page <= 1}
								onClick={() => {
									setPage((current) =>
										Math.max(1, current - 1)
									);
								}}
							>
								Previous
							</button>
							<span>
								Page {page} of {totalPages}
							</span>
							<span className="cr-pagination__spacer" />
							<button
								type="button"
								className="cr-btn is-ghost is-sm"
								disabled={page >= totalPages}
								onClick={() => {
									setPage((current) =>
										Math.min(totalPages, current + 1)
									);
								}}
							>
								Next
							</button>
						</div>
					</>
				)}
			</div>
		</div>
	);
};
