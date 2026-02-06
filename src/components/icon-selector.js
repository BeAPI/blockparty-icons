/**
 * WordPress dependencies
 */
import { memo } from '@wordpress/element';
import { Button } from '@wordpress/components';

import IconContent from './icon-content';
import { capitalize } from '../utils';

/**
 * IconSelector component
 *
 * @param {Object} props
 * @param {Function} props.handleIconSelectButtonClick
 * @param {Object} props.icon
 * @param {string} props.iconColorValue
 * @param {number} props.padding
 * @param {number} props.size
 * @param {React.ReactNode} props.children
 */
function IconSelector( {
	handleIconSelectButtonClick,
	icon,
	iconColorValue,
	padding,
	size,
	children,
} ) {
	return (
		<Button
			className="block-editor-block-types-list__item"
			onClick={ () => handleIconSelectButtonClick( icon ) }
		>
			<span className="block-editor-block-types-list__item-icon">
				<span className="block-editor-block-icon has-colors">
					<IconContent
						iconData={ {
							content: icon.content,
							icon,
							iconColor: iconColorValue,
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
