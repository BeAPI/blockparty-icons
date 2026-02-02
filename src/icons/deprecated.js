/**
 * External dependencies
 */
import parse from 'html-react-parser';
import classNames from 'classnames';

/**
 * WordPress dependencies
 */
import { Icon } from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

function IconRender( {
	attributes: { className, collection, icon, url, content, width },
	color,
} ) {
	if ( ! collection || ! icon || ( ! url && ! content ) ) {
		return <></>;
	}

	const iconStyle = {
		width: '100%',
		height: '100%',
	};

	const iconClassNames = classNames(
		'icon',
		{ [ `icon-${ icon.name }` ]: ! icon.name.startsWith( 'icon-' ) }, // css class generated if icon name doesn't start with icon-
		{ [ `${ icon.name }` ]: icon.name.startsWith( 'icon-' ) }, // css class generated if icon name start with icon-
		className // combine with custom classnames
	);

	const blockStyles = {
		width,
		height: width,
		backgroundColor: color && color.slug ? undefined : color.color,
	};

	let blockClassName = '';

	blockClassName = classNames(
		'icon-container',
		{
			[ `has-${ color.slug }-background-color` ]: !! (
				color && color.slug
			),
		} // css class generated is slug is set
	);

	switch ( icon.type ) {
		case 'file':
		case 'sprite':
			return (
				<div className={ blockClassName } style={ blockStyles }>
					<svg
						className={ iconClassNames }
						style={ iconStyle }
						focusable="false"
						aria-hidden="true"
					>
						<use href={ url } />
					</svg>
				</div>
			);
		default:
			return (
				<div style={ blockStyles } className={ blockClassName }>
					<Icon
						className={ iconClassNames }
						icon={ () =>
							parse( content, {
								trim: true,
								replace: ( domNode ) => {
									// TODO: Very basic SVG sanitization, needs more refinement.
									if (
										domNode.type !== 'tag' ||
										( ! domNode.parent &&
											domNode.name !== 'svg' ) ||
										! domNode.name
									) {
										return <></>;
									}
								},
							} )
						}
					/>
				</div>
			);
	}
}

const DEPRECATED_ATTRIBUTES_V1 = {
	collection: {
		type: 'object',
	},
	icon: {
		type: 'object',
	},
	url: {
		type: 'string',
	},
	content: {
		type: 'string',
		source: 'html',
		selector: '.icon-container',
		default: '',
	},
	width: {
		type: 'integer',
		default: 48,
	},
	iconBackground: {
		type: 'string',
	},
	customIconBackground: {
		type: 'string',
	},
	layout: {
		type: 'object',
	},
};

const v1 = {
	attributes: DEPRECATED_ATTRIBUTES_V1,
	supports: {
		html: false,
	},
	isEligible: ( { borderRadius, padding, size } ) =>
		!! borderRadius || !! padding || ! size,
	migrate( attributes, innerBlocks ) {
		const {
			content,
			customIconBackground,
			icon,
			iconBackground,
			width,
			...restAttributes
		} = attributes;

		// Check if icon exists before destructuring
		if ( ! icon ) {
			return [ attributes, innerBlocks ];
		}

		const { label, name, type } = icon;

		return [
			restAttributes,
			[
				createBlock( 'blockparty/icon', {
					content,
					icon: {
						label,
						name,
						type,
					},
					size: width,
				} ),
				...innerBlocks,
			],
		];
	},
	save( { attributes } ) {
		const { customIconBackground, iconBackground } = attributes;
		const colorObject = {
			slug: iconBackground,
			color: customIconBackground,
		};

		return (
			<div { ...useBlockProps.save() }>
				<IconRender attributes={ attributes } color={ colorObject } />
			</div>
		);
	},
};

const DEPRECATED_ATTRIBUTES_V2 = {
	collection: {
		type: 'object',
	},
	icon: {
		type: 'object',
	},
	url: {
		type: 'string',
	},
	content: {
		type: 'string',
		source: 'html',
		selector: '.icon-container',
		default: '',
	},
	size: {
		type: 'integer',
		default: 48,
	},
	padding: {
		type: 'object',
		default: {
			top: '0px',
			left: '0px',
			right: '0px',
			bottom: '0px',
		},
	},
	borderRadius: {
		type: 'integer',
		default: 0,
	},
	iconColor: {
		type: 'object',
		default: {},
	},
	backgroundColor: {
		type: 'object',
		default: {},
	},
	mini: {
		type: 'boolean',
		default: false,
	},
	layout: {
		type: 'object',
	},
};

/**
 * v2 single-icon format: block comment has collection + icon + url, no inner blocks.
 * Must be tried first so validation uses this save (matches saved HTML) instead of v1.
 */
const v2SingleIcon = {
	attributes: DEPRECATED_ATTRIBUTES_V2,
	supports: {
		html: false,
	},
	isEligible( attributes, innerBlocks ) {
		return (
			innerBlocks.length === 0 &&
			!! attributes.icon &&
			!! attributes.collection &&
			( !! attributes.url || !! attributes.content )
		);
	},
	migrate( attributes, innerBlocks ) {
		return v2.migrate( attributes, innerBlocks );
	},
	save( { attributes } ) {
		return v2.save( { attributes } );
	},
};

const v2 = {
	attributes: DEPRECATED_ATTRIBUTES_V2,
	supports: {
		html: false,
	},
	isEligible: ( { borderRadius, padding, size } ) =>
		!! borderRadius || !! padding || ! size,
	migrate( attributes, innerBlocks ) {
		const {
			backgroundColor,
			borderRadius,
			content,
			customIconBackground,
			icon,
			iconColor,
			padding,
			size,
			...restAttributes
		} = attributes;

		// Check if icon exists before destructuring
		if ( ! icon ) {
			return [ attributes, innerBlocks ];
		}

		const { label, name, type } = icon;

		let newContent = content;
		const matchedContent = content.match( /<use\s+href="([^"]*)"><\/use>/ );

		if ( matchedContent ) {
			newContent = matchedContent[ 1 ];
		}

		return [
			restAttributes,
			[
				createBlock( 'blockparty/icon', {
					borderRadius,
					content: newContent,
					icon: {
						label,
						name,
						type,
					},
					iconColorValue: iconColor.color,
					padding,
					text: '',
					size,
				} ),
				...innerBlocks,
			],
		];
	},
	save( { attributes } ) {
		const {
			backgroundColor,
			borderRadius,
			className,
			collection,
			content,
			icon,
			iconColor,
			padding,
			size,
			url,
		} = attributes;

		if ( icon && collection ) {
			if ( ! collection || ! icon || ( ! url && ! content ) ) {
				return <></>;
			}

			const iconStyle = {
				width: size,
				height: size,
			};

			const iconClassNames = classNames(
				'icon',
				{
					[ `icon-${ icon.name }` ]:
						Boolean( icon.name ) &&
						! icon.name.startsWith( 'icon-' ),
				}, // css class generated if icon name doesn't start with icon-
				{
					[ `${ icon.name }` ]:
						Boolean( icon.name ) && icon.name.startsWith( 'icon-' ),
				}, // css class generated if icon name start with icon-
				className // combine with custom classnames
			);

			const blockStyles = {
				borderRadius: `${ borderRadius }%`,
				display: 'inline-block',
				backgroundColor: backgroundColor.color,
				color: iconColor.color,
			};

			if ( Boolean( padding ) ) {
				blockStyles.padding = `${ padding.top } ${ padding.right } ${ padding.bottom } ${ padding.left }`;
			}

			// css class generated is slug is set
			let blockClassName = `icon-container`;

			if ( Boolean( iconColor ) && Boolean( iconColor.length ) ) {
				blockClassName += ` has-text-color`;
			}

			if ( Boolean( iconColor.slug ) ) {
				blockClassName += ` has-${ iconColor.slug }-text-color`;
			}

			if (
				Boolean( backgroundColor ) &&
				Boolean( backgroundColor.length )
			) {
				blockClassName += ` has-background-color`;
			}

			if ( Boolean( backgroundColor.slug ) ) {
				blockClassName += ` has-${ backgroundColor.slug }-background-color`;
			}

			switch ( icon.type ) {
				case 'sprite':
					return (
						<div { ...useBlockProps.save() }>
							<div
								className={ blockClassName }
								style={ blockStyles }
							>
								<svg
									className={ iconClassNames }
									style={ iconStyle }
									focusable="false"
									aria-hidden="true"
								>
									<use href={ url } />
								</svg>
							</div>
						</div>
					);
				default:
					return (
						<div { ...useBlockProps.save() }>
							<div
								style={ blockStyles }
								className={ blockClassName }
							>
								<Icon
									icon={ () =>
										parse( content, {
											trim: true,
											replace: ( domNode ) => {
												if (
													domNode.type === 'tag' &&
													domNode.name === 'svg'
												) {
													domNode.attribs.class = `${ icon.name } icon`;
													domNode.attribs.focusable =
														'false';
													domNode.attribs[
														'aria-hidden'
													] = 'true';
													domNode.attribs.style = `width:${ size }px;height:${ size }px;`;
												}
												if (
													domNode.type !== 'tag' ||
													( ! domNode.parent &&
														domNode.name !==
															'svg' ) ||
													! domNode.name
												) {
													return <></>;
												}
											},
										} )
									}
								/>
							</div>
						</div>
					);
			}
		}
	},
};

const v3 = {
	attributes: {
		iconColor: { type: 'string' },
		maxIcons: { type: 'number', default: 999 },
	},
	supports: {
		html: false,
	},
	isEligible( attributes, innerBlocks ) {
		return innerBlocks.length === 0;
	},
	save() {
		return <ul className="wp-block-blockparty-icons" />;
	},
	migrate( attributes, innerBlocks ) {
		return [
			{ maxIcons: 999 },
			[ ...innerBlocks ],
		];
	},
};

const deprecated = [ v1, v2, v3 ];

export default deprecated;
