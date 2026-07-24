/**
 * Shared pagination controls for data tables.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Combobox } from './Combobox';

const PER_PAGE_OPTIONS = [10, 20, 30, 50, 100].map((option) => ({
	value: String(option),
	label: String(option),
}));

interface TablePaginationProps {
	page: number;
	totalPages: number;
	perPage: number;
	onPageChange: (page: number) => void;
	onPerPageChange: (perPage: number) => void;
}

export const TablePagination = ({
	page,
	totalPages,
	perPage,
	onPageChange,
	onPerPageChange,
}: TablePaginationProps) => (
	<div className="cr-pagination">
		<div className="cr-pagination__limit">
			<span>{__('Rows per page', 'cart-rebound')}</span>
			<Combobox
				compact
				ariaLabel={__('Rows per page', 'cart-rebound')}
				value={String(perPage)}
				options={PER_PAGE_OPTIONS}
				onChange={(nextPerPage) => {
					onPerPageChange(Number(nextPerPage));
				}}
			/>
		</div>
		<span className="cr-pagination__spacer" />
		<button
			type="button"
			className="cr-btn is-ghost is-sm"
			disabled={page <= 1}
			onClick={() => {
				onPageChange(Math.max(1, page - 1));
			}}
		>
			{__('Previous', 'cart-rebound')}
		</button>
		<span>
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
				onPageChange(Math.min(totalPages, page + 1));
			}}
		>
			{__('Next', 'cart-rebound')}
		</button>
	</div>
);
