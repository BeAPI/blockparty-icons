/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import {
	BlockControls,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
	InnerBlocks,
} from '@wordpress/block-editor';

/**
 * WordPress dependencies
 */
import { Button, Placeholder, ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import CustomAppender from '../components/custom-appender';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * Internal dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import {
	getAllIcons,
	getCollections,
	hasBlockAncestors,
	setStoredIcons,
} from '../utils';
import IconModal from '../components/icon-modal';

/**
 * External dependencies
 */
import { plusCircle } from '@wordpress/icons';
import { shapes } from '@beapi/icons';

const ALLOWED_BLOCKS = [ 'blockparty/icon' ];

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @param {Object}   root0
 * @param {string}   root0.name
 * @param {Object}   root0.attributes
 * @param {string}   root0.attributes.iconColor
 * @param {number}   root0.attributes.maxIcons
 * @param {string}   root0.clientId
 * @param {Function} root0.setAttributes
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { name, attributes, clientId, setAttributes } ) {
	const { iconColor, maxIcons } = attributes;
	const hasAncestor = hasBlockAncestors( clientId, name );
	const { insertBlocks } = useDispatch( 'core/block-editor' );
	const innerBlockCount = useSelect(
		( selectStore ) =>
			selectStore( 'core/block-editor' ).getBlockCount( clientId ),
		[ clientId ]
	);
	const [ icons, setIcons ] = useState( null );
	const [ isIconModalOpen, setIsIconModalOpen ] = useState( false );
	const [ collections, setCollections ] = useState( null );

	useEffect( () => {
		if ( isIconModalOpen && ! collections ) {
			getCollections( { context: 'edit' } ).then( setCollections );
		}
	}, [ isIconModalOpen ] );

	const openIconModal = () => {
		if ( innerBlockCount >= maxIcons ) return;
		setIsIconModalOpen( true );
	};

	const handleIconSelect = ( selectedIcon ) => {
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
		insertBlocks( newBlock, innerBlockCount, clientId );
		setIsIconModalOpen( false );
	};
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		templateInsertUpdatesSelection: true,
		renderAppender: () =>
			innerBlockCount < maxIcons && innerBlockCount > 0 ? (
				<CustomAppender rootClientId={ clientId } />
			) : (
				false
			),
		placeholder: (
			<Placeholder
				icon={ shapes }
				label={ __( 'Add your first icon', 'blockparty-icons' ) }
				instructions={ __(
					'You can pick an icon from your library by clicking the button below.',
					'blockparty-icons'
				) }
			>
				<Button variant="primary" onClick={ openIconModal }>
					{ __( 'Add icon', 'blockparty-icons' ) }
				</Button>
			</Placeholder>
		),
		orientation: 'horizontal',
		directInsert: hasAncestor && innerBlockCount < maxIcons,
	} );

	const colorSettings = [
		{
			// Use custom attribute as fallback to prevent loss of named color selection when
			// switching themes to a new theme that does not have a matching named color.
			value: iconColor,
			onChange: ( colorValue ) => {
				setAttributes( { iconColor: colorValue } );
			},
			label: __( 'Icons color', 'blockparty-icons' ),
			resetAllFilter: () => {
				setAttributes( { iconColor: undefined } );
			},
		},
	];
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Load available icons each time the collection change.
	useEffect( () => {
		getAllIcons( {
			context: 'edit',
		} ).then( ( items ) => {
			setIcons( Object.keys( items ).map( ( item ) => items[ item ] ) );
		} );
	}, [] );

	if ( icons && ! icons.length ) {
		return (
			<Placeholder
				icon={ shapes }
				label={ __( 'No icons available', 'blockparty-icons' ) }
				instructions={ __(
					'You must set one or more icon collections from your theme or plugin.',
					'blockparty-icons'
				) }
			/>
		);
	}

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={ plusCircle }
						onClick={ openIconModal }
						disabled={ innerBlockCount >= maxIcons }
						label={ __( 'Add icon', 'blockparty-icons' ) }
					/>
				</ToolbarGroup>
			</BlockControls>
			{ isIconModalOpen && (
				<IconModal
					collections={ collections }
					onClose={ () => setIsIconModalOpen( false ) }
					handleIconSelectButtonClick={ handleIconSelect }
				/>
			) }
			<InspectorControls group="color">
				{ colorSettings.map(
					( {
						onChange,
						label: colorLabel,
						value,
						resetAllFilter,
					} ) => (
						// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
						<ColorGradientSettingsDropdown
							key={ `blockparty-icon-color-${ colorLabel }` }
							__experimentalIsRenderedInSidebar
							settings={ [
								{
									colorValue: value,
									label: colorLabel,
									onColorChange: onChange,
									isShownByDefault: true,
									resetAllFilter,
									enableAlpha: true,
								},
							] }
							panelId={ clientId }
							{ ...colorGradientSettings }
						/>
					)
				) }
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}
