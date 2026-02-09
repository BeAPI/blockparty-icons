/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __, sprintf } from '@wordpress/i18n';

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
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';

import { ServerSideRender } from '@wordpress/server-side-render';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * WordPress dependencies
 */
import {
	Disabled,
	PanelBody,
	RangeControl,
	TextControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import {
	capitalize,
	getAllIcons,
	getCollections,
	setStoredIcons,
} from '../utils';
import { link, linkOff, replace, trash } from '@wordpress/icons';
import IconModal from '../components/icon-modal';
import LinkURLPopover from '../components/link-url-popover';

const DEFAULT_BORDER_RADIUS = 0;
const MIN_BORDER_RADIUS = 0;
const MAX_BORDER_RADIUS = 50;
const DEFAULT_SIZE = 48;
const MAX_SIZE = 256;
const MIN_SIZE = 8;

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @param {Object}   root0               Block props.
 * @param {Object}   root0.attributes    Block attributes.
 * @param {string}   root0.clientId      Block client ID.
 * @param {boolean}  root0.isSelected    Whether the block is selected.
 * @param {Function} root0.setAttributes Set block attributes.
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( {
	attributes,
	clientId,
	isSelected,
	setAttributes,
} ) {
	const { borderRadius, content, iconColor, icon, label, size, url } =
		attributes;

	const blockRef = useRef( null );
	const [ icons, setIcons ] = useState( null );
	const [ , setIconColor ] = useState( iconColor );
	const [ collections, setCollections ] = useState( null );
	const [ isModalVisible, setIsModalVisible ] = useState( false );
	const [ showURLPopover, setPopover ] = useState( false );
	const [ popoverAnchor, setPopoverAnchor ] = useState();
	const { removeBlock } = useDispatch( 'core/block-editor' );

	const openLinkPopover = () => {
		setPopoverAnchor( blockRef.current );
		setPopover( true );
	};

	const removeLink = () => {
		setAttributes( { url: '' } );
	};

	const openIconModal = () => {
		setIsModalVisible( true );
	};

	const removeIconBlock = () => {
		removeBlock( clientId );
	};

	/**
	 * Update state icon and add it to localStorage
	 *
	 * @typedef {Object} IconObject
	 * @property {string}     content      Icon SVG content
	 * @property {string}     label        Icon label
	 * @property {string}     name         Icon name
	 * @property {string}     type         Icon type
	 *
	 * @param    {IconObject} selectedIcon Icon object to select
	 */
	const selectIcon = useCallback(
		( selectedIcon ) => {
			setStoredIcons( selectedIcon );

			const {
				content: iconContent,
				label: iconLabel,
				name: iconName,
				type: iconType,
			} = selectedIcon;

			setAttributes( {
				content: iconContent,
				icon: {
					label: iconLabel,
					name: iconName,
					type: iconType,
				},
			} );
		},
		[ setAttributes ]
	);

	const handleIconSelectFromModal = useCallback(
		( selectedIcon ) => {
			selectIcon( selectedIcon );
			setIsModalVisible( false );
		},
		[ selectIcon ]
	);

	const colorSettings = [
		{
			value: iconColor,
			onChange: ( colorValue ) => {
				setIconColor( colorValue );
				setAttributes( { iconColor: colorValue } );
			},
			label: __( 'Icon color', 'blockparty-icons' ),
			resetAllFilter: () => {
				setIconColor( undefined );
				setAttributes( { iconColor: undefined } );
			},
		},
	];
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

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

	// Open icon modal when block is selected and no icon is set (e.g. after insert via native appender).
	useEffect( () => {
		const hasNoIcon = ! icon || ! content;

		if ( isSelected && hasNoIcon && collections ) {
			setIsModalVisible( true );
		}
	}, [ isSelected, icon, content, collections ] );

	return (
		<div { ...useBlockProps( { ref: blockRef } ) }>
			{ isModalVisible && (
				<IconModal
					collections={ collections }
					onClose={ () => setIsModalVisible( false ) }
					handleIconSelectButtonClick={ handleIconSelectFromModal }
				/>
			) }
			{ icon && (
				<>
					<BlockControls group="other">
						<ToolbarButton
							icon={ url ? linkOff : link }
							onClick={ url ? removeLink : openLinkPopover }
							label={ __( 'Link', 'blockparty-icons' ) }
						/>
					</BlockControls>
					<BlockControls>
						<ToolbarGroup>
							<ToolbarButton
								icon={ replace }
								onClick={ openIconModal }
								disabled={ ! Boolean( icons ) }
								label={ __(
									'Replace icon',
									'blockparty-icons'
								) }
							/>
							<ToolbarButton
								icon={ trash }
								onClick={ removeIconBlock }
								label={ __(
									'Remove icon',
									'blockparty-icons'
								) }
							/>
						</ToolbarGroup>
					</BlockControls>
					{ showURLPopover && (
						<LinkURLPopover
							url={ url }
							setAttributes={ setAttributes }
							setPopover={ setPopover }
							popoverAnchor={ popoverAnchor }
							clientId={ clientId }
						/>
					) }
					{ url && url.length > 0 && (
						<InspectorControls>
							<PanelBody
								title={ sprintf(
									/* translators: %s: name of the icon name. */
									__( '%s label' ),
									capitalize( icon?.label || icon?.name )
								) }
							>
								<TextControl
									label={ __( 'Link label' ) }
									help={ __(
										'Briefly describe the link to help screen reader users.'
									) }
									value={ label || '' }
									onChange={ ( value ) =>
										setAttributes( { label: value } )
									}
								/>
							</PanelBody>
						</InspectorControls>
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
					<InspectorControls group="border">
						<div className="full-width-control-wrapper">
							<RangeControl
								label={ __(
									'Icon radius',
									'blockparty-icons'
								) }
								value={ borderRadius }
								onChange={ ( newBorderRadius ) => {
									setAttributes( {
										borderRadius: newBorderRadius,
									} );
								} }
								initialPosition={ DEFAULT_BORDER_RADIUS }
								min={ MIN_BORDER_RADIUS }
								max={ MAX_BORDER_RADIUS }
								allowReset={ true }
								withInputField={ true }
								renderTooltipContent={ ( value ) =>
									`${ value }%`
								}
							/>
						</div>
					</InspectorControls>
					<InspectorControls group="dimensions">
						<div className="full-width-control-wrapper">
							<RangeControl
								label={ __( 'Icon size', 'blockparty-icons' ) }
								value={ size }
								onChange={ ( newSize ) => {
									setAttributes( { size: newSize } );
								} }
								initialPosition={ DEFAULT_SIZE }
								min={ MIN_SIZE }
								max={ MAX_SIZE }
								allowReset={ true }
								withInputField={ true }
								renderTooltipContent={ ( value ) =>
									`${ value }px`
								}
							/>
						</div>
					</InspectorControls>
				</>
			) }

			<Disabled>
				<ServerSideRender
					block="blockparty/icon"
					attributes={ attributes }
				/>
			</Disabled>
		</div>
	);
}
