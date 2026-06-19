/**
 * WordPress dependencies
 */
import { memo } from '@wordpress/element';
import { Button, Tooltip } from '@wordpress/components';

import IconContent from './icon-content';
import { capitalize } from '../utils';

/**
 * IconSelector component
 *
 * @param {Object}   props
 * @param {Object}   props.collection                  Collection object
 * @param {Function} props.handleIconSelectButtonClick Called when icon is selected
 * @param {Object}   props.icon                        Icon object
 * @param {string}   props.iconColor                   Icon color
 * @param {number}   props.padding                     Padding value
 * @param {number}   props.size                        Icon size
 * @param {boolean}  props.allowStyling                Keep the SVG's own style/id/<style> (media library)
 * @param {boolean}  props.allowClassName              Keep the SVG's own class attributes
 * @param {boolean}  props.preview                     Mark the preview <svg> so recolor styles skip it
 * @param {*}        props.children                    Child content
 */
function IconSelector( {
	handleIconSelectButtonClick,
	collection,
	icon,
	iconColor,
	padding,
	size,
	allowStyling,
	allowClassName,
	preview,
	children,
} ) {
	return (
		<Tooltip text={ capitalize( icon.label || icon.name ) }>
			<Button
				className="block-editor-block-types-list__item"
				onClick={ () =>
					handleIconSelectButtonClick( collection, icon )
				}
			>
				<span className="block-editor-block-types-list__item-icon">
					<span className="block-editor-block-icon has-colors">
						<IconContent
							iconData={ {
								content: icon.content,
								icon,
								iconColor,
								padding,
								size,
							} }
							type={ icon.type }
							allowStyling={ allowStyling }
							allowClassName={ allowClassName }
							preview={ preview }
						/>
					</span>
				</span>
				<span className="block-editor-block-types-list__item-title">
					{ children }
				</span>
			</Button>
		</Tooltip>
	);
}

export default memo( IconSelector );
