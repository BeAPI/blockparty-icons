import {
	Fragment,
	useEffect,
	useState,
	useCallback,
	useRef,
} from '@wordpress/element';
import {
	Modal,
	RangeControl,
	SearchControl,
	Spinner,
	Button,
	TabPanel,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as preferencesStore } from '@wordpress/preferences';

import { capitalize, getIcons } from '../utils';
import IconSelector from './icon-selector';

const ICONS_PER_PAGE = 50;
const PREFERENCES_NAME = 'blockparty-icons';
const DEFAULT_ICON_PREVIEW_SIZE = 24;

function IconModal( { collections, onClose, handleIconSelectButtonClick } ) {
	const [ isOpen, setOpen ] = useState( true );
	const [ collectionsArr, setCollectionsArr ] = useState( [] );
	const [ searchInput, setSearchInput ] = useState( '' );
	const [ debouncedSearchInput, setDebouncedSearchInput ] = useState( '' );
	const [ loading, setLoading ] = useState( false );
	const [ loadingMore, setLoadingMore ] = useState( false );

	// Get icon preview size from preferences store (persisted)
	const iconPreviewSize = useSelect(
		( select ) =>
			select( preferencesStore ).get(
				PREFERENCES_NAME,
				'iconPreviewSize'
			) ?? DEFAULT_ICON_PREVIEW_SIZE,
		[]
	);

	// Local state for immediate slider feedback; avoids store updates on every tick
	const [ localPreviewSize, setLocalPreviewSize ] = useState( null );
	const persistSizeTimeoutRef = useRef( null );

	const displaySize = localPreviewSize ?? iconPreviewSize;

	// Sync local from store when modal opens
	useEffect( () => {
		if ( isOpen ) {
			setLocalPreviewSize( iconPreviewSize );
		}
	}, [ isOpen, iconPreviewSize ] );

	// Clear debounce on unmount
	useEffect(
		() => () => {
			if ( persistSizeTimeoutRef.current ) {
				clearTimeout( persistSizeTimeoutRef.current );
			}
		},
		[]
	);

	// Get dispatcher to update preferences
	const { set: setPreference } = useDispatch( preferencesStore );

	// Stable callback so IconSelector (memo) doesn't re-render when only size changes
	const handleSelectIcon = useCallback(
		( icon ) => {
			handleIconSelectButtonClick( icon );
			setOpen( false );
			onClose();
		},
		[ handleIconSelectButtonClick, onClose ]
	);

	/**
	 * Load icons for a collection with pagination support
	 *
	 * @param {string} collectionName Collection name
	 * @param {number} page           Page number (1-indexed)
	 * @param {string} search         Search query
	 * @return {Promise<{icons: Array, total: number, totalPages: number}>} Promise resolving to icon data
	 */
	const loadIcons = useCallback(
		async ( collectionName, page = 1, search = '' ) => {
			const args = {
				context: 'edit',
				per_page: ICONS_PER_PAGE,
				page,
			};

			if ( search ) {
				args.search = search;
			}

			const result = await getIcons( collectionName, args );
			const items = result.data || result; // Handle both old and new format
			const icons = Object.keys( items ).map( ( item ) => items[ item ] );

			// Get pagination info from headers if available
			const headers = result.headers || {};
			const currentPage = headers.totalPages ? page : 1;
			const totalPages = headers.totalPages || 1;
			const hasMore =
				currentPage < totalPages ||
				( ! headers.totalPages && icons.length === ICONS_PER_PAGE );

			return {
				icons,
				hasMore,
				totalPages,
				currentPage,
			};
		},
		[]
	);

	/**
	 * Store initial icons for reset when search is cleared
	 */
	const [ initialCollectionsArr, setInitialCollectionsArr ] = useState( [] );

	/**
	 * Load initial icons for all collections
	 */
	useEffect( () => {
		if ( collections ) {
			setLoading( true );
			async function getCollectionsArr( collectionsData ) {
				const collectionsArray = Object.keys( collectionsData ).map(
					( collection ) => collectionsData[ collection ]
				);

				for ( const collectionItem of collectionsArray ) {
					const { icons, hasMore } = await loadIcons(
						collectionItem.name,
						1,
						''
					);

					collectionItem.icons = icons;
					collectionItem.currentPage = 1;
					collectionItem.hasMore = hasMore;
					collectionItem.loading = false;
				}

				setCollectionsArr( collectionsArray );
				setInitialCollectionsArr( collectionsArray );
				setLoading( false );
			}

			getCollectionsArr( collections );
		}
	}, [ collections, loadIcons ] );

	/**
	 * Load more icons for a specific collection
	 */
	const loadMoreIcons = useCallback(
		async ( collectionName ) => {
			setLoadingMore( true );

			setCollectionsArr( ( prevCollections ) => {
				return prevCollections.map( ( collectionItem ) => {
					if ( collectionItem.name !== collectionName ) {
						return collectionItem;
					}

					const nextPage = ( collectionItem.currentPage || 1 ) + 1;
					collectionItem.loading = true;

					loadIcons(
						collectionName,
						nextPage,
						debouncedSearchInput
					).then( ( { icons, hasMore } ) => {
						collectionItem.icons = [
							...collectionItem.icons,
							...icons,
						];
						collectionItem.currentPage = nextPage;
						collectionItem.hasMore = hasMore;
						collectionItem.loading = false;
						setCollectionsArr( ( prev ) => [ ...prev ] );
						setLoadingMore( false );
					} );

					return collectionItem;
				} );
			} );
		},
		[ loadIcons, debouncedSearchInput ]
	);

	/**
	 * Debounce search input - delay before triggering actual search
	 */
	useEffect( () => {
		const timeoutId = setTimeout( () => {
			setDebouncedSearchInput( searchInput );
		}, 500 ); // 500ms delay

		return () => clearTimeout( timeoutId );
	}, [ searchInput ] );

	/**
	 * Handle search - use API search instead of client-side filtering
	 */
	useEffect( () => {
		// Only wait for collections metadata, not for initial icons to load
		if ( ! collections ) {
			return;
		}

		// If search is empty, restore initial icons (or wait for them to load)
		if ( ! debouncedSearchInput.trim() ) {
			if ( initialCollectionsArr.length ) {
				setCollectionsArr( initialCollectionsArr );
			}
			return;
		}

		// Allow search even if initial icons haven't loaded yet
		setLoading( true );

		async function searchIcons() {
			const collectionsData = Object.keys( collections ).map(
				( collectionKey ) => collections[ collectionKey ]
			);

			const updatedCollections = await Promise.all(
				collectionsData.map( async ( collectionItem ) => {
					const { icons, hasMore } = await loadIcons(
						collectionItem.name,
						1,
						debouncedSearchInput
					);

					return {
						...collectionItem,
						icons,
						currentPage: 1,
						hasMore,
						loading: false,
					};
				} )
			);

			setCollectionsArr( updatedCollections );
			setLoading( false );
		}

		searchIcons();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ debouncedSearchInput ] );

	return (
		<>
			{ isOpen && (
				<Modal
					title={ __( 'Select an icon', 'blockparty-icons' ) }
					onRequestClose={ () => {
						setOpen( false );
						onClose();
					} }
					className="blockparty-icons-modal"
				>
					{ ! collectionsArr.length && <Spinner /> }
					{ !! collectionsArr.length && (
						<>
							<div className="blockparty-icons-modal__toolbar">
								<SearchControl
									label={ __(
										'Search an icon',
										'blockparty-icons'
									) }
									value={ searchInput }
									onChange={ setSearchInput }
									hideLabelFromVision={ false }
									placeholder={ __(
										'wordpress, heart, star...',
										'blockparty-icons'
									) }
								/>
								<RangeControl
									label={ __(
										'Icon preview size',
										'blockparty-icons'
									) }
									help={ __(
										'Adjust preview size of the icons',
										'blockparty-icons'
									) }
									value={ displaySize }
									min={ 8 }
									initialPosition={ displaySize }
									max={ 256 }
									onChange={ ( newSize ) => {
										setLocalPreviewSize( newSize );
										if ( persistSizeTimeoutRef.current ) {
											clearTimeout(
												persistSizeTimeoutRef.current
											);
										}
										persistSizeTimeoutRef.current =
											setTimeout( () => {
												setPreference(
													PREFERENCES_NAME,
													'iconPreviewSize',
													newSize
												);
												persistSizeTimeoutRef.current =
													null;
											}, 300 );
									} }
								/>
							</div>
							<div
								className="blockparty-icons-modal__collections-list"
								style={ {
									'--blockparty-icon-preview-size': `${ displaySize }px`,
								} }
							>
								{ loading && <Spinner /> }
								{ ! loading &&
									( () => {
										const filteredCollections =
											collectionsArr.filter( ( c ) => {
												// During search, show all collections (even if empty)
												// Otherwise, only show collections with icons or loading
												if (
													debouncedSearchInput.trim()
												) {
													return true;
												}
												return (
													c.icons?.length > 0 ||
													c.loading
												);
											} );

										// Check if any collection has icons
										const hasAnyIcons =
											filteredCollections.some(
												( c ) => c.icons?.length > 0
											);

										// Show global "no results" message if searching and no icons found
										if (
											debouncedSearchInput.trim() &&
											! hasAnyIcons
										) {
											return (
												<div className="blockparty-icons-modal__no-results">
													<p>
														{ __(
															'No icons found matching your search.',
															'blockparty-icons'
														) }
													</p>
												</div>
											);
										}

										return (
											<TabPanel
												tabs={ filteredCollections.map(
													( c ) => ( {
														name: c.name,
														title: capitalize(
															c.name
														),
													} )
												) }
											>
												{ ( tab ) =>
													filteredCollections.map(
														( c ) =>
															tab.name ===
																c.name && (
																<Fragment
																	key={
																		c.name
																	}
																>
																	{ c.loading && (
																		<Spinner />
																	) }
																	{ !! c.icons
																		?.length && (
																		<>
																			<ul className="blockparty-icons-modal__list-icon">
																				{ c.icons.map(
																					(
																						i
																					) => (
																						<li
																							key={
																								i.name
																							}
																						>
																							<IconSelector
																								icon={
																									i
																								}
																								handleIconSelectButtonClick={
																									handleSelectIcon
																								}
																							/>
																						</li>
																					)
																				) }
																			</ul>
																		</>
																	) }
																</Fragment>
															)
													)
												}
											</TabPanel>
										);

										// return filteredCollections.map(
										// 	( c ) => (
										// 		<ul key={ c.name }>
										// 			<li>
										// 				<p className="blockparty-icons-modal__collection-name">
										// 					{ capitalize(
										// 						c.name
										// 					) }
										// 				</p>
										// 				{ c.loading && (
										// 					<Spinner />
										// 				) }
										// 				{ !! c.icons
										// 					?.length && (
										// 					<>
										// 						<ul className="blockparty-icons-modal__list-icon">
										// 							{ c.icons.map(
										// 								(
										// 									i
										// 								) => (
										// 									<li
										// 										key={
										// 											i.name
										// 										}
										// 									>
										// 										<IconSelector
										// 											icon={
										// 												i
										// 											}
										// 											size={
										// 												iconPreviewSize
										// 											}
										// 											handleIconSelectButtonClick={ () => {
										// 												handleIconSelectButtonClick(
										// 													i
										// 												);
										// 												setOpen(
										// 													false
										// 												);
										// 												onClose();
										// 											} }
										// 										/>
										// 									</li>
										// 								)
										// 							) }
										// 						</ul>
										// 						{ c.hasMore && (
										// 							<div className="blockparty-icons-modal__load-more">
										// 								<Button
										// 									variant="secondary"
										// 									onClick={ () =>
										// 										loadMoreIcons(
										// 											c.name
										// 										)
										// 									}
										// 									disabled={
										// 										loadingMore ||
										// 										c.loading
										// 									}
										// 								>
										// 									{ loadingMore ||
										// 									c.loading
										// 										? __(
										// 												'Loading…',
										// 												'blockparty-icons'
										// 										  )
										// 										: __(
										// 												'Load More',
										// 												'blockparty-icons'
										// 										  ) }
										// 								</Button>
										// 							</div>
										// 						) }
										// 					</>
										// 				) }
										// 			</li>
										// 		</ul>
										// 	)
										// );
									} )() }
							</div>
						</>
					) }
				</Modal>
			) }
		</>
	);
}

export default IconModal;
