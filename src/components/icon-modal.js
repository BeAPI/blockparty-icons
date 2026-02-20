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
import { __, sprintf } from '@wordpress/i18n';
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
const ICONS_PER_PAGE_DEBOUNCE_MS = 400;

function IconModal( { collections, onClose, handleIconSelectButtonClick } ) {
	const [ isOpen, setOpen ] = useState( true );
	const [ collectionsArr, setCollectionsArr ] = useState( [] );
	const [ searchInput, setSearchInput ] = useState( '' );
	const [ debouncedSearchInput, setDebouncedSearchInput ] = useState( '' );
	const [ loading, setLoading ] = useState( false );

	// Get Preview of icon sizes from preferences store (persisted)
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

	// Local state for "icons per page" with debounced persist (avoids refetch on every slider tick)
	const [ localIconsPerPage, setLocalIconsPerPage ] = useState( null );
	const iconsPerPageDebounceRef = useRef( null );

	const displaySize = localPreviewSize ?? iconPreviewSize;
	const displayIconsPerPage = localIconsPerPage ?? iconsPerPage;

	// Sync local from store when modal opens
	useEffect( () => {
		if ( isOpen ) {
			setLocalPreviewSize( iconPreviewSize );
			setLocalIconsPerPage( iconsPerPage );
		}
	}, [ isOpen, iconPreviewSize, iconsPerPage ] );

	// Clear debounce timeouts on unmount
	useEffect(
		() => () => {
			if ( persistSizeTimeoutRef.current ) {
				clearTimeout( persistSizeTimeoutRef.current );
			}
			if ( iconsPerPageDebounceRef.current ) {
				clearTimeout( iconsPerPageDebounceRef.current );
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
			const args = { context: 'edit' };

			if ( iconsPerPage !== DEFAULT_ICONS_PER_PAGE ) {
				args.per_page = iconsPerPage;
			}

			if ( page > 1 ) {
				args.page = page;
			}

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
					title={ __(
						'Select an icon from your collections',
						'blockparty-icons'
					) }
					onRequestClose={ () => {
						setOpen( false );
						onClose();
					} }
					className="blockparty-icons-modal"
					isFullScreen={ true }
				>
					{ ! collectionsArr.length && <Spinner /> }
					{ !! collectionsArr.length && (
						<div className="blockparty-icons-modal__grid">
							<div className="blockparty-icons-modal__toolbar">
								<Flex align="center" justify="space-between">
									<FlexBlock>
										<SearchControl
											label={ __(
												'Search an icon',
												'blockparty-icons'
											) }
											value={ searchInput }
											onChange={ setSearchInput }
											placeholder={ __(
												'wordpress, heart, star…',
												'blockparty-icons'
											) }
											__nextHasNoMarginBottom
										/>
									</FlexBlock>
									<FlexItem>
										<Button
											label={
												isSidebarOpen
													? __(
															'Hide settings',
															'blockparty-icons'
													  )
													: __(
															'Open settings',
															'blockparty-icons'
													  )
											}
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
								className={ `blockparty-icons-modal__sidebar-wrapper${
									! isSidebarOpen
										? ' blockparty-icons-modal__sidebar-wrapper--closed'
										: ''
								}` }
							>
								<div className="blockparty-icons-modal__sidebar-wrapper-inner">
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
															'Display',
															'blockparty-icons'
														) }
													>
														<RangeControl
															label={ __(
																'Preview of icon sizes',
																'blockparty-icons'
															) }
															help={ __(
																'Adjust preview size of the icons.',
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
																'Number of icons displayed per page.',
																'blockparty-icons'
															) }
															value={
																displayIconsPerPage
															}
															min={ 12 }
															max={ 100 }
															step={ 2 }
															onChange={ (
																value
															) => {
																setLocalIconsPerPage(
																	value
																);
																if (
																	iconsPerPageDebounceRef.current
																) {
																	clearTimeout(
																		iconsPerPageDebounceRef.current
																	);
																}
																iconsPerPageDebounceRef.current =
																	setTimeout(
																		() => {
																			setPreference(
																				PREFERENCES_NAME,
																				'iconsPerPage',
																				value
																			);
																			iconsPerPageDebounceRef.current =
																				null;
																		},
																		ICONS_PER_PAGE_DEBOUNCE_MS
																	);
															} }
														/>
													</PanelBody>
												</Panel>
											</div>
										) }
									</Animate>
								</div>
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
												// Only show collections that have results or are still loading
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
													<Notice
														status="info"
														isDismissible={ false }
													>
														{ __(
															'No icons found matching your search.',
															'blockparty-icons'
														) }
													</Notice>
												</div>
											);
										}

										const isSearching =
											!! debouncedSearchInput.trim();
										const firstCollectionWithResults =
											filteredCollections.find(
												( c ) => c.icons?.length > 0
											);
										const initialTabName =
											firstCollectionWithResults?.name ??
											filteredCollections[ 0 ]?.name;

										return (
											<TabPanel
												key={ `icons-tabpanel-${ debouncedSearchInput }` }
												initialTabName={
													initialTabName
												}
												tabs={ filteredCollections.map(
													( c ) => ( {
														name: c.name,
														title: isSearching
															? sprintf(
																	/* translators: 1: collection name, 2: number of results */
																	__(
																		'%1$s (%2$d)',
																		'blockparty-icons'
																	),
																	capitalize(
																		c.name
																	),
																	c.icons
																		?.length ??
																		0
															  )
															: capitalize(
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
																	{ !! c.icons
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
																				<FlexItem className="blockparty-icons__pagination-wrapper">
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
																</Fragment>
															)
													)
												}
											</TabPanel>
										);
									} )() }
							</div>
						</div>
					) }
				</Modal>
			) }
		</>
	);
}

export default IconModal;
