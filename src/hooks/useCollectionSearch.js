/**
 * WordPress dependencies
 */
import { useEffect } from '@wordpress/element';

/**
 * Hook to sync collections list with search: either restore initial data when
 * search is empty, or run API search when debounced search term changes.
 *
 * @param {Object} options
 * @param {Object} options.collections              Collections metadata (from API)
 * @param {string} options.debouncedSearchInput     Debounced search query
 * @param {Array}  options.initialCollectionsArr   Snapshot to restore when search is cleared
 * @param {Function} options.loadIcons              ( collectionName, page, search ) => Promise<{ icons, hasMore }>
 * @param {Function} options.setCollectionsArr      State setter for collections list
 * @param {Function} options.setLoading             State setter for loading flag
 */
export function useCollectionSearch( {
	collections,
	debouncedSearchInput,
	initialCollectionsArr,
	loadIcons,
	setCollectionsArr,
	setLoading,
} ) {
	useEffect( () => {
		if ( ! collections ) {
			return;
		}

		if ( ! debouncedSearchInput.trim() ) {
			if ( initialCollectionsArr.length ) {
				setCollectionsArr( initialCollectionsArr );
			}
			return;
		}

		setLoading( true );

		const runSearch = async () => {
			const collectionsData = Object.keys( collections ).map(
				( key ) => collections[ key ]
			);

			const updatedCollections = await Promise.all(
				collectionsData.map( async ( collectionItem ) => {
					const { icons, hasMore, totalPages } = await loadIcons(
						collectionItem.name,
						1,
						debouncedSearchInput
					);

					return {
						...collectionItem,
						icons,
						currentPage: 1,
						hasMore,
						totalPages: totalPages ?? 1,
						loading: false,
					};
				} )
			);

			setCollectionsArr( updatedCollections );
			setLoading( false );
		};

		runSearch();
	}, [
		debouncedSearchInput,
		collections,
		initialCollectionsArr,
		loadIcons,
		setCollectionsArr,
		setLoading,
	] );
}
