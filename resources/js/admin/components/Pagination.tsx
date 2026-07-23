/**
 * Shared table pagination bar.
 *
 * Paging is server-side: the caller requests one page at a time and the REST
 * endpoint returns just that slice plus the unfiltered total, so this bar only
 * reports the current window and moves the cursor. Changing the page size
 * resets to page 1 — the old cursor would point past the end of the new range.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Combobox } from './Combobox';

/** Page sizes offered to the user. The REST layer caps `per_page` at 100. */
export const PER_PAGE_OPTIONS = [10, 20, 50, 100];

export const Pagination = ({
	page,
	perPage,
	total,
	onPage,
	onPerPage,
}: {
	page: number;
	perPage: number;
	total: number;
	onPage: (next: number) => void;
	onPerPage: (next: number) => void;
}) => {
	const totalPages = Math.max(1, Math.ceil(total / perPage));
	const from = total === 0 ? 0 : (page - 1) * perPage + 1;
	const to = Math.min(total, page * perPage);

	return (
		<div className="cr-pagination">
			<span className="cr-toolbar__label">
				{__('Rows per page', 'cart-rebound')}
			</span>
			<Combobox
				compact
				ariaLabel={__('Rows per page', 'cart-rebound')}
				value={String(perPage)}
				options={PER_PAGE_OPTIONS.map((option) => ({
					value: String(option),
					label: String(option),
				}))}
				onChange={(next) => {
					onPerPage(Number.parseInt(next, 10));
				}}
			/>
			<span className="cr-nowrap">
				{sprintf(
					/* translators: 1: first row on this page, 2: last row on this page, 3: total rows. */
					__('Showing %1$d–%2$d of %3$d', 'cart-rebound'),
					from,
					to,
					total
				)}
			</span>

			<span className="cr-pagination__spacer" />

			<button
				type="button"
				className="cr-btn is-ghost is-sm"
				disabled={page <= 1}
				onClick={() => {
					onPage(Math.max(1, page - 1));
				}}
			>
				{__('Previous', 'cart-rebound')}
			</button>
			<span className="cr-nowrap">
				{sprintf(
					/* translators: 1: current page, 2: total pages. */
					__('Page %1$d of %2$d', 'cart-rebound'),
					page,
					totalPages
				)}
			</span>
			<button
				type="button"
				className="cr-btn is-ghost is-sm"
				disabled={page >= totalPages}
				onClick={() => {
					onPage(Math.min(totalPages, page + 1));
				}}
			>
				{__('Next', 'cart-rebound')}
			</button>
		</div>
	);
};
