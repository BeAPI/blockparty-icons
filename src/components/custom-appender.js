import { __ } from '@wordpress/i18n';
import { createBlock } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { Icon, plus } from '@wordpress/icons';
import { IconButton } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { getCollections, getAllIcons, setStoredIcons } from '../utils';
import IconModal from '../components/icon-modal';

/**
 * @param {Object}   root0
 * @param {boolean} root0.disabled
 * @param {string}  root0.rootClientId
 */
function CustomInserter( { disabled, rootClientId } ) {
	const [ icons, setIcons ] = useState( null );
	const [ collections, setCollections ] = useState( null );
	const [ isModalVisible, setIsModalVisible ] = useState( false );

	const { insertBlocks } = useDispatch( blockEditorStore );
	const innerBlockCount = useSelect(
		( select ) => select( blockEditorStore ).getBlockCount( rootClientId ),
		[ rootClientId ]
	);

	// Load available collections when the component is mount.
	useEffect( () => {
		getCollections( { context: 'edit' } ).then( ( items ) => {
			setCollections( items );
		} );
	}, [] );

	// Load available icons each time the collection change.
	useEffect( () => {
		if ( collections ) {
			getAllIcons( {
				context: 'edit',
			} ).then( ( items ) => {
				setIcons(
					Object.keys( items ).map( ( item ) => items[ item ] )
				);
			} );
		}
	}, [ collections ] );

	/**
	 * Insert an icon-item block with the selected icon and close the modal.
	 *
	 * @typedef {Object} IconObject
	 * @property {string} content Icon SVG content
	 * @property {string} label   Icon label
	 * @property {string} name    Icon name
	 * @property {string} type    Icon type
	 *
	 * @param {IconObject} selectedIcon Icon object to select
	 */
	function selectIcon( selectedIcon ) {
		setStoredIcons( selectedIcon );
		const {
			content: iconContent,
			label: iconLabel,
			name: iconName,
			type: iconType,
		} = selectedIcon;
		const newBlock = createBlock( 'blockparty/icon', {
			content: iconContent,
			icon: { label: iconLabel, name: iconName, type: iconType },
		} );
		insertBlocks( newBlock, innerBlockCount, rootClientId );
		setIsModalVisible( false );
	}

	return (
		<>
			{ isModalVisible && (
				<IconModal
					collections={ collections }
					icons={ icons }
					onClose={ () => setIsModalVisible( false ) }
					handleIconSelectButtonClick={ selectIcon }
				/>
			) }
			<IconButton
				className="block-editor-inserter__toggle has-icon"
				onClick={ () => setIsModalVisible( true ) }
				disabled={ disabled }
				label={ __( 'Add an icon', 'blockparty-icons' ) }
				icon={ <Icon icon={ plus } /> }
			/>
		</>
	);
}

/**
 * @param {Object}  root0
 * @param {string} root0.rootClientId Parent block client ID (blockparty/icons).
 */
export default function CustomAppender( { rootClientId } ) {
	return (
		<CustomInserter
			disabled={ false }
			rootClientId={ rootClientId }
		/>
	);
}
