/**
 * WordPress dependencies
 */
import { memo } from '@wordpress/element';
import { Button } from '@wordpress/components';

import IconContent from './icon-content';

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
 * @param {*}        props.children                    Child content
 */
function IconSelector( {
	handleIconSelectButtonClick,
	collection,
	icon,
	iconColor,
	padding,
	size,
	children,
} ) {
	return (
		<Button
			className="block-editor-block-types-list__item"
			onClick={ () => handleIconSelectButtonClick( collection, icon ) }
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
					/>
				</span>
			</span>
			<span className="block-editor-block-types-list__item-title">
				{ children }
			</span>
		</Button>
	);
}

export default memo( IconSelector );
