/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const PAGINATION_OFFSET = 2;

/**
 * Returns an array of page numbers and 'ellipsis' for pagination (current ± offset, plus first/last).
 *
 * @param {number} currentPage Current page (1-indexed)
 * @param {number} totalPages  Total number of pages
 * @param {number} offset      Number of pages before/after current
 * @return {(number|'ellipsis')[]} Items to render (number = page, 'ellipsis' = …)
 */
function getPaginationPages(
	currentPage,
	totalPages,
	offset = PAGINATION_OFFSET
) {
	const current = Math.max( 1, Math.min( currentPage, totalPages ) );
	const total = Math.max( 1, totalPages );
	const items = [];
	const left = Math.max( 1, current - offset );
	const right = Math.min( total, current + offset );

	if ( left > 1 ) {
		items.push( 1 );
		if ( left > 2 ) {
			items.push( 'ellipsis' );
		}
	}
	for ( let p = left; p <= right; p++ ) {
		items.push( p );
	}
	if ( right < total ) {
		if ( right < total - 1 ) {
			items.push( 'ellipsis' );
		}
		items.push( total );
	}
	return items;
}

/**
 * Pagination component: First | Previous | page numbers (current ± offset) | Next | Last.
 *
 * @param {Object}   props
 * @param {number}   props.currentPage  Current page (1-indexed)
 * @param {number}   props.totalPages   Total number of pages
 * @param {Function} props.onPageChange Called with (page: number) when user selects a page
 * @param {boolean}  [props.isLoading]  Disable buttons while loading
 * @param {string}   [props.ariaLabel]  Optional aria-label for the nav (default: "Icon pagination")
 */
export default function IconPagination( props ) {
	if ( Math.max( 1, props.totalPages ) <= 1 ) {
		return null;
	}

	const total = Math.max( 1, props.totalPages );
	const current = Math.max(
		1,
		Math.min( props.currentPage, props.totalPages )
	);

	return (
		<nav
			className="blockparty-icons__pagination"
			aria-label={
				props.ariaLabel ?? __( 'Icon pagination', 'blockparty-icons' )
			}
		>
			<Button
				variant="compact"
				onClick={ () => props.onPageChange( current - 1 ) }
				disabled={ ( props.isLoading ?? false ) || current <= 1 }
				label={ __( 'Previous page', 'blockparty-icons' ) }
			>
				{ __( 'Previous', 'blockparty-icons' ) }
			</Button>
			<span
				className="blockparty-icons-pagination__pages"
				aria-live="polite"
			>
				{ getPaginationPages( current, total, PAGINATION_OFFSET ).map(
					( page, idx ) =>
						page === 'ellipsis' ? (
							<span
								key={ `ellipsis-${ idx }` }
								className="blockparty-icons-pagination__ellipsis"
								aria-hidden="true"
							>
								…
							</span>
						) : (
							<Button
								key={ page }
								variant="compact"
								onClick={ () => props.onPageChange( page ) }
								disabled={
									( props.isLoading ?? false ) ||
									page === current
								}
								label={ sprintf(
									/* translators: %d: page number */
									__( 'Page %d', 'blockparty-icons' ),
									page
								) }
							>
								{ page }
							</Button>
						)
				) }
			</span>
			<Button
				variant="compact"
				onClick={ () => props.onPageChange( current + 1 ) }
				disabled={ ( props.isLoading ?? false ) || current >= total }
				label={ __( 'Next page', 'blockparty-icons' ) }
			>
				{ __( 'Next', 'blockparty-icons' ) }
			</Button>
		</nav>
	);
}
