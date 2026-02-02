/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Popover, SearchControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import IconSelector from './icon-selector';

import { useState } from '@wordpress/element';
import { getStoredIcons } from '../utils';

export default function IconPopover( {
	anchor,
	handleBrowseAllClick,
	handleIconSelectButtonClick,
	icons,
	placement = 'bottom-start',
} ) {
	const [ searchInput, setSearchInput ] = useState( '' );
	const storedIcons = getStoredIcons();
	const popoverIconsList = storedIcons.concat(
		icons
			.filter(
				( icon ) =>
					! storedIcons.some( ( sIcon ) => sIcon.name === icon.name )
			)
			.slice( 0, 9 - storedIcons.length )
	);

	return (
		<>
			<Popover
				className="wp-block-blockparty-icons__popover"
				anchor={ anchor }
				placement={ placement }
				variant="toolbar"
			>
				<SearchControl
					label={ __( 'Search an icon', 'blockparty-icons' ) }
					value={ searchInput }
					onChange={ setSearchInput }
				/>
				<ul className="wp-block-blockparty-icons__list-icon">
					{ popoverIconsList &&
						popoverIconsList
							.filter(
								( icon ) =>
									! searchInput.length ||
									icon.name
										.toLowerCase()
										.includes( searchInput.toLowerCase() )
							)
							.map( ( icon, index ) => (
								<li key={ `${ icon.name }-${ index }` }>
									<IconSelector
										icon={ icon }
										handleIconSelectButtonClick={
											handleIconSelectButtonClick
										}
									/>
								</li>
							) ) }
				</ul>

				<Button
					className="block-editor-inserter__quick-inserter-expand"
					onClick={ handleBrowseAllClick }
				>
					{ __( 'Browse all', 'blockparty-icons' ) }
				</Button>
			</Popover>
		</>
	);
}
