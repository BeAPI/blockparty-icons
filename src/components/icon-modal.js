import {
	Fragment,
	useEffect,
	useState,
	useCallback,
	useRef,
} from '@wordpress/element';
import {
	Animate,
	Button,
	Flex,
	FlexBlock,
	FlexItem,
	Modal,
	Notice,
	Panel,
	PanelBody,
	RangeControl,
	SearchControl,
	Spinner,
	TabPanel,
	TextHighlight,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Icon, settings } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as preferencesStore } from '@wordpress/preferences';

import { capitalize, getIcons } from '../utils';
import { useCollectionSearch } from '../hooks/useCollectionSearch';
import IconPagination from './icon-pagination';
import IconSelector from './icon-selector';

const PREFERENCES_NAME = 'blockparty-icons';
const DEFAULT_ICON_PREVIEW_SIZE = 24;
const DEFAULT_ICONS_PER_PAGE = 50;

function IconModal( { collections, onClose, handleIconSelectButtonClick } ) {
	const [ isOpen, setOpen ] = useState( true );
	const [ collectionsArr, setCollectionsArr ] = useState( [] );
	const [ searchInput, setSearchInput ] = useState( '' );
	const [ debouncedSearchInput, setDebouncedSearchInput ] = useState( '' );
	const [ loading, setLoading ] = useState( false );

	// Get icon preview size from preferences store (persisted)
	const iconPreviewSize = useSelect(
		( select ) =>
			select( preferencesStore ).get(
				PREFERENCES_NAME,
				'iconPreviewSize'
			) ?? DEFAULT_ICON_PREVIEW_SIZE,
		[]
	);

	// Get icons per page from preferences store (persisted)
	const iconsPerPage = useSelect(
		( select ) =>
			select( preferencesStore ).get(
				PREFERENCES_NAME,
				'iconsPerPage'
			) ?? DEFAULT_ICONS_PER_PAGE,
		[]
	);

	// Get sidebar open state from preferences store (persisted)
	const isSidebarOpen = useSelect(
		( select ) =>
			select( preferencesStore ).get( PREFERENCES_NAME, 'sidebarOpen' ) ??
			false,
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
		( collection, icon ) => {
			handleIconSelectButtonClick( collection, icon );
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
				per_page: iconsPerPage,
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
				( ! headers.totalPages && icons.length === iconsPerPage );

			return {
				icons,
				hasMore,
				totalPages,
				currentPage,
			};
		},
		[ iconsPerPage ]
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
					const { icons, hasMore, totalPages } = await loadIcons(
						collectionItem.name,
						1,
						''
					);

					collectionItem.icons = icons;
					collectionItem.currentPage = 1;
					collectionItem.hasMore = hasMore;
					collectionItem.totalPages = totalPages ?? 1;
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
	 * Go to a specific page for a collection (replaces current icons with that page)
	 *
	 * @param {string} collectionName Collection name
	 * @param {number} page           Page number (1-indexed)
	 */
	const goToPage = useCallback(
		( collectionName, page ) => {
			setCollectionsArr( ( prevCollections ) =>
				prevCollections.map( ( collectionItem ) =>
					collectionItem.name !== collectionName
						? collectionItem
						: { ...collectionItem, loading: true }
				)
			);
			loadIcons( collectionName, page, debouncedSearchInput ).then(
				( {
					icons,
					hasMore,
					totalPages: nextTotalPages,
					currentPage: nextPage,
				} ) => {
					setCollectionsArr( ( prev ) =>
						prev.map( ( item ) =>
							item.name !== collectionName
								? item
								: {
										...item,
										icons,
										currentPage: nextPage,
										hasMore,
										totalPages:
											nextTotalPages ??
											item.totalPages ??
											1,
										loading: false,
								  }
						)
					);
				}
			);
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
	 * Sync collections list with search: restore initial data or run API search
	 */
	useCollectionSearch( {
		collections,
		debouncedSearchInput,
		initialCollectionsArr,
		loadIcons,
		setCollectionsArr,
		setLoading,
	} );

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
						<div className="blockparty-icons-modal__content">
							<Flex align="stretch" gap="0">
								<FlexBlock>
									<div className="blockparty-icons-modal__toolbar">
										<Flex
											align="center"
											justify="space-between"
										>
											<FlexBlock>
												<SearchControl
													label={ __(
														'Search an icon',
														'blockparty-icons'
													) }
													value={ searchInput }
													onChange={ setSearchInput }
													placeholder={ __(
														'wordpress, heart, star...',
														'blockparty-icons'
													) }
													__nextHasNoMarginBottom
												/>
											</FlexBlock>
											<FlexItem>
												<Button
													label={ __(
														'Settings',
														'blockparty-icons'
													) }
													className="has-icon"
													size="compact"
													isPressed={ isSidebarOpen }
													onClick={ () =>
														setPreference(
															PREFERENCES_NAME,
															'sidebarOpen',
															! isSidebarOpen
														)
													}
												>
													<Icon icon={ settings } />
												</Button>
											</FlexItem>
										</Flex>
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
													collectionsArr.filter(
														( c ) => {
															// During search, show all collections (even if empty)
															// Otherwise, only show collections with icons or loading
															if (
																debouncedSearchInput.trim()
															) {
																return true;
															}
															return (
																c.icons
																	?.length >
																	0 ||
																c.loading
															);
														}
													);

												// Check if any collection has icons
												const hasAnyIcons =
													filteredCollections.some(
														( c ) =>
															c.icons?.length > 0
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
																			{ !! c
																				.icons
																				?.length && (
																				<Flex
																					direction="column"
																					gap="0"
																				>
																					<FlexBlock>
																						{ c.loading && (
																							<div className="blockparty-icons-modal__spinner">
																								<Spinner />
																							</div>
																						) }
																						{ !! c
																							.icons
																							?.length &&
																							! c.loading && (
																								<div className="blockparty-icons-modal__body">
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
																														collection={
																															c
																														}
																														handleIconSelectButtonClick={
																															handleSelectIcon
																														}
																													>
																														<TextHighlight
																															text={ capitalize(
																																i.label ||
																																	i.name
																															) }
																															highlight={
																																debouncedSearchInput
																															}
																														/>
																													</IconSelector>
																												</li>
																											)
																										) }
																									</ul>
																								</div>
																							) }
																					</FlexBlock>
																					{ ( c.totalPages ??
																						1 ) >
																						1 && (
																						<FlexItem>
																							<IconPagination
																								currentPage={
																									c.currentPage ??
																									1
																								}
																								totalPages={
																									c.totalPages ??
																									1
																								}
																								onPageChange={ (
																									page
																								) =>
																									goToPage(
																										c.name,
																										page
																									)
																								}
																								isLoading={
																									c.loading
																								}
																							/>
																						</FlexItem>
																					) }
																				</Flex>
																			) }
																			{ ! c.loading &&
																				! c
																					.icons
																					?.length && (
																					<div className="blockparty-icons-modal__no-results">
																						<Notice
																							status="info"
																							isDismissible={
																								false
																							}
																						>
																							{ __(
																								'No Icon found in this collection.',
																								'blockparty-icons'
																							) }
																						</Notice>
																					</div>
																				) }
																		</Fragment>
																	)
															)
														}
													</TabPanel>
												);
											} )() }
									</div>
								</FlexBlock>
								<FlexItem
									className={ `blockparty-icons-modal__sidebar-wrapper${
										! isSidebarOpen
											? ' blockparty-icons-modal__sidebar-wrapper--closed'
											: ''
									}` }
								>
									<Animate
										type="slide-in"
										options={ { origin: 'left' } }
									>
										{ ( { className } ) => (
											<div
												className={ `${ className } blockparty-icons-modal__sidebar` }
												aria-hidden={ ! isSidebarOpen }
											>
												<Panel
													header={ __(
														'Modal settings',
														'blockparty-icons'
													) }
												>
													<PanelBody
														title={ __(
															'Icon preview size',
															'blockparty-icons'
														) }
													>
														<RangeControl
															label={ __(
																'Icon preview size',
																'blockparty-icons'
															) }
															help={ __(
																'Adjust preview size of the icons',
																'blockparty-icons'
															) }
															value={
																displaySize
															}
															min={ 8 }
															initialPosition={
																displaySize
															}
															max={ 256 }
															onChange={ (
																newSize
															) => {
																setLocalPreviewSize(
																	newSize
																);
																if (
																	persistSizeTimeoutRef.current
																) {
																	clearTimeout(
																		persistSizeTimeoutRef.current
																	);
																}
																persistSizeTimeoutRef.current =
																	setTimeout(
																		() => {
																			setPreference(
																				PREFERENCES_NAME,
																				'iconPreviewSize',
																				newSize
																			);
																			persistSizeTimeoutRef.current =
																				null;
																		},
																		300
																	);
															} }
														/>
													</PanelBody>
													<PanelBody
														title={ __(
															'Pager',
															'blockparty-icons'
														) }
													>
														<RangeControl
															label={ __(
																'Icons per page',
																'blockparty-icons'
															) }
															help={ __(
																'Number of icons displayed per page',
																'blockparty-icons'
															) }
															value={
																iconsPerPage
															}
															min={ 12 }
															max={ 100 }
															step={ 2 }
															onChange={ (
																value
															) => {
																setPreference(
																	PREFERENCES_NAME,
																	'iconsPerPage',
																	value
																);
															} }
														/>
													</PanelBody>
												</Panel>
											</div>
										) }
									</Animate>
								</FlexItem>
							</Flex>
						</div>
					) }
				</Modal>
			) }
		</>
	);
}

export default IconModal;
