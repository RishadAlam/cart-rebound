/**
 * The shapes a screen shows while it waits.
 *
 * These lived inside Dashboard.tsx, which is why Analytics — the other screen
 * built from the same metric strip and the same tables — had no loading state at
 * all and rendered sample figures instead. A skeleton is not decoration: it is
 * the difference between "not yet" and "this is your number".
 */

/**
 * The metric strip, six tiles wide.
 * @param root0
 * @param root0.tiles
 */
export const MetricSkeleton = ({ tiles = 6 }: { tiles?: number }) => (
	<div className="cr-metrics">
		{Array.from({ length: tiles }, (_unused, index) => (
			<div key={index} className="cr-metric">
				<div className="cr-metric__top">
					<div
						className="cr-skeleton"
						style={{ height: 11, width: '64%' }}
					/>
				</div>
				<div
					className="cr-skeleton"
					style={{ height: 24, width: '52%', marginTop: 14 }}
				/>
			</div>
		))}
	</div>
);

/**
 * Table rows, sized so the first column reads as a name and the rest as figures.
 * @param root0         Component props.
 * @param root0.columns How many cells each row holds.
 * @param root0.rows    How many rows to draw.
 */
export const TableSkeleton = ({
	columns,
	rows = 4,
}: {
	columns: number;
	rows?: number;
}) => (
	<>
		{Array.from({ length: rows }, (_unusedRow, row) => (
			<tr key={row}>
				{Array.from({ length: columns }, (_unusedCol, col) => (
					<td key={col}>
						<div
							className="cr-skeleton"
							style={{
								height: 12,
								width: col === 0 ? '75%' : '45%',
							}}
						/>
					</td>
				))}
			</tr>
		))}
	</>
);
